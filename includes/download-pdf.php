<?php

    // Bail if the user isn't logged-in at all.
    if ( ! is_user_logged_in() ) {
        die( __( 'Whoops! You need to be logged-in to get this data.', 'pmpro-pdf-invoices' ) );
    }

    // Get the order number.
    $order_code = esc_attr( $_GET['pmpropdf'] );

    $order_data = pmpropdf_get_order_by_code( $order_code );
    // See if order exists and user has the right permissions.
    if ( ! empty( $order_data ) ) {
        global $current_user;

        if ( $current_user->ID === intval( $order_data[0]->user_id ) || current_user_can( 'pmpro_orders' ) ) {
            // Generate the PDF on-demand if it doesn't exist yet.
            $invoice_path = pmpropdf_get_invoice_directory_or_url() . pmpropdf_generate_invoice_name( $order_code );
            if ( ! file_exists( $invoice_path ) ) {
                pmpropdf_generate_pdf( $order_data[0] );
            }
            pmpropdf_download_invoice( $order_code );
        }
    } else {
        die( __( 'Invoice does not exist.', 'pmpro-pdf-invoices' ) );
    }