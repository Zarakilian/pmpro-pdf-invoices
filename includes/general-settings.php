<?php

/**
 * Settings page for PMPro PDF Invoices.
 */

/**
 * Helper to get the settings page base URL.
 */
function pmpropdf_settings_url( $args = array() ) {
	$args = array_merge( array( 'page' => 'pmpro_pdf_invoices_license_key' ), $args );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function pmpro_pdf_invoice_settings_page() {

	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'template_editor' ) {
		pmpro_pdf_template_editor_page();
		return false;
	}

	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'reset_template' ) {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ) {
			$template_dir = $upload_dir['basedir'] . '/pmpro-invoice-templates/order.html';
			if ( file_exists( $template_dir ) ) {
				unlink( $template_dir );
				pmpro_pdf_admin_notice( __( 'Template file reset.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
			}
		}
	}

	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'regen_rewrites' && ! pmpropdf_has_pmpro_restricted_directory() ) {
		pmpropdf_remove_rewrite_for_regen();
		pmpro_pdf_admin_notice( __( 'Regenerated rewrite file.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
	}

	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'delete_all_pdfs' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'pmpro-pdf-invoices' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pmpropdf_delete_all_pdfs' ) ) {
			wp_die( __( 'Security check failed.', 'pmpro-pdf-invoices' ) );
		}

		$invoice_dir = pmpropdf_get_invoice_directory_or_url();
		$deleted     = 0;
		foreach ( glob( $invoice_dir . '*.pdf' ) as $file ) {
			if ( is_file( $file ) && unlink( $file ) ) {
				$deleted++;
			}
		}
		pmpro_pdf_admin_notice(
			sprintf( _n( 'Deleted %d PDF invoice.', 'Deleted %d PDF invoices.', $deleted, 'pmpro-pdf-invoices' ), $deleted ),
			'success is-dismissible'
		);
	}

	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'set_template' ) {
		$template_selected = ! empty( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : false;
		if ( ! empty( $template_selected ) ) {
			try {
				$template_body = file_get_contents( PMPRO_PDF_DIR . '/templates/' . $template_selected . '.html' );
				$upload_dir    = wp_upload_dir();
				$template_dir  = $upload_dir['basedir'] . '/pmpro-invoice-templates/';

				if ( ! file_exists( $template_dir ) ) {
					mkdir( $template_dir, 0777, true );
				}

				$custom_dir = $template_dir . 'order.html';
				file_put_contents( $custom_dir, pmpro_pdf_temlate_editor_get_forced_css() . $template_body );
				pmpro_pdf_admin_notice( __( 'Template saved.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
			} catch ( Exception $ex ) {
				pmpro_pdf_admin_notice( __( 'Could not save template.', 'pmpro-pdf-invoices' ), 'error is-dismissible' );
			}
		}
	}

	wp_enqueue_style( 'pmpropdf-settings-styles', plugin_dir_url( __FILE__ ) . 'css/settings-styles.css', array(), PMPRO_PDF_VERSION );
	wp_enqueue_media();
	wp_enqueue_script( 'pmpropdf-settings-scripts', plugin_dir_url( __FILE__ ) . 'js/settings-scripts.js', array( 'jquery' ), PMPRO_PDF_VERSION );
	wp_localize_script( 'pmpropdf-settings-scripts', 'pmpropdf_js', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
	) );

	// Load license data.
	$license = get_option( 'pmpro_pdf_invoice_license_key' );
	$status  = get_option( 'pmpro_pdf_invoice_license_status' );
	$expires = get_option( 'pmpro_pdf_invoice_license_expires' );
	$expired = false;

	if ( ! empty( $expires ) ) {
		$expired = pmpro_pdf_license_expires( $expires );
		if ( $expired ) {
			$expires = __( 'Your license key has expired.', 'pmpro-pdf-invoices' );
		}
	}

	// -------------------------------------------------------------------------
	// Process: Save license key and activate in a single step.
	// -------------------------------------------------------------------------
	if ( isset( $_REQUEST['submit'] ) ) {
		if ( ! check_admin_referer( 'pmpro_pdf_license_nonce', 'pmpro_pdf_license_nonce' ) ) {
			return;
		}

		if ( ! empty( $_REQUEST['pmpro_pdf_invoice_license_key'] ) ) {
			$license = sanitize_text_field( $_REQUEST['pmpro_pdf_invoice_license_key'] );
			update_option( 'pmpro_pdf_invoice_license_key', $license );
		} else {
			delete_option( 'pmpro_pdf_invoice_license_key' );
			delete_option( 'pmpro_pdf_invoice_license_status' );
			delete_option( 'pmpro_pdf_invoice_license_expires' );
			delete_transient( 'pmpro_pdf_invoice_license_valid' );
			$license = '';
			$status  = false;
			pmpro_pdf_admin_notice( __( 'License key removed.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
		}

		// Attempt activation immediately after saving.
		if ( ! empty( $license ) ) {
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_id'    => PMPRO_PDF_PLUGIN_ID,
				'url'        => home_url(),
			);
			$response = wp_remote_post( YOOHOO_STORE, array( 'timeout' => 15, 'sslverify' => true, 'body' => $api_params ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$message = ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An error occurred, please try again.', 'pmpro-pdf-invoices' );
				pmpro_pdf_admin_notice( $message, 'error is-dismissible' );
			} else {
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
				$status       = sanitize_text_field( $license_data->license );
				$expires      = ! empty( $license_data->expires ) ? sanitize_text_field( $license_data->expires ) : '';

				update_option( 'pmpro_pdf_invoice_license_status', $status );
				update_option( 'pmpro_pdf_invoice_license_expires', $expires );

				if ( ! empty( $license_data->success ) ) {
					pmpro_pdf_admin_notice( __( 'License key saved and activated successfully.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
				} else {
					pmpro_pdf_admin_notice( __( 'License key saved, but activation failed. Please ensure your key is valid and not already in use on another site.', 'pmpro-pdf-invoices' ), 'warning is-dismissible' );
				}
			}

			delete_transient( 'pmpro_pdf_invoice_license_valid' );
		}
	}

	// -------------------------------------------------------------------------
	// Process: Deactivate license.
	// -------------------------------------------------------------------------
	if ( isset( $_POST['deactivate_license'] ) ) {
		if ( ! check_admin_referer( 'pmpro_pdf_license_nonce', 'pmpro_pdf_license_nonce' ) ) {
			return;
		}

		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_id'    => PMPRO_PDF_PLUGIN_ID,
			'url'        => home_url(),
		);
		$response = wp_remote_post( YOOHOO_STORE, array( 'body' => $api_params, 'timeout' => 15, 'sslverify' => true ) );

		if ( ! is_wp_error( $response ) ) {
			delete_option( 'pmpro_pdf_invoice_license_status' );
			delete_option( 'pmpro_pdf_invoice_license_expires' );
			$status = false;
			pmpro_pdf_admin_notice( __( 'License deactivated successfully.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
		}
		delete_transient( 'pmpro_pdf_invoice_license_valid' );
	}

	// -------------------------------------------------------------------------
	// Process: Save general settings.
	// -------------------------------------------------------------------------
	if ( isset( $_POST['pmpropdf_save_settings'] ) ) {
		$logo_url = ! empty( $_POST['logo_url'] ) ? strip_tags( $_POST['logo_url'] ) : '';
		update_option( PMPRO_PDF_LOGO_URL, $logo_url );
		update_option( PMPRO_PDF_ADMIN_EMAILS, ( ! empty( $_POST['admin_emails'] ) ? true : false ) );
		pmpro_pdf_admin_notice( __( 'Settings saved.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
	}

	// -------------------------------------------------------------------------
	// Process: Insert shortcode into account page.
	// -------------------------------------------------------------------------
	if ( isset( $_GET['sub_action'] ) && $_GET['sub_action'] === 'insert_account_shortcode' ) {
		if ( function_exists( 'pmpro_getOption' ) ) {
			$account_page_id = pmpro_getOption( 'account_page_id' );
			if ( $account_page_id !== null ) {
				$current_post = get_post( intval( $account_page_id ) );
				wp_update_post( array(
					'ID'           => intval( $account_page_id ),
					'post_content' => $current_post->post_content . "\n\n[pmpropdf_download_list]\n[pmpropdf_download_all_zip]",
				) );
				pmpro_pdf_admin_notice( __( 'Shortcodes added to the Account Page.', 'pmpro-pdf-invoices' ), 'success is-dismissible' );
			}
		}
	}

	// Current values.
	$logo_url     = get_option( PMPRO_PDF_LOGO_URL, '' );
	$admin_emails = get_option( PMPRO_PDF_ADMIN_EMAILS, false );

	// Determine active tab.
	$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'tools';
	$page_url    = pmpropdf_settings_url();

	// License tab badge.
	$license_badge      = '';
	$license_badge_type = '';
	if ( false !== $status && $status === 'valid' ) {
		if ( $expired ) {
			$license_badge      = true;
			$license_badge_type = 'expired';
		}
	} else {
		$license_badge      = true;
		$license_badge_type = 'unregistered';
	}

	// Check for custom template.
	$upload_dir    = wp_upload_dir();
	$custom_tpl    = ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] . '/pmpro-invoice-templates/order.html' : false;
	$has_custom_tpl = $custom_tpl && file_exists( $custom_tpl );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PMPro PDF Invoices', 'pmpro-pdf-invoices' ); ?></h1>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu', 'pmpro-pdf-invoices' ); ?>">
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'tools', $page_url ) ); ?>"
			   class="nav-tab <?php echo $current_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Tools', 'pmpro-pdf-invoices' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $page_url ) ); ?>"
			   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'pmpro-pdf-invoices' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'shortcodes', $page_url ) ); ?>"
			   class="nav-tab <?php echo $current_tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Shortcodes', 'pmpro-pdf-invoices' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'info', $page_url ) ); ?>"
			   class="nav-tab <?php echo $current_tab === 'info' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'System Info', 'pmpro-pdf-invoices' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'license', $page_url ) ); ?>"
			   class="nav-tab <?php echo $current_tab === 'license' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'License', 'pmpro-pdf-invoices' ); ?>
				<?php if ( $license_badge ) : ?>
					<span class="pmpropdf-license-dot pmpropdf-license-dot--<?php echo esc_attr( $license_badge_type ); ?>"></span>
				<?php endif; ?>
			</a>
		</nav>

		<?php /* ================================================================
		       TAB: Tools
		       ================================================================ */ ?>
		<?php if ( $current_tab === 'tools' ) : ?>

		<div class="pmpropdf-tab-content">

			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'Invoice Template', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<p>
						<?php if ( $has_custom_tpl ) : ?>
							<?php esc_html_e( 'You are using a custom template.', 'pmpro-pdf-invoices' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'No custom template is set. The default template will be used.', 'pmpro-pdf-invoices' ); ?>
						<?php endif; ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( pmpropdf_settings_url( array( 'sub_action' => 'template_editor' ) ) ); ?>">
							<?php echo $has_custom_tpl ? esc_html__( 'Edit Template', 'pmpro-pdf-invoices' ) : esc_html__( 'Create Template', 'pmpro-pdf-invoices' ); ?>
						</a>

						<?php if ( $has_custom_tpl ) : ?>
							<a class="button reset_template_btn" href="<?php echo esc_url( pmpropdf_settings_url( array( 'sub_action' => 'reset_template' ) ) ); ?>">
								<?php esc_html_e( 'Reset to Default', 'pmpro-pdf-invoices' ); ?>
							</a>
						<?php endif; ?>

						<a class="button select_template_btn" href="#">
							<?php esc_html_e( 'Select Built-in Template', 'pmpro-pdf-invoices' ); ?>
						</a>

						<?php $pdf_sample_nonce = wp_create_nonce( 'pmpropdf_view_sample' ); ?>
						<a class="button" href="<?php echo esc_url( pmpropdf_settings_url( array( 'sub_action' => 'view_sample', '_wpnonce' => $pdf_sample_nonce ) ) ); ?>" target="_blank">
							<?php esc_html_e( 'Download Sample PDF', 'pmpro-pdf-invoices' ); ?>
						</a>
					</p>
					<p class="description">
						<?php esc_html_e( "Tip: Not sure where to start? Click \"Select Built-in Template\" to choose from our included designs.", 'pmpro-pdf-invoices' ); ?>
					</p>
				</div>
			</div>

			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'Generate Invoices', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<div class="missing_invoice_log">
						<div class="item"><?php esc_html_e( 'No output yet...', 'pmpro-pdf-invoices' ); ?></div>
					</div>
					<br>
					<button class="button button-primary generate_missing_logs" data-force="0">
						<?php esc_html_e( 'Generate Missing Invoices', 'pmpro-pdf-invoices' ); ?>
					</button>
					&nbsp;
					<button class="button generate_missing_logs" data-force="1">
						<?php esc_html_e( 'Regenerate All Invoices', 'pmpro-pdf-invoices' ); ?>
					</button>
					<p class="description" style="margin-top: 8px;">
						<?php esc_html_e( '"Generate Missing" skips orders that already have a PDF. "Regenerate All" overwrites every existing PDF. Please leave this window open while processing.', 'pmpro-pdf-invoices' ); ?>
					</p>
				</div>
			</div>

			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'Archives', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<p class="description"><?php esc_html_e( 'Download all stored PDF invoices as a single ZIP file.', 'pmpro-pdf-invoices' ); ?></p>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="pmpro_pdf_invoices_license_key">
						<input type="hidden" name="sub_action" value="download_zip_archive">
						<?php wp_nonce_field( 'pmpropdf_download_zip', 'pmpropdf_download_nonce' ); ?>
						<label for="pmpropdf_date_from"><?php esc_html_e( 'From Date (YYYY-MM-DD)', 'pmpro-pdf-invoices' ); ?></label>
						<input type="date" id="pmpropdf_date_from" name="pmpropdf_date_from" placeholder="YYYY-MM-DD">
						<label for="pmpropdf_date_to"><?php esc_html_e( 'To Date (YYYY-MM-DD)', 'pmpro-pdf-invoices' ); ?></label>
						<input type="date" id="pmpropdf_date_to" name="pmpropdf_date_to" placeholder="YYYY-MM-DD">
						<button type="submit" class="button"><?php esc_html_e( 'Download by Date Range', 'pmpro-pdf-invoices' ); ?></button>
					</form>
					<p>
						<a class="button download_zip_btn" href="<?php echo esc_url( wp_nonce_url( pmpropdf_settings_url( array( 'sub_action' => 'download_zip_archive' ) ), 'pmpropdf_download_zip', 'pmpropdf_download_nonce' ) ); ?>">
							<?php esc_html_e( 'Download All as ZIP', 'pmpro-pdf-invoices' ); ?>
						</a>
					</p>					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<hr>
						<p class="description"><?php esc_html_e( 'Permanently delete all stored PDF invoice files from the server. This cannot be undone. PDFs can be regenerated using the tool above.', 'pmpro-pdf-invoices' ); ?></p>
						<p>
							<a class="button pmpropdf-delete-all-btn"
							   href="<?php echo esc_url( wp_nonce_url( pmpropdf_settings_url( array( 'sub_action' => 'delete_all_pdfs' ) ), 'pmpropdf_delete_all_pdfs' ) ); ?>">
								<?php esc_html_e( 'Delete All PDF Invoices', 'pmpro-pdf-invoices' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! pmpropdf_has_pmpro_restricted_directory() && ! empty( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) :
				$_upload_dir = wp_upload_dir();
				$_baseurl    = str_replace( site_url(), '', $_upload_dir['baseurl'] );
				$_invoice_dir = $_baseurl . '/pmpro-invoices/';
				$_access_key  = pmpropdf_get_rewrite_token();
			?>
			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'Nginx Configuration', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Your server is running Nginx. To protect stored invoices, add the following rule to your Nginx WordPress config file.', 'pmpro-pdf-invoices' ); ?></p>
					<pre class="pmpropdf-code-block">location <?php echo esc_html( $_invoice_dir ); ?> {
    if ($query_string !~ "access=<?php echo esc_html( $_access_key ); ?>") {
        return 403;
    }
}</pre>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- .pmpropdf-tab-content -->

		<?php /* ================================================================
		       TAB: Settings
		       ================================================================ */ ?>
		<?php elseif ( $current_tab === 'settings' ) : ?>

		<div class="pmpropdf-tab-content">
			<form method="POST" action="">
				<?php wp_nonce_field( 'pmpropdf_save_settings', 'pmpropdf_settings_nonce' ); ?>

				<div class="postbox pmpropdf-section">
					<h2 class="hndle"><?php esc_html_e( 'Invoice Logo', 'pmpro-pdf-invoices' ); ?></h2>
					<div class="inside">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Logo Image', 'pmpro-pdf-invoices' ); ?></th>
								<td>
									<div class="logo_holder">
										<?php if ( ! empty( $logo_url ) ) : ?>
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:200px; display:block; margin-bottom:8px;" />
										<?php else : ?>
											<em><?php esc_html_e( 'No logo selected.', 'pmpro-pdf-invoices' ); ?></em>
										<?php endif; ?>
									</div>
									<input id="logo_url" name="logo_url" type="hidden" value="<?php echo esc_attr( $logo_url ); ?>" />
									<button type="button" class="button pmpropdf_logo_upload"><?php esc_html_e( 'Select Image', 'pmpro-pdf-invoices' ); ?></button>
									<?php if ( ! empty( $logo_url ) ) : ?>
										<button type="button" class="button pmpropdf_logo_remove"><?php esc_html_e( 'Remove', 'pmpro-pdf-invoices' ); ?></button>
									<?php endif; ?>
									<p class="description"><?php esc_html_e( 'This logo will appear in your invoice PDFs via the {{logo_image}} template variable.', 'pmpro-pdf-invoices' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="postbox pmpropdf-section">
					<h2 class="hndle"><?php esc_html_e( 'Email Settings', 'pmpro-pdf-invoices' ); ?></h2>
					<div class="inside">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Admin Emails', 'pmpro-pdf-invoices' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="admin_emails" value="1" <?php checked( ! empty( $admin_emails ) ); ?>>
										<?php esc_html_e( "Attach PDF invoices to admin checkout notification emails.", 'pmpro-pdf-invoices' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'By default, PDF invoices are only attached to member-facing emails. Enable this to also attach them to admin copies.', 'pmpro-pdf-invoices' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'pmpro-pdf-invoices' ), 'primary', 'pmpropdf_save_settings' ); ?>
			</form>
		</div><!-- .pmpropdf-tab-content -->

		<?php /* ================================================================
		       TAB: Shortcodes
		       ================================================================ */ ?>
		<?php elseif ( $current_tab === 'shortcodes' ) : ?>

		<div class="pmpropdf-tab-content">
			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'Available Shortcodes', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><code>[pmpropdf_download_list]</code></th>
							<td>
								<p><?php esc_html_e( 'Displays a table of the current user\'s PDF invoices with download links.', 'pmpro-pdf-invoices' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><code>[pmpropdf_download_all_zip]</code></th>
							<td>
								<p><?php esc_html_e( 'Displays a link allowing the current user to download all their PDF invoices as a ZIP archive.', 'pmpro-pdf-invoices' ); ?></p>
								<?php if ( ! class_exists( 'ZipArchive' ) ) : ?>
									<p class="pmpropdf-notice pmpropdf-notice--warning">
										<?php esc_html_e( 'The ZipArchive PHP module is not available on your server. This shortcode will not function until it is enabled.', 'pmpro-pdf-invoices' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<?php if ( function_exists( 'pmpro_getOption' ) ) :
						$account_page_id = pmpro_getOption( 'account_page_id' );
						if ( $account_page_id !== null ) : ?>
						<p>
							<a class="button" href="<?php echo esc_url( pmpropdf_settings_url( array( 'sub_action' => 'insert_account_shortcode' ) ) ); ?>">
								<?php esc_html_e( 'Add Shortcodes to Account Page', 'pmpro-pdf-invoices' ); ?>
							</a>
						</p>
						<p class="description"><?php esc_html_e( 'Automatically append both shortcodes to your PMPro Account Page.', 'pmpro-pdf-invoices' ); ?></p>
					<?php endif; endif; ?>
				</div>
			</div>
		</div><!-- .pmpropdf-tab-content -->

		<?php /* ================================================================
		       TAB: System Info
		       ================================================================ */ ?>
		<?php elseif ( $current_tab === 'info' ) : ?>

		<div class="pmpropdf-tab-content">
			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'System Status', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'ZipArchive Module', 'pmpro-pdf-invoices' ); ?></th>
							<td>
								<?php if ( class_exists( 'ZipArchive' ) ) : ?>
									<span class="pmpropdf-status-badge pmpropdf-status-badge--active"><?php esc_html_e( 'Available', 'pmpro-pdf-invoices' ); ?></span>
									<span class="description"><?php esc_html_e( 'ZIP archive downloads are enabled.', 'pmpro-pdf-invoices' ); ?></span>
								<?php else : ?>
									<span class="pmpropdf-status-badge pmpropdf-status-badge--inactive"><?php esc_html_e( 'Unavailable', 'pmpro-pdf-invoices' ); ?></span>
									<span class="description"><?php esc_html_e( 'ZIP functionality is disabled. Enable the ZipArchive module on your server to use it.', 'pmpro-pdf-invoices' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Invoice File Protection', 'pmpro-pdf-invoices' ); ?></th>
							<td>
								<?php if ( pmpropdf_has_pmpro_restricted_directory() ) : ?>
									<span class="pmpropdf-status-badge pmpropdf-status-badge--active"><?php esc_html_e( 'Active (PMPro)', 'pmpro-pdf-invoices' ); ?></span>
									<span class="description"><?php esc_html_e( 'Invoice files are stored in PMPro\'s restricted content directory.', 'pmpro-pdf-invoices' ); ?></span>
								<?php elseif ( pmpropdf_check_rewrite_active() ) : ?>
									<span class="pmpropdf-status-badge pmpropdf-status-badge--active"><?php esc_html_e( 'Active', 'pmpro-pdf-invoices' ); ?></span>
									<span class="description"><?php esc_html_e( 'Invoice files are protected and cannot be accessed directly.', 'pmpro-pdf-invoices' ); ?></span>
								<?php else : ?>
									<span class="pmpropdf-status-badge pmpropdf-status-badge--inactive"><?php esc_html_e( 'Inactive', 'pmpro-pdf-invoices' ); ?></span>
									<span class="description"><?php esc_html_e( 'Invoice files are not protected and may be accessed directly.', 'pmpro-pdf-invoices' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Plugin Version', 'pmpro-pdf-invoices' ); ?></th>
							<td><?php echo esc_html( PMPRO_PDF_VERSION ); ?></td>
						</tr>
					</table>

					<?php if ( ! pmpropdf_has_pmpro_restricted_directory() ) : ?>
					<hr>
					<p>
						<strong><?php esc_html_e( 'Regenerate Rewrite File', 'pmpro-pdf-invoices' ); ?></strong><br>
						<span class="description"><?php esc_html_e( 'Use this if invoices are not downloading correctly. This will regenerate the .htaccess protection file.', 'pmpro-pdf-invoices' ); ?></span>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( pmpropdf_settings_url( array( 'sub_action' => 'regen_rewrites' ) ) ); ?>">
							<?php esc_html_e( 'Regenerate Rewrite File', 'pmpro-pdf-invoices' ); ?>
						</a>
					</p>

					<hr>
					<p>
						<strong><?php esc_html_e( 'PDF Invoice Coverage', 'pmpro-pdf-invoices' ); ?></strong><br>
						<?php
						$coverage = pmpropdf_get_pdf_coverage_stats();
						if ( $coverage ) {
							printf(
								/* translators: 1: number of orders with PDFs, 2: total number of orders */
								esc_html__( '%1$d of %2$d orders have PDF invoices generated.', 'pmpro-pdf-invoices' ),
								$coverage['with_pdf'],
								$coverage['total']
							);
							if ( $coverage['total'] > 0 ) {
								$percentage = round( ( $coverage['with_pdf'] / $coverage['total'] ) * 100, 1 );
								echo ' <strong>(' . esc_html( $percentage ) . '%)</strong>';
							}
						} else {
							esc_html_e( 'Unable to calculate coverage stats.', 'pmpro-pdf-invoices' );
						}
						?>
					</p>
						<?php endif; ?>
				</div>
			</div>
		</div><!-- .pmpropdf-tab-content -->

		<?php /* ================================================================
		       TAB: License
		       ================================================================ */ ?>
		<?php elseif ( $current_tab === 'license' ) : ?>

		<div class="pmpropdf-tab-content">

			<?php if ( $expired ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo wp_kses( __( 'Your license key has expired. <a href="https://yoohooplugins.com/plugins/paid-memberships-pro-pdf-invoices/" target="_blank" rel="noopener">Renew your license</a> to continue receiving automatic updates and priority support.', 'pmpro-pdf-invoices' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></p>
				</div>
			<?php elseif ( empty( $license ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php echo wp_kses( __( 'Enter your license key below to enable automatic updates and support. <a href="https://yoohooplugins.com/plugins/paid-memberships-pro-pdf-invoices/" target="_blank" rel="noopener">Purchase a license</a> if you don\'t have one yet.', 'pmpro-pdf-invoices' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="postbox pmpropdf-section">
				<h2 class="hndle"><?php esc_html_e( 'License Key', 'pmpro-pdf-invoices' ); ?></h2>
				<div class="inside">
					<form method="post" action="">
						<?php wp_nonce_field( 'pmpro_pdf_license_nonce', 'pmpro_pdf_license_nonce' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="pmpro_pdf_invoice_license_key"><?php esc_html_e( 'License Key', 'pmpro-pdf-invoices' ); ?></label>
								</th>
								<td>
									<input id="pmpro_pdf_invoice_license_key"
									       name="pmpro_pdf_invoice_license_key"
									       type="text"
									       class="regular-text"
									       value="<?php echo esc_attr( $license ); ?>"
									       placeholder="<?php esc_attr_e( 'Paste your license key here', 'pmpro-pdf-invoices' ); ?>" />
									<p class="description"><?php esc_html_e( 'Your license key can be found in your Yoohoo Plugins account.', 'pmpro-pdf-invoices' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'License Status', 'pmpro-pdf-invoices' ); ?></th>
								<td>
									<?php if ( false !== $status && $status === 'valid' ) : ?>
										<?php if ( ! $expired ) : ?>
											<span class="pmpropdf-status-badge pmpropdf-status-badge--active"><?php esc_html_e( 'Active', 'pmpro-pdf-invoices' ); ?></span>
											<?php if ( ! empty( $expires ) && $expires !== 'lifetime' ) : ?>
												<span class="description"><?php printf( esc_html__( 'Expires: %s', 'pmpro-pdf-invoices' ), esc_html( $expires ) ); ?></span>
											<?php else : ?>
												<span class="description"><?php esc_html_e( 'Lifetime license.', 'pmpro-pdf-invoices' ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<span class="pmpropdf-status-badge pmpropdf-status-badge--expired"><?php esc_html_e( 'Expired', 'pmpro-pdf-invoices' ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="pmpropdf-status-badge pmpropdf-status-badge--inactive"><?php esc_html_e( 'Unregistered', 'pmpro-pdf-invoices' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<p class="submit">
							<?php submit_button( __( 'Save License Key', 'pmpro-pdf-invoices' ), 'primary', 'submit', false ); ?>
							<?php if ( false !== $status && $status === 'valid' && ! $expired ) : ?>
								&nbsp;
								<input type="submit" class="button button-link-delete" name="deactivate_license" value="<?php esc_attr_e( 'Deactivate License', 'pmpro-pdf-invoices' ); ?>" />
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div><!-- .pmpropdf-tab-content -->

		<?php endif; ?>
	</div><!-- .wrap -->

	<?php /* ================================================================
	       Template selector modal
	       ================================================================ */ ?>
	<div class="pmpropdf-modal" id="pmpropdf-template-selector" style="display:none;">
		<div class="pmpropdf-modal__backdrop"></div>
		<div class="pmpropdf-modal__dialog">
			<div class="pmpropdf-modal__header">
				<h2><?php esc_html_e( 'Select a Built-in Template', 'pmpro-pdf-invoices' ); ?></h2>
				<button type="button" class="pmpropdf-modal__close button-link" aria-label="<?php esc_attr_e( 'Close', 'pmpro-pdf-invoices' ); ?>">&times;</button>
			</div>
			<div class="pmpropdf-modal__body">
				<p class="description"><?php esc_html_e( 'Click a template to apply it. Note: selecting a built-in template will replace your current custom template.', 'pmpro-pdf-invoices' ); ?></p>
				<div class="pmpropdf-template-grid">
					<?php
					$templates = array(
						'blank'     => __( 'Blank', 'pmpro-pdf-invoices' ),
						'order'     => __( 'Default', 'pmpro-pdf-invoices' ),
						'corporate' => __( 'Corporate', 'pmpro-pdf-invoices' ),
						'green'     => __( 'Green', 'pmpro-pdf-invoices' ),
						'split'     => __( 'Split', 'pmpro-pdf-invoices' ),
					);
					$template_images = array(
						'blank'     => 'blank_template.jpg',
						'order'     => 'default_template.jpg',
						'corporate' => 'corp_template.jpg',
						'green'     => 'green_template.jpg',
						'split'     => 'split_template.jpg',
					);
					foreach ( $templates as $slug => $label ) :
						$img = isset( $template_images[ $slug ] ) ? plugin_dir_url( __FILE__ ) . 'images/' . $template_images[ $slug ] : '';
					?>
					<div class="pmpropdf-template-tile" data-template="<?php echo esc_attr( $slug ); ?>">
						<?php if ( $img ) : ?>
							<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $label ); ?>" />
						<?php endif; ?>
						<span><?php echo esc_html( $label ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>

<?php
}

/**
 * Show an admin notice.
 * @since 1.8
 */
function pmpro_pdf_admin_notice( $message, $status ) {
	?>
	<div class="notice notice-<?php echo esc_attr( $status ); ?>">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>
	<?php
}

/**
 * Check whether a license expiry date has passed.
 */
function pmpro_pdf_license_expires( $expiry_date ) {
	return ( $expiry_date < date( 'Y-m-d H:i:s' ) );
}

/**
 * Handle the sample PDF download request.
 * @since 1.10
 */
function pmpro_pdf_admin_view_sample_pdf() {
	if ( ! empty( $_GET['page'] ) && ! empty( $_GET['sub_action'] ) ) {
		if ( $_GET['page'] === 'pmpro_pdf_invoices_license_key' && $_GET['sub_action'] === 'view_sample' ) {
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'pmpropdf_view_sample' ) ) {
				wp_die( 'Security check failed.' );
			}
			pmpropdf_generate_sample_pdf();
		}
	}
}
add_action( 'admin_init', 'pmpro_pdf_admin_view_sample_pdf' );
