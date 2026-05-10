<?php
/**
 * Sequential Invoice Numbers - Settings UI
 * 
 * @since 2.1
 */

// Prevent direct access
defined( 'ABSPATH' ) or exit;

/**
 * Render the Sequential Invoice Numbers settings section
 * 
 * Call this function inside the Settings tab in pmpro_pdf_invoice_settings_page()
 * 
 * @since 2.1
 */
function pmpropdf_render_sequential_settings() {
	// Get current values
	$enabled = get_option( PMPRO_PDF_SEQUENTIAL_ENABLED, false );
	$prefix = get_option( PMPRO_PDF_SEQUENTIAL_PREFIX, 'INV-' );
	$suffix = get_option( PMPRO_PDF_SEQUENTIAL_SUFFIX, '' );
	$next_number = get_option( PMPRO_PDF_SEQUENTIAL_NEXT, 1 );
	$padding = get_option( PMPRO_PDF_SEQUENTIAL_PADDING, 4 );
	$include_year = get_option( PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR, false );
	$year_placement = get_option( 'pmpro_pdf_sequential_year_placement', 'after_prefix' );
	$replace_invoice_code = get_option( 'pmpro_pdf_sequential_replace_invoice_code', false );
	?>
	
	<div class="postbox pmpropdf-section">
		<h2 class="hndle"><?php esc_html_e( 'Sequential Invoice Numbers', 'pmpro-pdf-invoices' ); ?></h2>
		<div class="inside">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Sequential Numbers', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
							       id="pmpro_pdf_sequential_enabled" 
							       name="pmpro_pdf_sequential_enabled" 
							       value="1" 
							       <?php checked( ! empty( $enabled ) ); ?>>
							<?php esc_html_e( 'Use sequential invoice numbers instead of random order codes', 'pmpro-pdf-invoices' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, invoices will display formatted sequential numbers like INV-0001, INV-0002, etc. The original order code is preserved in PMPro.', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Prefix', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<input type="text" 
						       name="pmpro_pdf_sequential_prefix" 
						       value="<?php echo esc_attr( $prefix ); ?>" 
						       class="regular-text" 
						       placeholder="INV-">
						<p class="description">
							<?php esc_html_e( 'Text to appear before the number. Example: "INV-" produces INV-0001', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Suffix', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<input type="text" 
						       name="pmpro_pdf_sequential_suffix" 
						       value="<?php echo esc_attr( $suffix ); ?>" 
						       class="regular-text" 
						       placeholder="">
						<p class="description">
							<?php esc_html_e( 'Text to appear after the number (optional). Example: "-UK" produces INV-0001-UK', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Number Padding', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<input type="number" 
						       name="pmpro_pdf_sequential_padding" 
						       value="<?php echo esc_attr( $padding ); ?>" 
						       min="1" 
						       max="10" 
						       class="small-text">
						<span class="description">
							<?php esc_html_e( 'Total digits. 4 = 0001, 5 = 00001, etc.', 'pmpro-pdf-invoices' ); ?>
						</span>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Include Year', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
							       id="pmpro_pdf_sequential_include_year"
							       name="pmpro_pdf_sequential_include_year" 
							       value="1" 
							       <?php checked( ! empty( $include_year ) ); ?>>
							<?php esc_html_e( 'Include the current year in invoice numbers', 'pmpro-pdf-invoices' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Useful for annual sequences or compliance requirements.', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options pmpropdf-year-option" <?php echo empty( $enabled ) || empty( $include_year ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Year Placement', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<select name="pmpro_pdf_sequential_year_placement" id="pmpro_pdf_sequential_year_placement">
							<option value="after_prefix" <?php selected( $year_placement, 'after_prefix' ); ?>>
								<?php esc_html_e( 'After prefix (INV-2026-0001)', 'pmpro-pdf-invoices' ); ?>
							</option>
							<option value="before_suffix" <?php selected( $year_placement, 'before_suffix' ); ?>>
								<?php esc_html_e( 'Before suffix (INV-0001-2026)', 'pmpro-pdf-invoices' ); ?>
							</option>
						</select>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Next Invoice Number', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<input type="number" 
						       name="pmpro_pdf_sequential_next" 
						       id="pmpro_pdf_sequential_next"
						       value="<?php echo esc_attr( $next_number ); ?>" 
						       min="1" 
						       class="small-text">
						<p class="description">
							<?php esc_html_e( 'The next invoice will use this number. Change with caution — gaps may occur if you skip numbers.', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
				
				<tr class="pmpropdf-sequential-options" <?php echo empty( $enabled ) ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Replace Order Code', 'pmpro-pdf-invoices' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
							       name="pmpro_pdf_sequential_replace_invoice_code" 
							       value="1" 
							       <?php checked( ! empty( $replace_invoice_code ) ); ?>>
							<?php esc_html_e( 'Use sequential number for {{invoice_code}} variable', 'pmpro-pdf-invoices' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, {{invoice_code}} will display the sequential number instead of the PMPro order code. {{sequential_invoice_number}} is always available.', 'pmpro-pdf-invoices' ); ?>
						</p>
					</td>
				</tr>
			</table>
			
			<?php if ( ! empty( $enabled ) ) : ?>
			<hr style="margin: 15px 0;">
			<p>
				<strong><?php esc_html_e( 'Preview:', 'pmpro-pdf-invoices' ); ?></strong>
				<code id="pmpropdf-sequential-preview" style="font-size: 14px; background: #f0f0f1; padding: 4px 8px; border-radius: 3px; margin-left: 8px;">
					<?php echo esc_html( pmpropdf_format_sequential_number( $next_number ) ); ?>
				</code>
			</p>
			<p class="description" style="margin-top: 8px;">
				<strong><?php esc_html_e( 'Template Variable:', 'pmpro-pdf-invoices' ); ?></strong><br>
				<code>{{sequential_invoice_number}}</code> — <?php esc_html_e( 'Displays the sequential invoice number in your PDF template.', 'pmpro-pdf-invoices' ); ?>
			</p>
			<?php endif; ?>
		</div>
	</div>
	
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		function toggleSequentialOptions() {
			var enabled = $('#pmpro_pdf_sequential_enabled').is(':checked');
			$('.pmpropdf-sequential-options').toggle(enabled);
			toggleYearOption();
			updatePreview();
		}
		
		function toggleYearOption() {
			var yearEnabled = $('#pmpro_pdf_sequential_include_year').is(':checked');
			var seqEnabled = $('#pmpro_pdf_sequential_enabled').is(':checked');
			$('.pmpropdf-year-option').toggle(seqEnabled && yearEnabled);
		}
		
		function updatePreview() {
			var enabled = $('#pmpro_pdf_sequential_enabled').is(':checked');
			if (!enabled) return;
			
			var prefix = $('input[name="pmpro_pdf_sequential_prefix"]').val() || 'INV-';
			var next = $('input[name="pmpro_pdf_sequential_next"]').val() || '1';
			var padding = $('input[name="pmpro_pdf_sequential_padding"]').val() || '4';
			var suffix = $('input[name="pmpro_pdf_sequential_suffix"]').val() || '';
			var includeYear = $('#pmpro_pdf_sequential_include_year').is(':checked');
			var yearPlacement = $('select[name="pmpro_pdf_sequential_year_placement"]').val();
			
			var num = String(next).padStart(parseInt(padding), '0');
			var year = new Date().getFullYear();
			
			var preview = prefix;
			if (includeYear && yearPlacement === 'after_prefix') {
				preview += year + '-';
			}
			preview += num;
			if (includeYear && yearPlacement === 'before_suffix') {
				preview += '-' + year;
			}
			preview += suffix;
			
			$('#pmpropdf-sequential-preview').text(preview);
		}
		
		// Event handlers
		$('#pmpro_pdf_sequential_enabled').on('change', toggleSequentialOptions);
		$('#pmpro_pdf_sequential_include_year').on('change', toggleYearOption);
		
		$('input[name="pmpro_pdf_sequential_prefix"], ' +
		  'input[name="pmpro_pdf_sequential_suffix"], ' +
		  'input[name="pmpro_pdf_sequential_next"], ' +
		  'input[name="pmpro_pdf_sequential_padding"], ' +
		  'select[name="pmpro_pdf_sequential_year_placement"]')
			.on('input change', updatePreview);
		
		// Initialize
		toggleSequentialOptions();
	});
	</script>
	<?php
}

/**
 * Save sequential invoice settings
 * 
 * Call this function in the settings save handler in general-settings.php
 * 
 * @since 2.1
 */
function pmpropdf_save_sequential_settings() {
	// Enable/disable
	$enabled = ! empty( $_POST['pmpro_pdf_sequential_enabled'] );
	update_option( PMPRO_PDF_SEQUENTIAL_ENABLED, $enabled );
	
	if ( $enabled ) {
		// Prefix
		$prefix = ! empty( $_POST['pmpro_pdf_sequential_prefix'] ) 
			? sanitize_text_field( $_POST['pmpro_pdf_sequential_prefix'] ) 
			: 'INV-';
		update_option( PMPRO_PDF_SEQUENTIAL_PREFIX, $prefix );
		
		// Suffix
		$suffix = ! empty( $_POST['pmpro_pdf_sequential_suffix'] ) 
			? sanitize_text_field( $_POST['pmpro_pdf_sequential_suffix'] ) 
			: '';
		update_option( PMPRO_PDF_SEQUENTIAL_SUFFIX, $suffix );
		
		// Padding (1-10)
		$padding = ! empty( $_POST['pmpro_pdf_sequential_padding'] ) 
			? intval( $_POST['pmpro_pdf_sequential_padding'] ) 
			: 4;
		$padding = max( 1, min( 10, $padding ) );
		update_option( PMPRO_PDF_SEQUENTIAL_PADDING, $padding );
		
		// Include year
		$include_year = ! empty( $_POST['pmpro_pdf_sequential_include_year'] );
		update_option( PMPRO_PDF_SEQUENTIAL_INCLUDE_YEAR, $include_year );
		
		// Year placement
		$year_placement = ! empty( $_POST['pmpro_pdf_sequential_year_placement'] ) 
			? sanitize_key( $_POST['pmpro_pdf_sequential_year_placement'] ) 
			: 'after_prefix';
		update_option( 'pmpro_pdf_sequential_year_placement', $year_placement );
		
		// Next number (must be >= 1)
		$next_number = ! empty( $_POST['pmpro_pdf_sequential_next'] ) 
			? intval( $_POST['pmpro_pdf_sequential_next'] ) 
			: 1;
		$next_number = max( 1, $next_number );
		update_option( PMPRO_PDF_SEQUENTIAL_NEXT, $next_number );
		
		// Replace invoice_code option
		$replace_invoice_code = ! empty( $_POST['pmpro_pdf_sequential_replace_invoice_code'] );
		update_option( 'pmpro_pdf_sequential_replace_invoice_code', $replace_invoice_code );
	}
	
	return true;
}
