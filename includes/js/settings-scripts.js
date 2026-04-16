jQuery( function ( $ ) {

	/* -------------------------------------------------------------------------
	   Batch state
	------------------------------------------------------------------------- */
	pmpropdf_js.batch_process = {
		total_count:   0,
		total_created: 0,
		total_skipped: 0,
		total_orders:  0
	};

	/* -------------------------------------------------------------------------
	   Generate / regenerate invoices
	------------------------------------------------------------------------- */
	$( '.generate_missing_logs' ).on( 'click', function ( e ) {
		e.preventDefault();

		var force = $( this ).data( 'force' ) === 1 || $( this ).data( 'force' ) === '1';

		if ( force && ! confirm( 'This will delete and regenerate every existing PDF invoice. This cannot be undone.\n\nAre you sure you want to continue?' ) ) {
			return;
		}

		pmpropdf_js.batch_process = { total_count: 0, total_created: 0, total_skipped: 0, total_orders: 0 };

		// Show progress bar immediately.
		$( '#pmpropdf_progress_wrap' ).show();
		$( '#pmpropdf_progress_bar' ).addClass( 'indeterminate' ).prop( 'value', '' );
		$( '#pmpropdf_progress_label' ).text( 'Starting...' );

		$( '.missing_invoice_log' ).html( '<div class="item">' + ( force ? 'Regenerating all invoices&hellip;' : 'Generating missing invoices&hellip;' ) + '</div>' );
		pmpropdf_ajax_batch_loop( 100, 0, force ? '1' : '0' );
	} );

	/* -------------------------------------------------------------------------
	   Logo uploader
	------------------------------------------------------------------------- */
	$( '.pmpropdf_logo_upload' ).on( 'click', function ( e ) {
		e.preventDefault();
		pmpropdf_logo_uploader();
	} );

	$( '.pmpropdf_logo_remove' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '.logo_holder' ).html( '<em>No logo selected.</em>' );
		$( '#logo_url' ).val( '' );
	} );

	/* -------------------------------------------------------------------------
	   Reset template – confirm before navigating
	------------------------------------------------------------------------- */
	$( '.reset_template_btn' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( confirm( 'This cannot be undone. Your current custom template will be deleted.\n\nAre you sure you want to continue?' ) ) {
			window.location = $( this ).attr( 'href' );
		}
	} );

	/* -------------------------------------------------------------------------
	   Delete all PDFs – confirm before navigating
	------------------------------------------------------------------------- */
	$( '.pmpropdf-delete-all-btn' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( confirm( 'This will permanently delete all stored PDF invoice files from the server. This cannot be undone.\n\nAre you sure you want to continue?' ) ) {
			window.location = $( this ).attr( 'href' );
		}
	} );

	/* -------------------------------------------------------------------------
	   Date range preset selector
	------------------------------------------------------------------------- */
	$( '#pmpropdf_date_preset' ).on( 'change', function () {
		var preset  = $( this ).val();
		var now     = new Date();
		var year    = now.getFullYear();
		var month   = now.getMonth();
		var fromStr = '';
		var toStr   = '';

		if ( preset === 'custom' ) {
			$( '#pmpropdf_custom_date_fields' ).show();
			$( '#pmpropdf_date_from' ).val( '' );
			$( '#pmpropdf_date_to' ).val( '' );
			$( '#pmpropdf_date_submit' ).prop( 'disabled', true );
			return;
		}

		$( '#pmpropdf_custom_date_fields' ).hide();
		$( '#pmpropdf_date_from' ).val( '' );
		$( '#pmpropdf_date_to' ).val( '' );

		if ( ! preset ) {
			$( '#pmpropdf_date_submit' ).prop( 'disabled', true );
			return;
		}

		switch ( preset ) {
			case 'this_month':
				fromStr = pmpropdf_format_date( year, month, 1 );
				toStr   = pmpropdf_format_date( year, month + 1, 0 );
				break;
			case 'last_month':
				fromStr = pmpropdf_format_date( year, month - 1, 1 );
				toStr   = pmpropdf_format_date( year, month, 0 );
				break;
			case 'this_quarter':
				var qStart = Math.floor( month / 3 ) * 3;
				fromStr = pmpropdf_format_date( year, qStart, 1 );
				toStr   = pmpropdf_format_date( year, qStart + 3, 0 );
				break;
			case 'this_year':
				fromStr = pmpropdf_format_date( year, 0, 1 );
				toStr   = pmpropdf_format_date( year, 11, 31 );
				break;
		}

		$( '#pmpropdf_date_from' ).val( fromStr );
		$( '#pmpropdf_date_to' ).val( toStr );
		$( '#pmpropdf_date_submit' ).prop( 'disabled', false );
	} );

	$( '#pmpropdf_date_from, #pmpropdf_date_to' ).on( 'change', function () {
		var from = $( '#pmpropdf_date_from' ).val();
		var to   = $( '#pmpropdf_date_to' ).val();
		$( '#pmpropdf_date_submit' ).prop( 'disabled', ! from || ! to );
	} );

	/* -------------------------------------------------------------------------
	   Template selector modal
	------------------------------------------------------------------------- */
	$( '.select_template_btn' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#pmpropdf-template-selector' ).show();
		$( 'body' ).addClass( 'pmpropdf-modal-open' );
	} );

	$( '#pmpropdf-template-selector' ).on( 'click', '.pmpropdf-modal__close, .pmpropdf-modal__backdrop', function () {
		$( '#pmpropdf-template-selector' ).hide();
		$( 'body' ).removeClass( 'pmpropdf-modal-open' );
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			$( '#pmpropdf-template-selector' ).hide();
			$( 'body' ).removeClass( 'pmpropdf-modal-open' );
		}
	} );

	$( '.pmpropdf-template-tile' ).on( 'click', function () {
		var template = $( this ).data( 'template' );
		var url      = new URL( window.location.href );
		url.searchParams.set( 'sub_action', 'set_template' );
		url.searchParams.set( 'template', template );
		window.location = url.toString();
	} );

} );

/* ---------------------------------------------------------------------------
   AJAX batch loop
--------------------------------------------------------------------------- */
function pmpropdf_ajax_batch_loop( batch_size, batch_no, force ) {
	force = force || '0';

	jQuery.ajax( {
		url:  pmpropdf_js.ajax_url,
		type: 'post',
		data: {
			action:     'pmpropdf_batch_processor',
			batch_size: batch_size,
			batch_no:   batch_no,
			force:      force
		},
		success: function ( response ) {
			try {
				response = JSON.parse( response );
			} catch ( e ) {
				jQuery( '.missing_invoice_log' ).html( '<div class="item">Unexpected server response. Check for PHP errors in your debug log.</div>' );
				jQuery( '#pmpropdf_progress_wrap' ).hide();
				return;
			}

			if ( typeof response.error !== 'undefined' ) {
				jQuery( '.missing_invoice_log' ).html( '<div class="item">' + response.error + '</div>' );
				jQuery( '#pmpropdf_progress_wrap' ).hide();
				return;
			}

			// Capture total order count from first batch response.
			if ( response.total_orders ) {
				pmpropdf_js.batch_process.total_orders = response.total_orders;
				// Exit indeterminate state and set initial value.
				jQuery( '#pmpropdf_progress_bar' ).removeClass( 'indeterminate' ).val( 0 );
			}

			pmpropdf_js.batch_process.total_count   += response.batch_count || 0;
			pmpropdf_js.batch_process.total_created += response.created     || 0;
			pmpropdf_js.batch_process.total_skipped += response.skipped     || 0;

			pmpropdf_update_batch_stats();

			if ( typeof response.batch_count !== 'undefined' && response.batch_count >= batch_size ) {
				pmpropdf_ajax_batch_loop( batch_size, response.batch_no + 1, force );
			} else {
				// Set bar to 100% on completion.
				jQuery( '#pmpropdf_progress_bar' ).val( 100 );
				jQuery( '#pmpropdf_progress_label' ).text(
					pmpropdf_js.batch_process.total_orders + ' / ' + pmpropdf_js.batch_process.total_orders + ' orders (100%)'
				);

				var msg;
				if ( force === '1' ) {
					msg = 'Regeneration complete.';
				} else {
					msg = pmpropdf_js.batch_process.total_created === 0
						? 'No missing invoices found.'
						: 'Processing complete.';
				}
				jQuery( '.missing_invoice_log' ).append( '<div class="item">' + msg + '</div>' );
			}
		},
		error: function ( xhr ) {
			jQuery( '.missing_invoice_log' ).html( '<div class="item">AJAX request failed (HTTP ' + xhr.status + '). Check your server error log.</div>' );
			jQuery( '#pmpropdf_progress_wrap' ).hide();
		}
	} );
}

function pmpropdf_update_batch_stats() {
	var total = pmpropdf_js.batch_process.total_orders;
	var count = pmpropdf_js.batch_process.total_count;

	// Update progress bar if we have a total.
	if ( total > 0 ) {
		var pct = Math.min( 100, Math.round( ( count / total ) * 100 ) );
		jQuery( '#pmpropdf_progress_bar' ).val( pct );
		jQuery( '#pmpropdf_progress_label' ).text( count + ' / ' + total + ' orders (' + pct + '%)' );
	}

	jQuery( '.missing_invoice_log' ).html(
		'<div class="item">' +
			'Processed: ' + pmpropdf_js.batch_process.total_count   + '<br>' +
			'Created: '   + pmpropdf_js.batch_process.total_created + '<br>' +
			'Skipped: '   + pmpropdf_js.batch_process.total_skipped +
		'</div>'
	);
}

/* ---------------------------------------------------------------------------
   Date helper — returns YYYY-MM-DD from year, 0-indexed month, day.
--------------------------------------------------------------------------- */
function pmpropdf_format_date( year, month, day ) {
	var d  = new Date( year, month, day );
	var mm = ( '0' + ( d.getMonth() + 1 ) ).slice( -2 );
	var dd = ( '0' + d.getDate() ).slice( -2 );
	return d.getFullYear() + '-' + mm + '-' + dd;
}

/* ---------------------------------------------------------------------------
   Logo uploader (WP media)
--------------------------------------------------------------------------- */
function pmpropdf_logo_uploader() {
	var file_frame;

	if ( typeof file_frame !== 'undefined' ) {
		file_frame.open();
		return;
	}

	file_frame = wp.media.frames.file_frame = wp.media( {
		title:    'Select Invoice Logo',
		button:   { text: 'Use as Logo' },
		multiple: false
	} );

	file_frame.on( 'select', function () {
		var attachment = file_frame.state().get( 'selection' ).first().toJSON();
		jQuery( '.logo_holder' ).html( '<img src="' + attachment.url + '" alt="" style="max-width:200px; display:block; margin-bottom:8px;" />' );
		jQuery( '#logo_url' ).val( attachment.url );
	} );

	file_frame.open();
}
