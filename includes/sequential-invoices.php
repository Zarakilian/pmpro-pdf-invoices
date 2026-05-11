<?php
/**
 * Sequential Invoice Numbers for PMPro PDF Invoices
 * 
 * This file adds sequential invoice numbering functionality to the plugin.
 * When enabled, PMPro order codes are replaced with sequential numbers 
 * (e.g., INV-0001) at order creation time.
 * 
 * @since 2.1
 */

// Prevent direct access
defined( 'ABSPATH' ) or exit;

// Define option names
if ( ! defined( 'PMPRO_PDF_SEQUENTIAL_ENABLED' ) ) {
	define( 'PMPRO_PDF_SEQUENTIAL_ENABLED', 'pmpro_pdf_sequential_enabled' );
	define( 'PMPRO_PDF_SEQUENTIAL_PREFIX', 'pmpro_pdf_sequential_prefix' );
	define( 'PMPRO_PDF_SEQUENTIAL_SUFFIX', 'pmpro_pdf_sequential_suffix' );
	define( 'PMPRO_PDF_SEQUENTIAL_NEXT', 'pmpro_pdf_sequential_next' );
	define( 'PMPRO_PDF_SEQUENTIAL_PADDING', 'pmpro_pdf_sequential_padding' );
	define( 'PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR', 'pmpro_pdf_sequential_include_year' );
}

/**
 * Check if sequential invoice numbers are enabled
 * 
 * @since 2.1
 * @return bool
 */
function pmpropdf_sequential_enabled() {
	return (bool) get_option( PMPRO_PDF_SEQUENTIAL_ENABLED, false );
}

/**
 * Format a sequential number with prefix, suffix, padding, and year
 * 
 * @since 2.1
 * @param int $number The raw sequential number
 * @return string The formatted invoice number
 */
function pmpropdf_format_sequential_number( $number ) {
	$prefix = get_option( PMPRO_PDF_SEQUENTIAL_PREFIX, 'INV-' );
	$suffix = get_option( PMPRO_PDF_SEQUENTIAL_SUFFIX, '' );
	$padding = intval( get_option( PMPRO_PDF_SEQUENTIAL_PADDING, 4 ) );
	$include_year = (bool) get_option( PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR, false );
	$year_placement = get_option( 'pmpro_pdf_sequential_year_placement', 'after_prefix' );
	
	// Pad the number with zeros
	$padded_number = str_pad( $number, $padding, '0', STR_PAD_LEFT );
	
	// Build the invoice number
	$invoice_number = $prefix;
	
	// Add year if enabled
	if ( $include_year ) {
		$year = date( 'Y' );
		
		if ( $year_placement === 'after_prefix' ) {
			$invoice_number .= $year . '-';
		}
	}
	
	$invoice_number .= $padded_number;
	
	// Add year at end if configured
	if ( $include_year && $year_placement === 'before_suffix' ) {
		$invoice_number .= '-' . date( 'Y' );
	}
	
	$invoice_number .= $suffix;
	
	/**
	 * Filter the formatted sequential invoice number
	 * 
	 * @since 2.1
	 * @param string $invoice_number The formatted invoice number
	 * @param int $number The raw sequential number
	 */
	return apply_filters( 'pmpropdf_sequential_invoice_number', $invoice_number, $number );
}

/**
 * Get the next sequential invoice number and increment the counter
 * 
 * Uses WordPress transients for simple locking to prevent race conditions.
 * 
 * @since 2.1
 * @return string The formatted sequential invoice number
 */
function pmpropdf_get_next_sequential_number() {
	// Get next number
	$next_number = intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) );
	
	// Use WordPress transient for simple locking (10 second lock)
	$lock_key = 'pmpropdf_seq_lock_' . uniqid();
	$lock_acquired = false;
	$max_attempts = 10;
	
	for ( $i = 0; $i < $max_attempts; $i++ ) {
		if ( false === get_transient( $lock_key ) ) {
			set_transient( $lock_key, true, 10 );
			$lock_acquired = true;
			break;
		}
		usleep( 200000 ); // 200ms
	}
	
	if ( ! $lock_acquired ) {
		// Log lock failure
		error_log( "PMPro PDF: Sequential number lock failed, proceeding anyway" );
	}
	
	// Double-check after acquiring lock
	$next_number = intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) );
	
	// Increment for next time
	update_option( PMPRO_PDF_SEQUENTIAL_NEXT, $next_number + 1 );
	
	// Release lock
	if ( $lock_acquired ) {
		delete_transient( $lock_key );
	}
	
	// Format and return
	return pmpropdf_format_sequential_number( $next_number );
}

/**
 * Override PMPro order code with sequential invoice number
 * 
 * This filter hooks into pmpro_random_code to replace PMPro's random
 * order codes with sequential invoice numbers when enabled.
 * 
 * @since 2.1
 * @param string $code The original random code from PMPro
 * @return string The sequential invoice number or original code
 */
function pmpropdf_override_order_code( $code ) {
	// Only override if sequential numbers are enabled
	if ( ! pmpropdf_sequential_enabled() ) {
		return $code;
	}
	
	// Get the next sequential number
	$sequential_code = pmpropdf_get_next_sequential_number();
	
	/**
	 * Fires when a sequential invoice number is assigned
	 * 
	 * @since 2.1
	 * @param string $sequential_code The sequential invoice number
	 */
	do_action( 'pmpropdf_sequential_number_assigned', $sequential_code );
	
	return $sequential_code;
}
// Hook into PMPro's random code filter
add_filter( 'pmpro_random_code', 'pmpropdf_override_order_code', 10, 1 );

/**
 * Add sequential invoice number template variable (for backwards compatibility)
 * 
 * When sequential numbers are enabled, this just returns the order code.
 * When disabled, it also returns the order code (for template compatibility).
 * 
 * @since 2.1
 * @param array $replacements The template variable replacements
 * @param object $order_data The order data object
 * @return array Modified replacements array
 */
function pmpropdf_add_sequential_template_replacement( $replacements, $order_data ) {
	// {{sequential_invoice_number}} always shows the order code
	// (which is now the sequential number when enabled)
	$replacements['{{sequential_invoice_number}}'] = $order_data->code ?: '';
	
	return $replacements;
}
add_filter( 'pmpro_pdf_invoice_custom_variables', 'pmpropdf_add_sequential_template_replacement', 10, 2 );

/**
 * Migration tool: Reassign sequential numbers to existing orders
 * 
 * WARNING: This will change order codes in the database. Use with caution.
 * This is mainly useful for sites that want to apply sequential numbers
 * to historical orders after enabling the feature.
 * 
 * @since 2.1
 * @param int $batch_size Number of orders to process per batch
 * @param bool $dry_run If true, returns what would happen without making changes
 * @return array Stats array with 'processed', 'skipped', 'errors', 'preview'
 */
function pmpropdf_migrate_existing_orders_to_sequential( $batch_size = 100, $dry_run = false ) {
	global $wpdb;
	
	if ( ! pmpropdf_sequential_enabled() ) {
		return array( 'error' => 'Sequential invoice numbers are not enabled' );
	}
	
	$stats = array(
		'processed' => 0,
		'skipped' => 0,
		'errors' => 0,
		'preview' => array()
	);
	
	// Get orders that look like random codes (not already sequential)
	// Sequential codes typically start with a prefix like "INV-"
	$prefix = get_option( PMPRO_PDF_SEQUENTIAL_PREFIX, 'INV-' );
	
	$orders = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, code, user_id, timestamp 
		 FROM {$wpdb->pmpro_membership_orders}
		 WHERE code NOT LIKE %s
		 ORDER BY timestamp ASC
		 LIMIT %d",
		$prefix . '%',
		$batch_size
	) );
	
	if ( empty( $orders ) ) {
		return $stats;
	}
	
	foreach ( $orders as $order ) {
		$new_code = pmpropdf_format_sequential_number( 
			intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) ) + $stats['processed']
		);
		
		if ( $dry_run ) {
			$stats['preview'][] = array(
				'id' => $order->id,
				'old_code' => $order->code,
				'new_code' => $new_code
			);
			$stats['processed']++;
			continue;
		}
		
		// Update the order code
		$updated = $wpdb->update(
			$wpdb->pmpro_membership_orders,
			array( 'code' => $new_code ),
			array( 'id' => $order->id ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( $updated ) {
			$stats['processed']++;
		} else {
			$stats['errors']++;
		}
	}
	
	// Update the next number counter
	if ( ! $dry_run && $stats['processed'] > 0 ) {
		$current_next = intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) );
		update_option( PMPRO_PDF_SEQUENTIAL_NEXT, $current_next + $stats['processed'] );
	}
	
	return $stats;
}

/**
 * AJAX handler for migrating existing orders
 * 
 * @since 2.1
 */
function pmpropdf_ajax_migrate_sequential() {
	// Security check
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}
	
	check_ajax_referer( 'pmpropdf_migrate_sequential', 'nonce' );
	
	if ( ! pmpropdf_sequential_enabled() ) {
		wp_send_json_error( array( 'message' => 'Sequential invoice numbers are not enabled' ) );
	}
	
	$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 50;
	$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'];
	
	$stats = pmpropdf_migrate_existing_orders_to_sequential( $batch_size, $dry_run );
	
	wp_send_json_success( $stats );
}
add_action( 'wp_ajax_pmpropdf_migrate_sequential', 'pmpropdf_ajax_migrate_sequential' );
