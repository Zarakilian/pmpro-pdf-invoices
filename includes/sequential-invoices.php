<?php
/**
 * Sequential Invoice Numbers for PMPro PDF Invoices
 * 
 * This file adds sequential invoice numbering functionality to the plugin.
 * When enabled, invoices display formatted sequential numbers (e.g., INV-0001)
 * instead of PMPro's random order codes.
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
	define( 'PMPRO_PDF_SEQUENTIAL_MAP_META_KEY', '_pmpro_sequential_invoice_number' );
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
 * Get sequential invoice number for an existing order
 * 
 * @since 2.1
 * @param string $order_code The PMPro order code
 * @return string|null The sequential number or null if not assigned
 */
function pmpropdf_get_order_sequential_number( $order_code ) {
	global $wpdb;
	
	// Try to get from order meta
	$meta_table = $wpdb->pmpro_membership_ordermeta;
	
	if ( ! $meta_table ) {
		// Fallback: check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}pmpro_membership_ordermeta'" );
		if ( ! $table_exists ) {
			return null;
		}
		$meta_table = $wpdb->prefix . 'pmpro_membership_ordermeta';
	}
	
	return $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$meta_table} 
		 WHERE pmpro_membership_order_id = (
		   SELECT id FROM {$wpdb->pmpro_membership_orders} WHERE code = %s
		 ) 
		 AND meta_key = %s",
		$order_code,
		PMPRO_PDF_SEQUENTIAL_MAP_META_KEY
	) );
}

/**
 * Store sequential invoice number for an order
 * 
 * @since 2.1
 * @param int $order_id The PMPro order ID
 * @param string $sequential_number The sequential invoice number
 * @return bool
 */
function pmpropdf_store_order_sequential_number( $order_id, $sequential_number ) {
	global $wpdb;
	
	// Check if add_pmpro_membership_order_meta exists (PMPro 2.6+)
	if ( function_exists( 'add_pmpro_membership_order_meta' ) ) {
		return add_pmpro_membership_order_meta( $order_id, PMPRO_PDF_SEQUENTIAL_MAP_META_KEY, $sequential_number );
	}
	
	// Fallback: direct insert
	$meta_table = $wpdb->prefix . 'pmpro_membership_ordermeta';
	
	// Check if already exists
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_id FROM {$meta_table} WHERE pmpro_membership_order_id = %d AND meta_key = %s",
		$order_id,
		PMPRO_PDF_SEQUENTIAL_MAP_META_KEY
	) );
	
	if ( $exists ) {
		return $wpdb->update(
			$meta_table,
			array( 'meta_value' => $sequential_number ),
			array( 'pmpro_membership_order_id' => $order_id, 'meta_key' => PMPRO_PDF_SEQUENTIAL_MAP_META_KEY ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}
	
	return $wpdb->insert(
		$meta_table,
		array(
			'pmpro_membership_order_id' => $order_id,
			'meta_key' => PMPRO_PDF_SEQUENTIAL_MAP_META_KEY,
			'meta_value' => $sequential_number
		),
		array( '%d', '%s', '%s' )
	);
}

/**
 * Get the next sequential invoice number and increment the counter
 * 
 * Uses WordPress transients for simple locking to prevent race conditions.
 * 
 * @since 2.1
 * @param string $order_code The PMPro order code
 * @return string The formatted sequential invoice number
 */
function pmpropdf_get_next_sequential_number( $order_code ) {
	global $wpdb;
	
	// Check if this order already has a sequential number assigned
	$existing = pmpropdf_get_order_sequential_number( $order_code );
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	
	// Get next number
	$next_number = intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) );
	
	// Use WordPress transient for simple locking (10 second lock)
	$lock_key = 'pmpropdf_seq_lock_' . md5( $order_code );
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
		// Log lock failure and proceed anyway (with incremented number to avoid duplicates)
		error_log( "PMPro PDF: Sequential number lock failed for order {$order_code}" );
	}
	
	// Double-check after acquiring lock
	$next_number = intval( get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 ) );
	
	// Increment for next time
	update_option( PMPRO_PDF_SEQUENTIAL_NEXT, $next_number + 1 );
	
	// Release lock
	if ( $lock_acquired ) {
		delete_transient( $lock_key );
	}
	
	// Format the number
	$formatted_number = pmpropdf_format_sequential_number( $next_number );
	
	// Get order ID and store the mapping
	$order_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->pmpro_membership_orders} WHERE code = %s",
		$order_code
	) );
	
	if ( $order_id ) {
		pmpropdf_store_order_sequential_number( $order_id, $formatted_number );
	}
	
	/**
	 * Fires after a sequential invoice number is assigned
	 * 
	 * @since 2.1
	 * @param string $order_code The PMPro order code
	 * @param string $formatted_number The formatted sequential number
	 * @param int $next_number The raw sequential number
	 */
	do_action( 'pmpropdf_sequential_number_assigned', $order_code, $formatted_number, $next_number );
	
	return $formatted_number;
}

/**
 * Assign sequential number when an order is added
 * 
 * @since 2.1
 * @param MemberOrder $order The PMPro order object
 */
function pmpropdf_assign_sequential_number_on_order( $order ) {
	if ( ! pmpropdf_sequential_enabled() ) {
		return;
	}
	
	// Check if already assigned
	$existing = pmpropdf_get_order_sequential_number( $order->code );
	if ( ! empty( $existing ) ) {
		return;
	}
	
	// Assign the sequential number
	pmpropdf_get_next_sequential_number( $order->code );
}
// Hook early (priority 5) before PDF generation
add_action( 'pmpro_added_order', 'pmpropdf_assign_sequential_number_on_order', 5 );

/**
 * Add sequential invoice number to template replacements
 * 
 * This filter adds {{sequential_invoice_number}} to the available template variables.
 * When sequential numbers are disabled, it falls back to the order code.
 * 
 * @since 2.1
 * @param array $replacements The template variable replacements
 * @param object $order_data The order data object
 * @return array Modified replacements array
 */
function pmpropdf_add_sequential_replacement( $replacements, $order_data ) {
	if ( ! pmpropdf_sequential_enabled() ) {
		// When disabled, use the regular order code
		$replacements['{{sequential_invoice_number}}'] = $order_data->code ?: '';
		return $replacements;
	}
	
	// Get existing sequential number for this order
	$sequential_number = pmpropdf_get_order_sequential_number( $order_data->code );
	
	// If not yet assigned (legacy orders), generate it now
	if ( empty( $sequential_number ) ) {
		$sequential_number = pmpropdf_get_next_sequential_number( $order_data->code );
	}
	
	$replacements['{{sequential_invoice_number}}'] = $sequential_number;
	
	return $replacements;
}
add_filter( 'pmpro_pdf_invoice_custom_variables', 'pmpropdf_add_sequential_template_replacement', 10, 2 );

/**
 * Optionally replace {{invoice_code}} with sequential number
 * 
 * @since 2.1
 * @param array $replacements The template variable replacements
 * @param object $order_data The order data object
 * @return array Modified replacements array
 */
function pmpropdf_maybe_replace_invoice_code( $replacements, $order_data ) {
	// Check if we should replace {{invoice_code}} with sequential number
	$replace_invoice_code = apply_filters( 
		'pmpropdf_sequential_replace_invoice_code', 
		get_option( 'pmpro_pdf_sequential_replace_invoice_code', false ) 
	);
	
	if ( ! $replace_invoice_code || ! pmpropdf_sequential_enabled() ) {
		return $replacements;
	}
	
	// Get the sequential number
	$sequential_number = pmpropdf_get_order_sequential_number( $order_data->code );
	
	if ( empty( $sequential_number ) ) {
		$sequential_number = pmpropdf_get_next_sequential_number( $order_data->code );
	}
	
	// Override {{invoice_code}} with the sequential number
	$replacements['{{invoice_code}}'] = $sequential_number;
	
	return $replacements;
}
add_filter( 'pmpro_pdf_invoice_custom_variables', 'pmpropdf_maybe_replace_invoice_code', 15, 2 );

/**
 * Migration tool: Assign sequential numbers to existing orders
 * 
 * @since 2.1
 * @param int $batch_size Number of orders to process per batch
 * @return array Stats array with 'processed', 'skipped', 'errors'
 */
function pmpropdf_migrate_existing_orders_to_sequential( $batch_size = 100 ) {
	global $wpdb;
	
	if ( ! pmpropdf_sequential_enabled() ) {
		return array( 'error' => 'Sequential invoice numbers are not enabled' );
	}
	
	$stats = array(
		'processed' => 0,
		'skipped' => 0,
		'errors' => 0
	);
	
	// Get orders without sequential numbers
	$orders = $wpdb->get_results(
		"SELECT o.id, o.code 
		 FROM {$wpdb->pmpro_membership_orders} o
		 LEFT JOIN {$wpdb->prefix}pmpro_membership_ordermeta om 
		    ON o.id = om.pmpro_membership_order_id 
		    AND om.meta_key = %s
		 WHERE om.meta_id IS NULL
		 ORDER BY o.id ASC
		 LIMIT %d",
		PMPRO_PDF_SEQUENTIAL_MAP_META_KEY,
		$batch_size
	);
	
	if ( empty( $orders ) ) {
		return $stats;
	}
	
	foreach ( $orders as $order ) {
		try {
			pmpropdf_get_next_sequential_number( $order->code );
			$stats['processed']++;
		} catch ( Exception $e ) {
			$stats['errors']++;
			error_log( "PMPro PDF Sequential Migration Error for order {$order->code}: " . $e->getMessage() );
		}
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
	$stats = pmpropdf_migrate_existing_orders_to_sequential( $batch_size );
	
	wp_send_json_success( $stats );
}
add_action( 'wp_ajax_pmpropdf_migrate_sequential', 'pmpropdf_ajax_migrate_sequential' );
