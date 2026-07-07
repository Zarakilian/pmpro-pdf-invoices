<?php
/**
 * Sequential Invoice Numbers for PMPro PDF Invoices
 *
 * This file adds sequential invoice numbering functionality to the plugin.
 * When enabled, invoice numbers are derived directly from the real PMPro
 * order ID (the auto-increment primary key of wp_pmpro_membership_orders)
 * and assigned only when an order is actually inserted into the database.
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
	define( 'PMPRO_PDF_SEQUENTIAL_OFFSET', 'pmpro_pdf_sequential_offset' );
	define( 'PMPRO_PDF_SEQUENTIAL_PADDING', 'pmpro_pdf_sequential_padding' );
	define( 'PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR', 'pmpro_pdf_sequential_include_year' );
	define( 'PMPRO_PDF_SEQUENTIAL_RESET_YEARLY', 'pmpro_pdf_sequential_reset_yearly' );
	define( 'PMPRO_PDF_SEQUENTIAL_YEAR_OFFSETS', 'pmpro_pdf_sequential_year_offsets' );
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
 * Work out the raw sequential number for a given (real, saved) order ID.
 *
 * Numbers are derived from the order's real auto-increment ID minus an
 * offset, so they can only ever be assigned once per actual saved order —
 * there is no separate counter that can drift out of sync with real orders.
 *
 * @since 2.1
 * @param int $order_id The real, saved order ID (wp_pmpro_membership_orders.id).
 * @param bool $persist Whether to persist a newly-established yearly offset.
 *                       Pass false for read-only previews (e.g. settings page)
 *                       so rendering a page never has side effects.
 * @return int
 */
function pmpropdf_get_sequential_number_for_order( $order_id, $persist = true ) {
	$order_id = intval( $order_id );

	$include_year = (bool) get_option( PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR, false );
	$reset_yearly = (bool) get_option( PMPRO_PDF_SEQUENTIAL_RESET_YEARLY, false );

	if ( $include_year && $reset_yearly ) {
		$year    = date( 'Y' );
		$offsets = get_option( PMPRO_PDF_SEQUENTIAL_YEAR_OFFSETS, array() );
		if ( ! is_array( $offsets ) ) {
			$offsets = array();
		}

		// First order seen in a given year establishes that year's offset,
		// so it becomes number 1 and every later order that year counts up from it.
		if ( ! isset( $offsets[ $year ] ) ) {
			$offsets[ $year ] = $order_id - 1;
			if ( $persist ) {
				update_option( PMPRO_PDF_SEQUENTIAL_YEAR_OFFSETS, $offsets );
			}
		}

		return $order_id - $offsets[ $year ];
	}

	$offset = intval( get_option( PMPRO_PDF_SEQUENTIAL_OFFSET, 0 ) );
	return $order_id - $offset;
}

/**
 * Format a sequential number with prefix, suffix, padding, and year
 *
 * @since 2.1
 * @param int $number The raw sequential number (already offset from the order ID)
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
 * Format the sequential invoice number for a real, saved order ID.
 *
 * @since 2.1
 * @param int $order_id The real, saved order ID (wp_pmpro_membership_orders.id).
 * @return string
 */
function pmpropdf_format_sequential_number_for_order( $order_id ) {
	return pmpropdf_format_sequential_number( pmpropdf_get_sequential_number_for_order( $order_id ) );
}

/**
 * Assign the sequential invoice number to an order right after it is
 * actually inserted into the database.
 *
 * Hooked to `pmpro_added_order`, which PMPro only fires once per real
 * order insert (with $order->id already populated) — unlike
 * `pmpro_random_code`, which fires on every MemberOrder object
 * construction (checkout page previews, email template test renders,
 * etc.), even when no order is ever saved. Assigning here guarantees the
 * invoice number always matches the real, saved order sequence with no
 * gaps or skips.
 *
 * @since 2.1
 * @param MemberOrder $order The order that was just inserted.
 */
function pmpropdf_assign_sequential_invoice_number( $order ) {
	if ( ! pmpropdf_sequential_enabled() || empty( $order->id ) ) {
		return;
	}

	global $wpdb;

	$new_code = pmpropdf_format_sequential_number_for_order( $order->id );

	$order->code = $new_code;

	$wpdb->update(
		$wpdb->pmpro_membership_orders,
		array( 'code' => $new_code ),
		array( 'id' => $order->id ),
		array( '%s' ),
		array( '%d' )
	);

	/**
	 * Fires when a sequential invoice number is assigned
	 *
	 * @since 2.1
	 * @param string $new_code The sequential invoice number
	 * @param MemberOrder $order The order it was assigned to
	 */
	do_action( 'pmpropdf_sequential_number_assigned', $new_code, $order );
}
add_action( 'pmpro_added_order', 'pmpropdf_assign_sequential_invoice_number', 10, 1 );

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
		// Each order's number is derived from its own real ID, so batches
		// can run in any order/size without drifting out of sequence.
		$new_code = pmpropdf_format_sequential_number_for_order( $order->id );

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
