jQuery( function ( $ ) {

	/* -------------------------------------------------------------------------
	   Batch state
	------------------------------------------------------------------------- */
	pmpropdf_js.batch_process = {
		total_count:   0,
		total_created: 0,
		total_skipped: 0
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

		pmpropdf_js.batch_process = { total_count: 0, total_created: 0, total_skipped: 0 };
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
			response = JSON.parse( response );

			if ( typeof response.error !== 'undefined' ) {
				jQuery( '.missing_invoice_log' ).html( '<div class="item">' + response.error + '</div>' );
				return;
			}

			pmpropdf_js.batch_process.total_count   += response.batch_count || 0;
			pmpropdf_js.batch_process.total_created += response.created     || 0;
			pmpropdf_js.batch_process.total_skipped += response.skipped     || 0;

			pmpropdf_update_batch_stats();

			if ( typeof response.batch_count !== 'undefined' && response.batch_count >= batch_size ) {
				pmpropdf_ajax_batch_loop( batch_size, response.batch_no + 1, force );
			} else {
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
		}
	} );
}

function pmpropdf_update_batch_stats() {
	jQuery( '.missing_invoice_log' ).html(
		'<div class="item">' +
			'Processed: ' + pmpropdf_js.batch_process.total_count   + '<br>' +
			'Created: '   + pmpropdf_js.batch_process.total_created + '<br>' +
			'Skipped: '   + pmpropdf_js.batch_process.total_skipped +
		'</div>'
	);
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
