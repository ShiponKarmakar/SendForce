<?php
/**
 * Admin functionality.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Admin
 *
 * Handles admin menu, pages, and AJAX endpoints.
 */
class SendForce_Admin {

	/**
	 * Allowed tabs.
	 *
	 * @var array
	 */
	private $tabs = array( 'dashboard', 'settings', 'email-log', 'queue', 'email-test', 'alerts', 'routing', 'upcoming', 'changelog', 'setup' );

	/**
	 * Known conflicting SMTP/mail plugins: [ plugin-file => display-name ]
	 *
	 * @var array
	 */
	private $conflicting_plugins = array(
		'wp-mail-smtp/wp_mail_smtp.php'                           => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'                             => 'FluentSMTP',
		'easy-wp-smtp/easy-wp-smtp.php'                           => 'Easy WP SMTP',
		'post-smtp/postman-smtp.php'                              => 'Post SMTP',
		'gmail-smtp/main.php'                                     => 'Gmail SMTP',
		'wp-smtp/wp-smtp.php'                                     => 'WP SMTP',
		'smtp-mailer/main.php'                                    => 'SMTP Mailer',
		'bws-smtp/bws-smtp.php'                                   => 'SMTP by BestWebSoft',
		'configure-smtp/configure-smtp.php'                       => 'Configure SMTP',
		'wp-mail-bank/wp-mail-bank.php'                           => 'WP Mail Bank',
		'sendgrid-email-delivery-simplified/wpsendgrid.php'       => 'SendGrid',
		'mailgun/mailgun.php'                                     => 'Mailgun for WordPress',
		'sparkpost/sparkpost.php'                                 => 'SparkPost',
		'elasticemail-sender/elasticemail-sender.php'             => 'Elastic Email Sender',
		'brevo-woocommerce/brevo-woocommerce.php'                 => 'Brevo (Sendinblue)',
		'wp-ses/wp-ses.php'                                       => 'WP SES',
		'postmark-approved-wordpress-plugin/postmark.php'         => 'Postmark',
	);

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_active_submenu' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_retire_setup_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_conflict_notice_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'show_conflict_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_update_welcome_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_credential_recovery_notice' ) );
		add_action( 'wp_ajax_sendforce_dismiss_update_welcome', array( $this, 'ajax_dismiss_update_welcome' ) );

		// On SendForce's own admin screens, suppress every other plugin's
		// admin notice — they clutter our settings, hurt our UX, and most are
		// unrelated promo/upsell banners. Our own conflict notice + the
		// `settings_errors()` calls we make ourselves still render.
		add_action( 'in_admin_header', array( $this, 'maybe_suppress_other_notices' ), 1000 );
		add_action( 'wp_ajax_sendforce_dismiss_conflict', array( $this, 'ajax_dismiss_conflict' ) );
		add_action( 'wp_ajax_sendforce_import_preview', array( $this, 'ajax_import_preview' ) );
		add_action( 'wp_ajax_sendforce_import_run', array( $this, 'ajax_import_run' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_sendforce_send_test_email', array( $this, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_sendforce_get_provider', array( $this, 'ajax_get_provider' ) );
		add_action( 'wp_ajax_sendforce_view_log', array( $this, 'ajax_view_log' ) );
		add_action( 'wp_ajax_sendforce_save_channel', array( $this, 'ajax_save_channel' ) );
		add_action( 'wp_ajax_sendforce_toggle_channel', array( $this, 'ajax_toggle_channel' ) );
		add_action( 'wp_ajax_sendforce_send_test_alert', array( $this, 'ajax_send_test_alert' ) );
		add_action( 'wp_ajax_sendforce_save_connection', array( $this, 'ajax_save_connection' ) );
		add_action( 'wp_ajax_sendforce_delete_connection', array( $this, 'ajax_delete_connection' ) );
		add_action( 'wp_ajax_sendforce_chart_data', array( $this, 'ajax_chart_data' ) );
		add_action( 'wp_ajax_sendforce_sysinfo', array( $this, 'ajax_sysinfo' ) );

		// Smart Routing AJAX endpoints.
		add_action( 'wp_ajax_sendforce_get_route', array( $this, 'ajax_get_route' ) );
		add_action( 'wp_ajax_sendforce_save_route', array( $this, 'ajax_save_route' ) );
		add_action( 'wp_ajax_sendforce_delete_route', array( $this, 'ajax_delete_route' ) );
		add_action( 'wp_ajax_sendforce_reorder_routes', array( $this, 'ajax_reorder_routes' ) );
		add_action( 'wp_ajax_sendforce_test_route', array( $this, 'ajax_test_route' ) );
		add_action( 'wp_ajax_sendforce_toggle_routing', array( $this, 'ajax_toggle_routing' ) );
		add_action( 'wp_ajax_sendforce_cleanup_logs_now', array( $this, 'ajax_cleanup_logs_now' ) );
		add_action( 'wp_ajax_sendforce_process_queue_now', array( $this, 'ajax_process_queue_now' ) );

		// admin-post handlers (file download).
		add_action( 'admin_post_sendforce_export_logs', array( $this, 'handle_export_logs' ) );

		// WordPress main dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		// Add settings link on plugins page.
		add_filter( 'plugin_action_links_' . SENDFORCE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Cache-busting version for one of the plugin's own asset files.
	 *
	 * The plugin version alone is not enough: within a single release the files
	 * can change (a patched install, a hotfix, development), and browsers would
	 * keep serving the cached copy because the ?ver= never moved. The file's own
	 * modification time always moves when the file does.
	 *
	 * @param string $relative Path under the plugin directory, e.g. assets/js/admin.js.
	 * @return string
	 */
	private function asset_ver( $relative ) {
		$path = SENDFORCE_PLUGIN_DIR . $relative;
		$time = file_exists( $path ) ? filemtime( $path ) : 0;
		return $time ? (string) $time : SENDFORCE_VERSION;
	}

	/**
	 * Retire the Setup screen once a connection exists.
	 *
	 * Setup is for getting the first connection in place. After that it has
	 * nothing left to offer, so opening it — by an old bookmark, the back
	 * button, or a reactivation — lands on the Dashboard instead.
	 *
	 * The one exception is the reload that follows an import started on this
	 * screen: that import is what created the connection, and its result is
	 * waiting to be shown, so that render is allowed through.
	 */
	public function maybe_retire_setup_screen() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which screen was requested, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'sendforce-mail-relay' !== $page || 'setup' !== $tab ) {
			return;
		}
		if ( empty( get_option( 'sendforce_connections', array() ) ) ) {
			return;
		}
		// A deliberate return from a step the wizard sent them off to do, e.g.
		// saving the first connection on the Settings tab. Without this the
		// wizard would bounce them to the Dashboard the moment they finished
		// step 1, with step 2 never offered.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation flag, no state change.
		if ( ! empty( $_GET['resume'] ) ) {
			return;
		}
		if ( get_transient( 'sendforce_import_result_' . get_current_user_id() ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=sendforce-mail-relay' ) );
		exit;
	}

	/**
	 * Every active SMTP plugin SendForce knows about, whether or not the
	 * current user has dismissed its conflict warning.
	 *
	 * @return array Map of slug => [name, file, importable, deactivate_url].
	 */
	public function get_active_smtp_plugins() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$found = array();
		foreach ( $this->conflicting_plugins as $plugin_file => $plugin_name ) {
			if ( ! in_array( $plugin_file, $active_plugins, true ) ) {
				continue;
			}
			$slug           = explode( '/', $plugin_file )[0];
			$found[ $slug ] = array(
				'name'           => $plugin_name,
				'file'           => $plugin_file,
				'importable'     => isset( SendForce_Importer::SOURCES[ $slug ] ),
				'deactivate_url' => wp_nonce_url(
					admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $plugin_file ) . '&plugin_status=all' ),
					'deactivate-plugin_' . $plugin_file
				),
			);
		}
		return $found;
	}

	/**
	 * The subset of active SMTP plugins the current user has not dismissed.
	 * Used by the conflict notice; the Setup screen lists them all.
	 *
	 * @return array Map of slug => [name, file, deactivate_url].
	 */
	private function get_active_conflicts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'sendforce_conflict_dismissed', true );
		if ( ! is_array( $dismissed ) || empty( $dismissed['_v'] ) || $dismissed['_v'] < 2 ) {
			$dismissed = array( '_v' => 2 );
			update_user_meta( $user_id, 'sendforce_conflict_dismissed', $dismissed );
		}

		$now       = time();
		$conflicts = array();
		foreach ( $this->get_active_smtp_plugins() as $slug => $info ) {
			$expiry = isset( $dismissed[ $slug ] ) ? (int) $dismissed[ $slug ] : 0;
			if ( $expiry < $now ) {
				$conflicts[ $slug ] = $info;
			}
		}

		return $conflicts;
	}

	/**
	 * Enqueue the conflict-notice CSS + JS during admin_enqueue_scripts so
	 * the styles land in <head> rather than being printed too late from the
	 * admin_notices hook. Only loads when the notice will actually render, or
	 * on the Settings tab when its "Import from another plugin" card does.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_conflict_notice_assets( $hook = '' ) {
		$on_sendforce_page = 'toplevel_page_sendforce-mail-relay' === $hook && current_user_can( 'manage_options' );
		// The Settings tab's import card and the Setup screen both use this
		// script; the notice itself now renders only on our own screens.
		$needs_import_ui = $on_sendforce_page
			&& in_array( $this->get_current_tab(), array( 'settings', 'setup' ), true )
			&& ! empty( SendForce_Importer::available_sources() );
		if ( ! $needs_import_ui && ! ( $on_sendforce_page && $this->get_active_conflicts() ) ) {
			return;
		}
		wp_enqueue_style(
			'sendforce-conflict-notice',
			SENDFORCE_PLUGIN_URL . 'assets/css/conflict-notice.css',
			array(),
			$this->asset_ver( 'assets/css/conflict-notice.css' )
		);
		wp_enqueue_script(
			'sendforce-conflict-notice',
			SENDFORCE_PLUGIN_URL . 'assets/js/conflict-notice.js',
			array(),
			$this->asset_ver( 'assets/js/conflict-notice.js' ),
			true
		);
		wp_localize_script(
			'sendforce-conflict-notice',
			'sendForceConflict',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'importNonce' => wp_create_nonce( 'sendforce_import' ),
				'strings'     => array(
					'checking'     => __( 'Checking settings…', 'sendforce-mail-relay' ),
					'importing'    => __( 'Importing…', 'sendforce-mail-relay' ),
					'intro'        => __( 'This will be added to SendForce. Nothing in SendForce is replaced, and passwords are re-encrypted with SendForce\'s own key.', 'sendforce-mail-relay' ),
					'confirm'      => __( 'Import', 'sendforce-mail-relay' ),
					'cancel'       => __( 'Cancel', 'sendforce-mail-relay' ),
					'nothingToDo'  => __( 'Everything here has already been imported.', 'sendforce-mail-relay' ),
					'role_primary' => __( 'Primary', 'sendforce-mail-relay' ),
					'role_fallback' => __( 'Fallback', 'sendforce-mail-relay' ),
					'status_already' => __( 'Already imported', 'sendforce-mail-relay' ),
					'status_unsupported' => __( 'Can\'t be imported', 'sendforce-mail-relay' ),
					/* translators: %s: sender email address */
					'sender'       => __( 'Sender identity: %s', 'sendforce-mail-relay' ),
					/* translators: %d: number of connections */
					'done'         => __( 'Imported %d connection(s).', 'sendforce-mail-relay' ),
					/* translators: %d: number of routing rules */
					'doneRules'    => __( 'Created %d Smart Routing rule(s) for its per-sender routing.', 'sendforce-mail-relay' ),
					'routingOff'   => __( 'Smart Routing is switched off, so these rules will not apply until you turn it on in the Routing tab.', 'sendforce-mail-relay' ),
					/* translators: %s: comma-separated connection names */
					'doneAction'   => __( 'Needs your attention: %s', 'sendforce-mail-relay' ),
					'review'       => __( 'Review in Settings', 'sendforce-mail-relay' ),
					'nextStep'     => __( 'When you\'ve sent a test email from SendForce, deactivate the other plugin so only one of them handles email.', 'sendforce-mail-relay' ),
					'failed'       => __( 'Import failed. Please try again.', 'sendforce-mail-relay' ),
				),
			)
		);
	}

	/**
	 * On SendForce's own admin screens, strip every other plugin's admin
	 * notice from the page so our UI doesn't get cluttered with promo
	 * banners, "rate us" prompts, upgrade nags, etc.
	 *
	 * Runs at priority 1000 on `in_admin_header` so we fire AFTER all
	 * plugins have had a chance to register their notices on `admin_init`,
	 * but BEFORE WordPress prints the notices via `do_action( 'admin_notices' )`.
	 *
	 * Our own SendForce notices (conflict warning + settings_errors output)
	 * are re-attached after the wipe so they continue to render.
	 */
	public function maybe_suppress_other_notices() {
		if ( ! $this->is_sendforce_admin_screen() ) {
			return;
		}

		// Wipe every plugin's notice — including ours, then re-attach ours.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );

		// Re-attach our own notices so they still appear on our pages. The
		// credential warning especially must survive here — the Settings tab is
		// exactly where the user goes to re-enter the password it asks for.
		add_action( 'admin_notices', array( $this, 'show_conflict_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_credential_recovery_notice' ) );
	}

	/**
	 * Whether the current admin screen belongs to SendForce.
	 *
	 * @return bool
	 */
	private function is_sendforce_admin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		// Top-level page id matches `toplevel_page_<menu_slug>`.
		// Submenu ids match `<menu_label>_page_<menu_slug>` etc.
		// Both contain our slug, so a substring match is the simplest reliable check.
		return false !== strpos( (string) $screen->id, 'sendforce-mail-relay' );
	}

	/**
	 * Detect active conflicting SMTP plugins and show an admin notice.
	 */
	public function show_conflict_notice() {
		// Only on SendForce's own screens. This warning used to render on every
		// admin page, which meant someone activating an unrelated plugin met it
		// on the Plugins list with no context. New installs are walked through
		// the same information on the Setup screen instead.
		if ( ! $this->is_sendforce_admin_screen() ) {
			return;
		}
		// The Setup screen already leads with the same plugins, with more
		// context and the same buttons — showing both would just repeat it.
		if ( 'setup' === $this->get_current_tab() ) {
			return;
		}
		$conflicts = $this->get_active_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'sendforce_conflict_nonce' );
		?>
		<div class="sendforce-conflict-notice" id="sendforce-conflict-notice">
			<div class="sendforce-conflict-notice-inner">
				<div class="sendforce-conflict-icon">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
						<line x1="12" y1="9" x2="12" y2="13"></line>
						<line x1="12" y1="17" x2="12.01" y2="17"></line>
					</svg>
				</div>
				<div class="sendforce-conflict-content">
					<p class="sendforce-conflict-title">
						<?php
						printf(
							/* translators: %s: Plugin name */
							esc_html__( 'SendForce Mail Relay has detected %s that may conflict with email sending:', 'sendforce-mail-relay' ),
							1 === count( $conflicts )
								? esc_html__( 'another active SMTP plugin', 'sendforce-mail-relay' )
								: esc_html__( 'other active SMTP plugins', 'sendforce-mail-relay' )
						);
						?>
					</p>
					<ul class="sendforce-conflict-list">
						<?php foreach ( $conflicts as $slug => $info ) : ?>
						<li>
							<strong><?php echo esc_html( $info['name'] ); ?></strong>
							<?php if ( isset( SendForce_Importer::SOURCES[ $slug ] ) && SendForce_Importer::already_imported( $slug ) ) : ?>
								<span class="sendforce-import-badge is-muted"><?php esc_html_e( 'Imported', 'sendforce-mail-relay' ); ?></span>
							<?php endif; ?>
							<span class="sendforce-conflict-actions">
								<?php
								if ( isset( SendForce_Importer::SOURCES[ $slug ] ) ) :
									$imported = SendForce_Importer::already_imported( $slug );
									?>
									<button type="button" class="sendforce-conflict-import<?php echo $imported ? ' is-secondary' : ''; ?>"
										data-source="<?php echo esc_attr( $slug ); ?>"
										data-done-label="<?php esc_attr_e( 'Check again', 'sendforce-mail-relay' ); ?>">
										<?php echo $imported ? esc_html__( 'Check again', 'sendforce-mail-relay' ) : esc_html__( 'Import settings', 'sendforce-mail-relay' ); ?>
									</button>
								<?php endif; ?>
								<a href="<?php echo esc_url( $info['deactivate_url'] ); ?>" class="sendforce-conflict-deactivate">
									<?php esc_html_e( 'Deactivate', 'sendforce-mail-relay' ); ?>
								</a>
								<button type="button" class="sendforce-conflict-dismiss-one"
									data-slug="<?php echo esc_attr( $slug ); ?>"
									data-nonce="<?php echo esc_attr( $nonce ); ?>"
									title="<?php esc_attr_e( 'Dismiss for 30 days', 'sendforce-mail-relay' ); ?>">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
										<line x1="18" y1="6" x2="6" y2="18"></line>
										<line x1="6" y1="6" x2="18" y2="18"></line>
									</svg>
								</button>
							</span>
							<?php if ( isset( SendForce_Importer::SOURCES[ $slug ] ) ) : ?>
								<div class="sendforce-import-panel" data-source="<?php echo esc_attr( $slug ); ?>" aria-live="polite" hidden></div>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<button type="button" class="sendforce-conflict-close" id="sendforce-conflict-close"
					title="<?php esc_attr_e( 'Hide until next page load', 'sendforce-mail-relay' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
		</div>
		<?php
		// Assets for this notice are enqueued separately via the
		// admin_enqueue_scripts hook (see enqueue_conflict_notice_assets).
	}

	/**
	 * AJAX: what importing a source plugin's settings would do (no secrets).
	 */
	public function ajax_import_preview() {
		check_ajax_referer( 'sendforce_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}
		$source  = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		$preview = SendForce_Importer::preview( $source );
		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( $preview->get_error_message() );
		}
		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: import a source plugin's connections and sender settings.
	 */
	public function ajax_import_run() {
		check_ajax_referer( 'sendforce_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		$result = SendForce_Importer::import( $source );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		$result['settings_url'] = admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' );

		// The Setup screen reloads after an import so its steps reflect the new
		// connection. Stash the outcome so that reload can show it, rendered
		// server-side rather than rebuilt from strings in JavaScript. Only for
		// that screen: an import run from the notice or the Settings tab shows
		// its result inline, and stashing it there would surface a stale banner
		// the next time the Setup screen happened to be opened.
		if ( 'setup' === sanitize_key( wp_unslash( $_POST['context'] ?? '' ) ) ) {
			set_transient( 'sendforce_import_result_' . get_current_user_id(), $result, 2 * MINUTE_IN_SECONDS );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Dismiss a single conflicting plugin notice for 30 days.
	 */
	public function ajax_dismiss_conflict() {
		check_ajax_referer( 'sendforce_conflict_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$slug    = sanitize_text_field( wp_unslash( $_POST['slug'] ?? '' ) );
		$user_id = get_current_user_id();

		if ( ! $user_id || empty( $slug ) ) {
			wp_send_json_error();
		}

		$dismissed = get_user_meta( $user_id, 'sendforce_conflict_dismissed', true );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array( '_v' => 2 );
		}

		$dismissed[ $slug ] = time() + ( 30 * DAY_IN_SECONDS );

		update_user_meta( $user_id, 'sendforce_conflict_dismissed', $dismissed );

		wp_send_json_success();
	}

	/**
	 * One-time "Welcome to v{X}.{Y}.{Z}" admin notice shown to admins after
	 * the plugin updates to a new version. Links to the in-admin "What's New"
	 * tab so users discover the new release's features without having to
	 * visit WordPress.org. Dismissible per-user; doesn't show again for the
	 * same version after dismissal.
	 *
	 * Stored as user-meta (per-admin) not site-option so each admin on a
	 * multi-admin install sees the welcome once on their first visit after
	 * the update. Auto-suppresses on activation (set during activator) so
	 * fresh installs don't see "welcome to 1.0.X" when there's no prior
	 * version to compare against.
	 */
	public function show_update_welcome_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Don't render on the SendForce tabs themselves — the "What's New"
		// tab IS the destination, so we'd be telling users to go where they
		// already are. The notice is for OTHER admin pages where they'd
		// otherwise miss the update.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'sendforce-mail-relay' ) ) {
			return;
		}

		$user_id      = get_current_user_id();
		$last_seen    = (string) get_user_meta( $user_id, 'sendforce_welcome_seen_version', true );
		$current_ver  = SENDFORCE_VERSION;

		// Hide notice when:
		//  - user has already seen this exact version's welcome
		//  - last_seen is empty AND this is the very first user-meta read
		//    (treated as a fresh install rather than an upgrade — set the
		//    flag silently so we don't pop a "welcome to vN" the first time
		//    someone installs the plugin)
		if ( '' === $last_seen ) {
			update_user_meta( $user_id, 'sendforce_welcome_seen_version', $current_ver );
			return;
		}
		if ( version_compare( $last_seen, $current_ver, '>=' ) ) {
			return;
		}

		$whats_new_url = admin_url( 'admin.php?page=sendforce-mail-relay&tab=upcoming' );
		?>
		<div class="notice notice-success is-dismissible sendforce-welcome-notice" data-sendforce-version="<?php echo esc_attr( $current_ver ); ?>">
			<p>
				<strong>SendForce Mail Relay</strong>
				<?php
				/* translators: %s: plugin version, e.g. 1.0.28 */
				printf( esc_html__( 'updated to v%s.', 'sendforce-mail-relay' ), esc_html( $current_ver ) );
				?>
				&nbsp;
				<a href="<?php echo esc_url( $whats_new_url ); ?>" class="button button-small sendforce-welcome-cta">
					<?php esc_html_e( "See what's new", 'sendforce-mail-relay' ); ?>
				</a>
			</p>
		</div>
		<?php
		// Behaviour lives in an enqueued file rather than an inline <script>
		// (WordPress.org guideline). Enqueuing here, during admin_notices, is
		// fine: footer scripts are printed later in admin_print_footer_scripts.
		wp_enqueue_script(
			'sendforce-welcome-notice',
			SENDFORCE_PLUGIN_URL . 'assets/js/welcome-notice.js',
			array(),
			$this->asset_ver( 'assets/js/welcome-notice.js' ),
			true
		);
		wp_localize_script(
			'sendforce-welcome-notice',
			'sendForceWelcome',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sendforce_nonce' ),
			)
		);
	}

	/**
	 * Warn when a stored credential can no longer be decrypted.
	 *
	 * Before 1.0.37 the encryption key was derived from wp_salt('auth'). If the
	 * site's salts were rotated — a security plugin's "regenerate security
	 * keys", a host migration, a staging push — every stored password became
	 * permanently unreadable. decrypt_password() fails closed, so the connection
	 * still LOOKS configured while silently sending with an empty password, and
	 * users blamed whichever plugin update happened to follow. Surfacing it
	 * explicitly turns a silent mail outage into a one-line fix.
	 *
	 * The scan decrypts stored secrets, so the result is cached for an hour
	 * rather than repeated on every admin page load.
	 */
	public function show_credential_recovery_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$broken = get_transient( 'sendforce_broken_credentials' );
		if ( false === $broken ) {
			$broken      = array();
			$connections = get_option( 'sendforce_connections', array() );
			if ( is_array( $connections ) ) {
				foreach ( $connections as $conn ) {
					if ( ! is_array( $conn ) ) {
						continue;
					}
					foreach ( array( 'password', 'api_key' ) as $field ) {
						if ( '' === (string) ( $conn[ $field ] ?? '' ) ) {
							continue;
						}
						SendForce_Mailer::$last_decrypt_failed = false;
						SendForce_Mailer::decrypt_password( (string) $conn[ $field ] );
						if ( SendForce_Mailer::$last_decrypt_failed ) {
							$broken[] = (string) ( $conn['label'] ?? $conn['id'] ?? '' );
							break;
						}
					}
				}
			}
			$broken = array_values( array_unique( array_filter( $broken ) ) );
			set_transient( 'sendforce_broken_credentials', $broken, HOUR_IN_SECONDS );
		}

		if ( empty( $broken ) || ! is_array( $broken ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' );
		?>
		<div class="notice notice-error sendforce-admin-notice">
			<p>
				<strong><?php esc_html_e( 'SendForce Mail Relay:', 'sendforce-mail-relay' ); ?></strong>
				<?php
				printf(
					/* translators: %s: comma-separated connection names */
					esc_html__( 'The saved credentials for %s can no longer be read, so email sending will fail. This happens when a site\'s security keys/salts are regenerated (by a security plugin, host migration or site clone), which invalidates previously encrypted passwords.', 'sendforce-mail-relay' ),
					'<strong>' . esc_html( implode( ', ', $broken ) ) . '</strong>'
				);
				?>
				&nbsp;
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-small">
					<?php esc_html_e( 'Re-enter password', 'sendforce-mail-relay' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: mark the current version's update-welcome notice as seen for
	 * this user. Triggered by the WP-core dismiss "X" on the notice.
	 */
	public function ajax_dismiss_update_welcome() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );
		if ( '' === $version ) {
			$version = SENDFORCE_VERSION;
		}
		update_user_meta( get_current_user_id(), 'sendforce_welcome_seen_version', $version );
		wp_send_json_success();
	}

	/**
	 * Add admin menu — single top-level page.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'SendForce Mail Relay', 'sendforce-mail-relay' ),
			__( 'SendForce', 'sendforce-mail-relay' ),
			'manage_options',
			'sendforce-mail-relay',
			array( $this, 'render_dashboard' ),
			'dashicons-email-alt',
			80
		);

		// Sub-menu items — each opens the main page on a specific tab.
		// All point to render_dashboard(); the tab is selected via ?tab= and
		// validated against the whitelist in get_current_tab().
		$submenus = array(
			'dashboard'  => __( 'Dashboard', 'sendforce-mail-relay' ),
			'settings'   => __( 'Settings', 'sendforce-mail-relay' ),
			'routing'    => __( 'Routing', 'sendforce-mail-relay' ),
			'email-log'  => __( 'Email Log', 'sendforce-mail-relay' ),
			'queue'      => __( 'Email Queue', 'sendforce-mail-relay' ),
			'email-test' => __( 'Email Test', 'sendforce-mail-relay' ),
			'alerts'     => __( 'Alerts', 'sendforce-mail-relay' ),
		);

		foreach ( $submenus as $tab => $label ) {
			add_submenu_page(
				'sendforce-mail-relay',
				$label,
				$label,
				'manage_options',
				'sendforce-mail-relay' . ( 'dashboard' === $tab ? '' : '&tab=' . $tab ),
				array( $this, 'render_dashboard' )
			);
		}

		// Rename the auto-added first submenu (which mirrors the parent slug)
		// from "SendForce" to "Dashboard" so the flyout reads cleanly.
		// (Handled by registering 'dashboard' first above; WordPress overrides
		// the auto-entry when the slug matches the parent.)
	}

	/**
	 * Highlight the correct sidebar submenu based on the active ?tab= query.
	 *
	 * WordPress matches submenu active state by the `page` slug alone, so when
	 * several submenus share the same parent page (with different ?tab= values)
	 * only the first one ever gets the "current" highlight. This filter rewrites
	 * the highlight target to the slug we registered for the current tab.
	 *
	 * @param string|null $submenu_file The current submenu slug WordPress chose.
	 * @param string      $parent_file  The parent menu slug.
	 * @return string|null
	 */
	public function highlight_active_submenu( $submenu_file, $parent_file ) {
		// Only act on our own plugin page.
		if ( ! isset( $_GET['page'] ) || 'sendforce-mail-relay' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only nav-state check, no state mutation.
			return $submenu_file;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only nav-state check.

		if ( ! in_array( $tab, $this->tabs, true ) ) {
			$tab = 'dashboard';
		}

		return 'dashboard' === $tab
			? 'sendforce-mail-relay'
			: 'sendforce-mail-relay&tab=' . $tab;
	}

	/**
	 * Get the current tab.
	 *
	 * @return string
	 */
	public function get_current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, $this->tabs, true ) ? $tab : 'dashboard';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$is_plugin_page    = 'toplevel_page_sendforce-mail-relay' === $hook;
		$is_wp_dashboard   = 'index.php' === $hook;

		if ( ! $is_plugin_page && ! $is_wp_dashboard ) {
			return;
		}

		// Dashboard widget assets (Chart.js + lightweight widget JS/CSS only).
		if ( $is_wp_dashboard ) {
			wp_enqueue_script(
				'chartjs',
				SENDFORCE_PLUGIN_URL . 'assets/js/chart.umd.min.js',
				array(),
				'4.5.0',
				true
			);
			wp_enqueue_script(
				'sendforce-widget',
				SENDFORCE_PLUGIN_URL . 'assets/js/dashboard-widget.js',
				array( 'jquery', 'chartjs' ),
				$this->asset_ver( 'assets/js/dashboard-widget.js' ),
				true
			);
			wp_enqueue_style(
				'sendforce-widget',
				SENDFORCE_PLUGIN_URL . 'assets/css/dashboard-widget.css',
				array(),
				$this->asset_ver( 'assets/css/dashboard-widget.css' )
			);
			wp_localize_script(
				'sendforce-widget',
				'sendForceWidget',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sendforce_nonce' ),
				)
			);
			return;
		}

		if ( ! $is_plugin_page ) {
			return;
		}

		// Use file modification time as version → guaranteed cache busting on
		// every CSS edit, no matter the user's browser cache settings.
		$sendforce_admin_css_path = SENDFORCE_PLUGIN_DIR . 'assets/css/admin.css';
		$sendforce_admin_css_ver  = file_exists( $sendforce_admin_css_path )
			? (string) filemtime( $sendforce_admin_css_path )
			: SENDFORCE_VERSION;

		wp_enqueue_style(
			'sendforce-admin',
			SENDFORCE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$sendforce_admin_css_ver
		);

		wp_enqueue_style(
			'sendforce-custom-select',
			SENDFORCE_PLUGIN_URL . 'assets/css/custom-select.css',
			array( 'sendforce-admin' ),
			$this->asset_ver( 'assets/css/custom-select.css' )
		);

		wp_enqueue_style(
			'sendforce-oauth',
			SENDFORCE_PLUGIN_URL . 'assets/css/oauth.css',
			array( 'sendforce-admin' ),
			$this->asset_ver( 'assets/css/oauth.css' )
		);

		wp_enqueue_script(
			'sendforce-routing',
			SENDFORCE_PLUGIN_URL . 'assets/js/routing.js',
			array( 'jquery', 'sendforce-admin' ),
			$this->asset_ver( 'assets/js/routing.js' ),
			true
		);

		wp_enqueue_script(
			'sendforce-custom-select',
			SENDFORCE_PLUGIN_URL . 'assets/js/custom-select.js',
			array(),
			$this->asset_ver( 'assets/js/custom-select.js' ),
			true
		);

		wp_enqueue_script(
			'chartjs',
			SENDFORCE_PLUGIN_URL . 'assets/js/chart.umd.min.js',
			array(),
			'4.5.0',
			true
		);

		wp_enqueue_script(
			'sendforce-admin',
			SENDFORCE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs' ),
			$this->asset_ver( 'assets/js/admin.js' ),
			true
		);

		$raw_connections = get_option( 'sendforce_connections', array() );
		$js_connections  = array();
		foreach ( $raw_connections as $conn ) {
			$js_connections[] = array(
				'id'                   => $conn['id'] ?? '',
				'label'                => $conn['label'] ?? '',
				'provider'             => $conn['provider'] ?? 'other',
				'connection_type'      => $conn['connection_type'] ?? 'smtp',
				'host'                 => $conn['host'] ?? '',
				'port'                 => $conn['port'] ?? 587,
				'encryption'           => $conn['encryption'] ?? 'tls',
				'auth'                 => ! empty( $conn['auth'] ),
				'username'             => $conn['username'] ?? '',
				'has_password'         => ! empty( $conn['password'] ),
				'has_api_key'          => ! empty( $conn['api_key'] ),
				'ses_region'           => $conn['ses_region'] ?? 'us-east-1',
				'ses_access_key'       => $conn['ses_access_key'] ?? '',
				'mailgun_domain'       => $conn['mailgun_domain'] ?? '',
				'mailgun_region'       => $conn['mailgun_region'] ?? 'us',
				'smtpcom_channel'      => $conn['smtpcom_channel'] ?? '',
				'socketlabs_server_id' => $conn['socketlabs_server_id'] ?? '',
				'outlook_tenant_id'    => $conn['outlook_tenant_id'] ?? '',
				'outlook_client_id'    => $conn['outlook_client_id'] ?? '',
				'gmail_client_id'      => $conn['gmail_client_id'] ?? '',
				'oauth_email'          => $conn['oauth_email'] ?? '',
				'rate_limit'           => absint( $conn['rate_limit'] ?? 0 ),
			);
		}

		wp_localize_script(
			'sendforce-admin',
			'sendForce',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'sendforce_nonce' ),
				'connections' => $js_connections,
				'strings'     => array(
					// Pre-existing keys (kept for back-compat with the JS).
					'sending'            => __( 'Sending...', 'sendforce-mail-relay' ),
					'send_test'          => __( 'Send Test Email', 'sendforce-mail-relay' ),
					'success'            => __( 'Test email sent successfully!', 'sendforce-mail-relay' ),
					'error'              => __( 'Failed to send test email.', 'sendforce-mail-relay' ),
					'confirm_delete'     => __( 'Are you sure you want to delete the selected items?', 'sendforce-mail-relay' ),
					'confirm_del_conn'   => __( 'Delete this connection? This cannot be undone.', 'sendforce-mail-relay' ),
					'saving'             => __( 'Saving…', 'sendforce-mail-relay' ),
					'deleting'           => __( 'Deleting...', 'sendforce-mail-relay' ),
					'add_connection'     => __( 'Add Connection', 'sendforce-mail-relay' ),
					'edit_connection'    => __( 'Edit Connection', 'sendforce-mail-relay' ),
					'conn_saved'         => __( 'Connection saved successfully.', 'sendforce-mail-relay' ),
					'conn_deleted'       => __( 'Connection deleted.', 'sendforce-mail-relay' ),

					// Field labels (provider grid + connection form).
					'show_less'          => __( 'Show Less', 'sendforce-mail-relay' ),
					'show_all_providers' => __( 'Show All Providers', 'sendforce-mail-relay' ),
					'username'           => __( 'Username', 'sendforce-mail-relay' ),
					'password'           => __( 'Password', 'sendforce-mail-relay' ),
					'api_key'            => __( 'API Key', 'sendforce-mail-relay' ),
					/* translators: %s: the default username value for the selected provider. */
					'default_for_provider' => __( 'Default for this provider: %s', 'sendforce-mail-relay' ),
					'api_mode_desc'      => __( 'Send emails directly via the provider HTTP API. Faster and more reliable than SMTP.', 'sendforce-mail-relay' ),
					'smtp_mode_desc'     => __( 'Send emails via the traditional SMTP protocol.', 'sendforce-mail-relay' ),

					// Generic actions / confirm modal.
					'confirm'            => __( 'Confirm', 'sendforce-mail-relay' ),
					'confirm_generic'    => __( 'Are you sure? This action cannot be undone.', 'sendforce-mail-relay' ),
					'ok'                 => __( 'OK', 'sendforce-mail-relay' ),
					'save'               => __( 'Save', 'sendforce-mail-relay' ),
					'edit'               => __( 'Edit', 'sendforce-mail-relay' ),
					'delete'             => __( 'Delete', 'sendforce-mail-relay' ),
					'edit_conn_aria'     => __( 'Edit connection', 'sendforce-mail-relay' ),
					'delete_conn_aria'   => __( 'Delete connection', 'sendforce-mail-relay' ),
					'save_connection'    => __( 'Save Connection', 'sendforce-mail-relay' ),
					'validating'         => __( 'Validating data. Please wait…', 'sendforce-mail-relay' ),
					'copied'             => __( 'Copied', 'sendforce-mail-relay' ),
					'request_failed'     => __( 'Request failed. Please try again.', 'sendforce-mail-relay' ),
					'request_failed_short' => __( 'Request failed.', 'sendforce-mail-relay' ),
					'network_error'      => __( 'Network error', 'sendforce-mail-relay' ),
					'network_error_msg'  => __( 'Could not reach the server. Please try again.', 'sendforce-mail-relay' ),

					// Delete confirmations.
					'del_conn_title'     => __( 'Delete Connection', 'sendforce-mail-relay' ),
					'del_conn_msg'       => __( 'Are you sure you want to delete this connection? This action cannot be undone.', 'sendforce-mail-relay' ),
					'err_del_conn'       => __( 'Error deleting connection.', 'sendforce-mail-relay' ),
					'del_logs_title'     => __( 'Delete Selected Logs', 'sendforce-mail-relay' ),
					/* translators: %d: number of selected log entries. */
					'del_logs_msg_one'   => __( 'Are you sure you want to delete %d selected log? This action cannot be undone.', 'sendforce-mail-relay' ),
					/* translators: %d: number of selected log entries. */
					'del_logs_msg_many'  => __( 'Are you sure you want to delete %d selected logs? This action cannot be undone.', 'sendforce-mail-relay' ),
					'del_queue_title'    => __( 'Delete Queue Item', 'sendforce-mail-relay' ),
					'del_queue_msg'      => __( 'Are you sure you want to delete this queued email? This action cannot be undone.', 'sendforce-mail-relay' ),

					// Connection save / test.
					'err_save_conn'      => __( 'Error saving connection.', 'sendforce-mail-relay' ),
					'err_save_settings'  => __( 'Error saving settings.', 'sendforce-mail-relay' ),
					'send_test_short'    => __( 'Send Test', 'sendforce-mail-relay' ),
					'err_send_test'      => __( 'Failed to send test.', 'sendforce-mail-relay' ),

					// Debug / clipboard.
					'collecting'         => __( 'Collecting…', 'sendforce-mail-relay' ),
					'err_debug'          => __( 'Failed to collect debug info.', 'sendforce-mail-relay' ),
					'copied_clipboard'   => __( 'Copied to clipboard ✓', 'sendforce-mail-relay' ),
					'copy_manual'        => __( 'Could not auto-copy — select the text above and copy manually.', 'sendforce-mail-relay' ),

					// Channel modal.
					/* translators: %s: alert channel name (e.g. Telegram, Slack). */
					'configure_channel'  => __( 'Configure %s', 'sendforce-mail-relay' ),

					// OAuth (Gmail / Microsoft 365).
					/* translators: %s: the connected account's email address. */
					'connected_as'       => __( 'Connected as %s', 'sendforce-mail-relay' ),
					'connected'          => __( 'Connected.', 'sendforce-mail-relay' ),
					'reconnect'          => __( 'Reconnect', 'sendforce-mail-relay' ),
					'oauth_orphaned'     => __( 'Connection has a stored OAuth token but the Client ID or Client Secret is missing. Re-enter your OAuth credentials and click Reconnect.', 'sendforce-mail-relay' ),
					'oauth_not_connected' => __( 'Not connected. Save the connection, then click Connect.', 'sendforce-mail-relay' ),
					'oauth_switch_api'   => __( 'Switch the connection to API mode above to use OAuth.', 'sendforce-mail-relay' ),
					'oauth_connect'      => __( 'Connect with provider', 'sendforce-mail-relay' ),
					'oauth_save_first_title' => __( 'Save the connection first', 'sendforce-mail-relay' ),
					'oauth_save_first_msg' => __( 'Enter your Client ID and Client Secret, then click "Save Connection". After it\'s saved you can click "Connect with provider" to authorise.', 'sendforce-mail-relay' ),
					'oauth_opening'      => __( 'Opening provider…', 'sendforce-mail-relay' ),
					'oauth_start_fail_title' => __( 'Could not start authorisation', 'sendforce-mail-relay' ),
					'oauth_start_fail_msg' => __( 'Failed to start OAuth flow.', 'sendforce-mail-relay' ),
					'oauth_disconnect_title' => __( 'Disconnect this account?', 'sendforce-mail-relay' ),
					'oauth_disconnect_msg' => __( 'You will need to reconnect to send via this connection. Stored OAuth tokens will be removed.', 'sendforce-mail-relay' ),
					'disconnect'         => __( 'Disconnect', 'sendforce-mail-relay' ),
					'oauth_disconnect_fail_title' => __( 'Disconnect failed', 'sendforce-mail-relay' ),
					'oauth_disconnect_fail_msg' => __( 'Failed to disconnect.', 'sendforce-mail-relay' ),

					// Queue / cleanup buttons.
					'processing'         => __( 'Processing...', 'sendforce-mail-relay' ),
					'queue_processed'    => __( 'Queue processed.', 'sendforce-mail-relay' ),
					'queue_fail_title'   => __( 'Queue processing failed', 'sendforce-mail-relay' ),
					'queue_fail_msg'     => __( 'Queue processing failed.', 'sendforce-mail-relay' ),
					'clean_logs_title'   => __( 'Clean logs now?', 'sendforce-mail-relay' ),
					'clean_logs_msg'     => __( 'This will immediately delete log entries older than your configured retention period. This action cannot be undone.', 'sendforce-mail-relay' ),
					'clean_now'          => __( 'Clean Now', 'sendforce-mail-relay' ),
					'cleaning'           => __( 'Cleaning...', 'sendforce-mail-relay' ),
					'cleanup_complete'   => __( 'Cleanup complete.', 'sendforce-mail-relay' ),
					'cleanup_fail_title' => __( 'Cleanup failed', 'sendforce-mail-relay' ),
					'cleanup_fail_msg'   => __( 'Cleanup failed.', 'sendforce-mail-relay' ),
				),
			)
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'sendforce_settings_group',
			'sendforce_settings',
			array( $this, 'sanitize_settings' )
		);

		register_setting(
			'sendforce_alerts_group',
			'sendforce_alerts',
			array( $this, 'sanitize_alert_settings' )
		);
	}

	/**
	 * Handle form actions (bulk delete, queue actions).
	 *
	 * Both branches gate on `manage_options` in addition to the nonce check.
	 * The nonce alone only proves the request originated from a logged-in
	 * user with a valid session — it does NOT prove they have admin rights.
	 * In practice the form/link that mints these nonces is rendered only on
	 * pages already gated by `manage_options`, so a non-admin can't normally
	 * obtain a valid nonce. The capability check here is defense-in-depth
	 * and what WordPress.org's Plugin Check tool flags as required.
	 */
	public function handle_actions() {
		// Log bulk delete.
		if ( isset( $_POST['sendforce_log_action'] ) ) {
			check_admin_referer( 'sendforce_log_bulk', 'sendforce_log_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'sendforce-mail-relay' ), '', array( 'response' => 403 ) );
			}

			$action = sanitize_text_field( wp_unslash( $_POST['sendforce_log_action'] ) );

			if ( 'delete' === $action && ! empty( $_POST['log_ids'] ) ) {
				$log_ids = array_map( 'absint', $_POST['log_ids'] );
				SendForce_Logger::bulk_delete( $log_ids );
				add_settings_error( 'sendforce', 'logs_deleted', __( 'Selected logs deleted.', 'sendforce-mail-relay' ), 'updated' );
			}
		}

		// Queue actions.
		if ( isset( $_GET['sendforce_queue_action'] ) ) {
			$action   = sanitize_text_field( wp_unslash( $_GET['sendforce_queue_action'] ) );
			$queue_id = isset( $_GET['queue_id'] ) ? absint( $_GET['queue_id'] ) : 0;

			if ( $queue_id ) {
				check_admin_referer( 'sendforce_queue_' . $action . '_' . $queue_id );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to perform this action.', 'sendforce-mail-relay' ), '', array( 'response' => 403 ) );
				}

				if ( 'retry' === $action ) {
					SendForce_Queue::retry_queue_item( $queue_id );
					add_settings_error( 'sendforce', 'queue_retried', __( 'Email queued for retry.', 'sendforce-mail-relay' ), 'updated' );
				} elseif ( 'delete' === $action ) {
					SendForce_Queue::delete_queue_item( $queue_id );
					add_settings_error( 'sendforce', 'queue_deleted', __( 'Queue item deleted.', 'sendforce-mail-relay' ), 'updated' );
				}
			}
		}
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Connection routing.
		$sanitized['default_connection']  = sanitize_text_field( $input['default_connection'] ?? '' );
		$sanitized['fallback_connection'] = sanitize_text_field( $input['fallback_connection'] ?? '' );

		// Global sender overrides. Reject malformed emails so we never store
		// a half-truncated value that wp_mail will silently fail on.
		$from_email = sanitize_email( $input['from_email'] ?? '' );
		if ( '' !== $from_email && ! is_email( $from_email ) ) {
			add_settings_error( 'sendforce_settings', 'invalid_from_email', __( 'The "From Email" address is not a valid email; the previous value was kept.', 'sendforce-mail-relay' ), 'error' );
			$existing = get_option( 'sendforce_settings', array() );
			$from_email = $existing['from_email'] ?? '';
		}
		$sanitized['from_email'] = $from_email;
		$sanitized['from_name']  = sanitize_text_field( $input['from_name'] ?? '' );

		// Logging.
		$sanitized['log_enabled']        = ! empty( $input['log_enabled'] );
		$sanitized['log_retention_days'] = absint( $input['log_retention_days'] ?? 30 );
		$sanitized['log_email_body']     = ! empty( $input['log_email_body'] );

		// Sending behaviour.
		$sanitized['simulation_mode'] = ! empty( $input['simulation_mode'] );
		$sanitized['multipart_text']  = ! empty( $input['multipart_text'] );

		// Queue. Clamp ranges to match the form-level min/max so a copy-pasted
		// or hand-crafted POST cannot persist out-of-bounds values.
		$sanitized['queue_enabled'] = ! empty( $input['queue_enabled'] );
		$sanitized['batch_size']    = max( 1, min( 500, absint( $input['batch_size'] ?? 50 ) ) );
		// Run interval in minutes: one of SendForce_Queue::allowed_intervals(), else 1.
		$sanitized['queue_interval'] = SendForce_Queue::interval_minutes( $input );
		$sanitized['max_attempts']  = max( 1, min( 10, absint( $input['max_attempts'] ?? 3 ) ) );

		// Developer debugging.
		$sanitized['developer_debug'] = ! empty( $input['developer_debug'] );

		return $sanitized;
	}

	/**
	 * Get active connection config (for test email and mailer validation).
	 *
	 * @return array Connection config array (may be merged sendforce_settings for legacy mode).
	 */
	private function get_active_connection_config() {
		$settings   = get_option( 'sendforce_settings', array() );
		$default_id = $settings['default_connection'] ?? '';

		if ( 'phpmail' === $default_id ) {
			return array( 'provider' => 'phpmail' );
		}

		if ( ! empty( $default_id ) ) {
			$connections = get_option( 'sendforce_connections', array() );
			foreach ( $connections as $conn ) {
				if ( isset( $conn['id'] ) && $conn['id'] === $default_id ) {
					return $conn;
				}
			}
		}

		// Legacy fallback — sendforce_settings used to hold SMTP config directly.
		return $settings;
	}

	/**
	 * Render the main dashboard shell.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SENDFORCE_PLUGIN_DIR . 'admin/partials/dashboard-page.php';
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array
	 */
	public static function get_dashboard_stats() {
		// The two lifetime COUNT(*) queries grow with the log table and run on
		// every dashboard page load. Cache them for an hour; the cache is
		// flushed immediately whenever a new log row is inserted (see
		// SendForce_Logger::insert_log) so the numbers never look stale after
		// an actual send. Queue stats are left live — that table is small and
		// changes on a different cadence.
		$counts = get_transient( 'sendforce_dashboard_counts' );

		if ( ! is_array( $counts ) || ! isset( $counts['total_sent'], $counts['total_failed'] ) ) {
			global $wpdb;

			$total_sent = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'sent'"
			);

			$total_failed = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'failed'"
			);

			$counts = array(
				'total_sent'   => absint( $total_sent ),
				'total_failed' => absint( $total_failed ),
			);

			set_transient( 'sendforce_dashboard_counts', $counts, HOUR_IN_SECONDS );
		}

		$queue_stats = SendForce_Queue::get_stats();
		$total_all   = $counts['total_sent'] + $counts['total_failed'];
		$success_pct = $total_all > 0 ? round( ( $counts['total_sent'] / $total_all ) * 100, 1 ) : 0;

		return array(
			'total_sent'   => $counts['total_sent'],
			'total_failed' => $counts['total_failed'],
			'queued'       => $queue_stats['queued'],
			'success_rate' => $success_pct,
		);
	}

	/**
	 * Get recent email log entries.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public static function get_recent_emails( $limit = 10 ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, to_email, subject, status, created_at FROM {$wpdb->prefix}sendforce_logs ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get daily email stats for the last N days.
	 *
	 * @param int $days Number of days.
	 * @return array Array of ['date' => 'Y-m-d', 'label' => 'Mon', 'sent' => N, 'failed' => N].
	 */
	public static function get_daily_stats( $days = 7 ) {
		$days = absint( $days );

		// This runs one COUNT per day per status — up to 180 queries for the
		// 90-day range. Cache the assembled series for an hour, keyed by range.
		// Flushed on every new log insert so a fresh send shows up promptly.
		$cache_key = 'sendforce_daily_stats_' . $days;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date  = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			// Use short day name for 7 days, short date for longer ranges.
			$label = $days <= 7
				? gmdate( 'D', strtotime( "-{$i} days" ) )
				: gmdate( 'M j', strtotime( "-{$i} days" ) );

			$sent = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'sent' AND DATE(created_at) = %s",
					$date
				)
			);

			$failed = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'failed' AND DATE(created_at) = %s",
					$date
				)
			);

			$results[] = array(
				'date'   => $date,
				'label'  => $label,
				'sent'   => absint( $sent ),
				'failed' => absint( $failed ),
			);
		}

		set_transient( $cache_key, $results, HOUR_IN_SECONDS );

		return $results;
	}

	/**
	 * Flush the cached dashboard/chart statistics. Called whenever a new log
	 * row is written so the dashboard reflects real activity within the same
	 * request rather than waiting out the hour-long transient TTL.
	 *
	 * Keys mirror those set in get_dashboard_stats() and get_daily_stats().
	 *
	 * @return void
	 */
	public static function flush_stats_cache() {
		delete_transient( 'sendforce_dashboard_counts' );
		delete_transient( 'sendforce_daily_stats_7' );
		delete_transient( 'sendforce_daily_stats_30' );
		delete_transient( 'sendforce_daily_stats_90' );
	}

	/**
	 * Get totals for the last 7 days.
	 *
	 * @return array ['sent' => N, 'failed' => N].
	 */
	public static function get_weekly_totals() {
		global $wpdb;

		$since = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		$sent = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'sent' AND created_at >= %s",
				$since
			)
		);

		$failed = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'failed' AND created_at >= %s",
				$since
			)
		);

		return array(
			'sent'   => absint( $sent ),
			'failed' => absint( $failed ),
		);
	}

	/**
	 * Register the WP Dashboard widget.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'sendforce_dashboard_widget',
			__( 'SendForce Mail Relay — Email Stats', 'sendforce-mail-relay' ),
			array( $this, 'render_dashboard_widget' )
		);

		// Force widget to the very top of the first (normal) column.
		global $wp_meta_boxes;

		$widget_id = 'sendforce_dashboard_widget';
		$widget    = null;

		// Find and remove it from wherever WordPress placed it.
		foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
			if ( isset( $wp_meta_boxes['dashboard']['normal'][ $priority ][ $widget_id ] ) ) {
				$widget = $wp_meta_boxes['dashboard']['normal'][ $priority ][ $widget_id ];
				unset( $wp_meta_boxes['dashboard']['normal'][ $priority ][ $widget_id ] );
				break;
			}
		}

		// Prepend to the 'high' priority slot so it renders first.
		if ( $widget ) {
			if ( ! isset( $wp_meta_boxes['dashboard']['normal']['high'] ) ) {
				$wp_meta_boxes['dashboard']['normal']['high'] = array();
			}
			$wp_meta_boxes['dashboard']['normal']['high'] = array_merge(
				array( $widget_id => $widget ),
				$wp_meta_boxes['dashboard']['normal']['high']
			);
		}

	}

	/**
	 * Render the WP Dashboard widget content.
	 */
	public function render_dashboard_widget() {
		$stats   = self::get_dashboard_stats();
		$log_url = admin_url( 'admin.php?page=sendforce-mail-relay&tab=email-log' );
		?>
		<div class="smw-widget">
			<!-- Range selector -->
			<div class="smw-header">
				<div class="smw-legend">
					<span class="smw-dot smw-dot-sent"></span><?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?>
					<span class="smw-dot smw-dot-failed" style="margin-left:12px;"></span><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?>
				</div>
				<select id="smw-range" class="smw-range-select">
					<option value="7"><?php esc_html_e( 'Last 7 Days', 'sendforce-mail-relay' ); ?></option>
					<option value="30"><?php esc_html_e( 'Last 30 Days', 'sendforce-mail-relay' ); ?></option>
					<option value="all"><?php esc_html_e( 'Last 90 Days', 'sendforce-mail-relay' ); ?></option>
				</select>
			</div>

			<!-- Chart canvas -->
			<div class="smw-chart-wrap">
				<canvas id="smw-chart" height="160"></canvas>
				<div class="smw-chart-loader" id="smw-loader">
					<span class="smw-spinner"></span>
				</div>
			</div>

			<!-- Footer stats -->
			<div class="smw-footer">
				<div class="smw-stat">
					<span class="smw-stat-num" id="smw-total"><?php echo esc_html( $stats['total_sent'] + $stats['total_failed'] ); ?></span>
					<span class="smw-stat-lbl"><?php esc_html_e( 'Total', 'sendforce-mail-relay' ); ?></span>
				</div>
				<div class="smw-stat-divider"></div>
				<div class="smw-stat">
					<span class="smw-stat-num smw-num-sent" id="smw-sent"><?php echo esc_html( $stats['total_sent'] ); ?></span>
					<span class="smw-stat-lbl"><?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?></span>
				</div>
				<div class="smw-stat-divider"></div>
				<div class="smw-stat">
					<span class="smw-stat-num smw-num-failed" id="smw-failed"><?php echo esc_html( $stats['total_failed'] ); ?></span>
					<span class="smw-stat-lbl"><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?></span>
				</div>
				<div class="smw-stat-divider"></div>
				<div class="smw-stat">
					<span class="smw-stat-num smw-num-rate" id="smw-rate"><?php echo esc_html( $stats['success_rate'] ); ?>%</span>
					<span class="smw-stat-lbl"><?php esc_html_e( 'Success', 'sendforce-mail-relay' ); ?></span>
				</div>
			</div>

			<!-- View logs link -->
			<div class="smw-actions">
				<a href="<?php echo esc_url( $log_url ); ?>" class="smw-view-link">
					<?php esc_html_e( 'View Email Logs', 'sendforce-mail-relay' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: return chart data for the given date range.
	 */
	public function ajax_chart_data() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$range = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : '7';

		if ( 'all' === $range ) {
			$days = 90;
		} elseif ( '30' === $range ) {
			$days = 30;
		} else {
			$days = 7;
		}

		$daily  = self::get_daily_stats( $days );
		$labels = array();
		$sent   = array();
		$failed = array();

		foreach ( $daily as $day ) {
			$labels[] = $day['label'];
			$sent[]   = $day['sent'];
			$failed[] = $day['failed'];
		}

		$total_sent   = array_sum( $sent );
		$total_failed = array_sum( $failed );

		wp_send_json_success(
			array(
				'labels'       => $labels,
				'sent'         => $sent,
				'failed'       => $failed,
				'total'        => $total_sent + $total_failed,
				'total_sent'   => $total_sent,
				'total_failed' => $total_failed,
			)
		);
	}

	/**
	 * Get system info for dashboard.
	 *
	 * @return array
	 */
	public static function get_system_info() {
		global $wpdb;

		$settings    = get_option( 'sendforce_settings', array() );
		$total_logs  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$next_queue  = wp_next_scheduled( 'sendforce_process_queue' );
		$next_cleanup = wp_next_scheduled( 'sendforce_cleanup_logs' );

		// Look up the active connection for SMTP host/port/encryption.
		$connections = get_option( 'sendforce_connections', array() );
		$default_id  = $settings['default_connection'] ?? '';
		$active_conn = array();
		foreach ( $connections as $conn ) {
			if ( isset( $conn['id'] ) && $conn['id'] === $default_id ) {
				$active_conn = $conn;
				break;
			}
		}

		return array(
			'php_version'    => PHP_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'smtp_host'      => $active_conn['host'] ?? '',
			'smtp_port'      => $active_conn['port'] ?? '',
			'smtp_enc'       => strtoupper( $active_conn['encryption'] ?? 'N/A' ),
			'queue_enabled'  => ! empty( $settings['queue_enabled'] ),
			'log_retention'  => $settings['log_retention_days'] ?? 30,
			'total_logs'     => absint( $total_logs ),
			'next_queue_run' => $next_queue ? wp_date( 'M j, g:i a', $next_queue ) : __( 'Not scheduled', 'sendforce-mail-relay' ),
			'next_cleanup'   => $next_cleanup ? wp_date( 'M j, g:i a', $next_cleanup ) : __( 'Not scheduled', 'sendforce-mail-relay' ),
		);
	}

	/**
	 * AJAX: Send test email using wp_mail with proper error capture.
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

		if ( ! is_email( $to ) ) {
			wp_send_json_error( __( 'Please enter a valid email address.', 'sendforce-mail-relay' ) );
		}

		// Block test in simulation mode.
		$sim_settings = get_option( 'sendforce_settings', array() );
		if ( ! empty( $sim_settings['simulation_mode'] ) ) {
			wp_send_json_error( __( 'Simulation mode is active — no emails will be sent. Disable simulation mode in Settings to send test emails.', 'sendforce-mail-relay' ) );
		}

		// Check if mailer is configured.
		$active          = $this->get_active_connection_config();
		$provider        = $active['provider'] ?? 'other';
		$connection_type = $active['connection_type'] ?? 'smtp';

		if ( 'phpmail' === $provider ) {
			// PHP Mail — no config required. Allow test to proceed.
		} elseif ( 'api' === $connection_type && 'other' !== $provider ) {
			// API mode — check for API key. Distinguish "never entered" from
			// "stored but unreadable": telling someone their key is missing
			// when it is actually present-but-undecryptable sends them looking
			// in the wrong place entirely.
			SendForce_Mailer::$last_decrypt_failed = false;
			$decrypted_api_key                     = SendForce_Mailer::decrypt_password( $active['api_key'] ?? '' );
			if ( SendForce_Mailer::$last_decrypt_failed ) {
				wp_send_json_error( __( 'Your saved API key could not be decrypted. This happens when a site\'s security keys/salts are regenerated — by a security plugin, a host migration or a site clone — which invalidates previously encrypted credentials. Please re-enter your API key in Settings and save again.', 'sendforce-mail-relay' ) );
			}
			if ( '' === (string) $decrypted_api_key ) {
				wp_send_json_error(
					sprintf(
						/* translators: %s: Provider name */
						__( 'API key is not configured. Please go to Settings, add a connection for %s with the API tab, and enter your API key.', 'sendforce-mail-relay' ),
						ucfirst( $provider )
					)
				);
			}
		} else {
			// SMTP mode — check for host.
			if ( empty( $active['host'] ) ) {
				wp_send_json_error( __( 'No email connection is configured. Please go to Settings > Active Email Connections and add a connection first.', 'sendforce-mail-relay' ) );
			}

			// Verify password decryption works. '' === comparison rather than
			// empty(), which would also flag the literal password "0".
			SendForce_Mailer::$last_decrypt_failed = false;
			$decrypted_pass                        = SendForce_Mailer::decrypt_password( $active['password'] ?? '' );
			if ( SendForce_Mailer::$last_decrypt_failed ) {
				wp_send_json_error( __( 'Your saved SMTP password could not be decrypted. This happens when a site\'s security keys/salts are regenerated — by a security plugin, a host migration or a site clone — which invalidates previously encrypted credentials. Please re-enter your password in Settings and save again.', 'sendforce-mail-relay' ) );
			}
			if ( ! empty( $active['auth'] ) && '' === (string) $decrypted_pass ) {
				wp_send_json_error( __( 'No SMTP password is saved for this connection. Please enter your password in Settings and save again.', 'sendforce-mail-relay' ) );
			}
		}

		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'SendForce Mail Relay Test Email from %s', 'sendforce-mail-relay' ),
			get_bloginfo( 'name' )
		);

		$message  = __( 'This is a test email sent by SendForce Mail Relay.', 'sendforce-mail-relay' ) . "\r\n\r\n";
		$message .= __( 'If you are reading this, your SMTP configuration is working correctly.', 'sendforce-mail-relay' ) . "\r\n\r\n";
		$message .= sprintf(
			/* translators: %s: Current date/time */
			__( 'Sent at: %s', 'sendforce-mail-relay' ),
			current_time( 'mysql' )
		);

		// Capture SMTP debug log via phpmailer_init.
		$debug_log = '';

		$debug_callback = function ( $phpmailer ) use ( &$debug_log ) {
			$phpmailer->SMTPDebug   = 2; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Debugoutput = function ( $str ) use ( &$debug_log ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$debug_log .= trim( $str ) . "\n";
			};
		};

		add_action( 'phpmailer_init', $debug_callback, 99999 );

		// Capture wp_mail_failed error.
		$mail_error = '';
		$fail_callback = function ( $wp_error ) use ( &$mail_error ) {
			$mail_error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $fail_callback );

		// Bypass the queue for test sends — the user clicked "Send Test" and
		// expects immediate feedback. With queue mode on, intercept_mail
		// would queue the test and return true (looks successful), but the
		// actual delivery wouldn't happen until the next cron run, which is
		// misleading. The one-shot bypass flag tells the queue interceptor
		// to skip this call. It auto-resets after the first wp_mail() call,
		// so we don't need to remember to clear it.
		if ( class_exists( 'SendForce_Queue' ) ) {
			SendForce_Queue::$bypass_once = true;
		}

		// Send via wp_mail so our phpmailer_init hook configures SMTP.
		$sent = wp_mail( $to, $subject, $message );

		remove_action( 'phpmailer_init', $debug_callback, 99999 );
		remove_action( 'wp_mail_failed', $fail_callback );

		if ( $sent ) {
			// Was the email actually delivered via the fallback connection?
			$used_fallback   = ! empty( SendForce_Mailer::$last_send_used_fallback );
			$fallback_label  = SendForce_Mailer::$last_fallback_label;

			if ( $used_fallback ) {
				// Primary failed but fallback succeeded — show that explicitly.
				$msg = sprintf(
					/* translators: 1: primary provider name, 2: fallback connection label */
					__( 'Test email sent via the fallback connection (%2$s). The primary connection (%1$s) failed; the fallback recovered the send. Check your inbox and spam folder.', 'sendforce-mail-relay' ),
					ucfirst( $provider ),
					$fallback_label ? $fallback_label : __( 'fallback', 'sendforce-mail-relay' )
				);
				if ( ! empty( $mail_error ) ) {
					// Defense-in-depth: provider error messages are echoed back
					// from third-party APIs and could in theory contain HTML.
					// The client renders this via .text() so XSS is already
					// blocked, but stripping tags here keeps the log/UI clean.
					$msg .= "\n\n" . __( 'Primary failure reason:', 'sendforce-mail-relay' ) . "\n" . wp_strip_all_tags( $mail_error );
				}
			} else {
				$msg = __( 'Test email sent! Check your inbox and spam folder.', 'sendforce-mail-relay' );

				// SMTP-specific sanity checks. Skip them entirely when the
				// active connection is API or PHP-Mail (no SMTP debug expected).
				if ( 'api' !== $connection_type && 'phpmail' !== $provider ) {
					if ( empty( $debug_log ) ) {
						$msg .= "\n\n" . __( 'WARNING: No SMTP log was captured. Your email may have been sent via PHP mail() instead of SMTP. Check if another plugin is overriding email settings.', 'sendforce-mail-relay' );
					} elseif ( false === stripos( $debug_log, 'SERVER -> CLIENT' ) && false === stripos( $debug_log, 'SERVER->CLIENT' ) ) {
						$msg .= "\n\n" . __( 'WARNING: SMTP connection may not have completed properly.', 'sendforce-mail-relay' );
					} else {
						if ( false !== stripos( $debug_log, '235' ) ) {
							$msg .= "\n" . __( 'SMTP Authentication: OK', 'sendforce-mail-relay' );
						}
						if ( preg_match( '/250.*(?:OK|queued|Accepted|id=)/i', $debug_log ) ) {
							$msg .= "\n" . __( 'SMTP Server: Message accepted for delivery', 'sendforce-mail-relay' );
						}
					}
				}
			}

			// Append truncated SMTP log when present (helpful even on fallback path).
			if ( ! empty( $debug_log ) ) {
				$msg .= "\n\nSMTP Debug Log:" . substr( $debug_log, 0, 1200 );
			}

			wp_send_json_success( $msg );
		} else {
			$msg = ! empty( $mail_error ) ? wp_strip_all_tags( $mail_error ) : __( 'wp_mail() returned false.', 'sendforce-mail-relay' );

			if ( ! empty( $debug_log ) ) {
				$msg .= "\n\nSMTP Debug Log:" . substr( $debug_log, 0, 1200 );
			}

			wp_send_json_error( $msg );
		}
	}

	/**
	 * AJAX: Get provider configuration.
	 */
	public function ajax_get_provider() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$provider_key = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$provider     = SendForce_Providers::get_provider( $provider_key );

		if ( $provider ) {
			wp_send_json_success( $provider );
		} else {
			wp_send_json_error( __( 'Provider not found.', 'sendforce-mail-relay' ) );
		}
	}

	/**
	 * AJAX: View log details.
	 */
	public function ajax_view_log() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$log    = SendForce_Logger::get_log( $log_id );

		if ( $log ) {
			$debug_pretty  = '';
			$fallback_used = false;
			if ( ! empty( $log->debug_data ) ) {
				$decoded = json_decode( $log->debug_data, true );
				if ( is_array( $decoded ) ) {
					$fallback_used = ! empty( $decoded['fallback_used'] );
					$debug_pretty  = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				} else {
					$debug_pretty = (string) $log->debug_data;
				}
			}

			// Build the "Copy diagnostic snapshot" payload (1.0.30+) — a single
			// pre-formatted text blob the user can paste into a support ticket
			// without having to assemble fields by hand. Secrets in headers /
			// debug_data are already redacted by SendForce_API_Mailer::make_request().
			$snapshot  = "### SendForce Mail Relay — Email Log Diagnostic ###\n";
			$snapshot .= 'plugin.version: ' . SENDFORCE_VERSION . "\n";
			$snapshot .= 'env.wordpress:  ' . get_bloginfo( 'version' ) . "\n";
			$snapshot .= 'env.php:        ' . PHP_VERSION . "\n";
			$snapshot .= "---\n";
			$snapshot .= 'log_id:         ' . (int) $log->id . "\n";
			$snapshot .= 'created_at:     ' . $log->created_at . "\n";
			$snapshot .= 'status:         ' . $log->status . "\n";
			if ( ! empty( $log->provider ) ) {
				$snapshot .= 'provider:       ' . $log->provider . "\n";
			}
			$snapshot .= 'to:             ' . $log->to_email . "\n";
			$snapshot .= 'subject:        ' . $log->subject . "\n";
			if ( ! empty( $log->error ) ) {
				$snapshot .= "error:\n  " . str_replace( "\n", "\n  ", trim( $log->error ) ) . "\n";
			}
			if ( ! empty( $log->headers ) ) {
				$snapshot .= "headers:\n  " . str_replace( "\n", "\n  ", trim( $log->headers ) ) . "\n";
			}
			if ( ! empty( $log->attachments ) ) {
				$snapshot .= 'attachments:    ' . $log->attachments . "\n";
			}
			if ( ! empty( $debug_pretty ) ) {
				$snapshot .= "debug_data:\n" . $debug_pretty . "\n";
			}
			$snapshot .= "---\n";

			wp_send_json_success(
				array(
					'to'            => esc_html( $log->to_email ),
					'subject'       => esc_html( $log->subject ),
					'message'       => esc_html( $log->message ),
					'headers'       => esc_html( $log->headers ),
					'attachments'   => esc_html( $log->attachments ),
					'status'        => esc_html( $log->status ),
					'error'         => esc_html( $log->error ),
					'created_at'    => esc_html( $log->created_at ),
					// Sent raw — the client renders this via .text() which encodes safely.
					// Pre-escaping with esc_html() would convert quotes to &quot; and corrupt the JSON view.
					'debug_data'    => $debug_pretty,
					'fallback_used' => (bool) $fallback_used,
					'snapshot'      => $snapshot,
				)
			);
		} else {
			wp_send_json_error( __( 'Log entry not found.', 'sendforce-mail-relay' ) );
		}
	}

	/**
	 * Sanitize alert settings before saving.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized.
	 */
	public function sanitize_alert_settings( $input ) {
		$sanitized = array();

		$sanitized['summary_enabled'] = ! empty( $input['summary_enabled'] );
		$sanitized['summary_emails']  = sanitize_textarea_field( $input['summary_emails'] ?? '' );

		$allowed_days         = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
		$sanitized['summary_days'] = array();

		if ( ! empty( $input['summary_days'] ) && is_array( $input['summary_days'] ) ) {
			foreach ( $input['summary_days'] as $day ) {
				if ( in_array( $day, $allowed_days, true ) ) {
					$sanitized['summary_days'][] = $day;
				}
			}
		}

		// Channel settings: prefer the incoming value (AJAX toggle / save_channel
		// has already prepared `$input['channels']` with the up-to-date state).
		// When the Summary Email form posts, it does NOT include `channels`, so
		// we fall back to whatever's already in the DB so we don't wipe them.
		if ( isset( $input['channels'] ) && is_array( $input['channels'] ) ) {
			$sanitized['channels'] = $input['channels'];
		} else {
			$existing              = get_option( 'sendforce_alerts', array() );
			$sanitized['channels'] = $existing['channels'] ?? array();
		}

		return $sanitized;
	}

	/**
	 * AJAX: Save channel configuration.
	 */
	public function ajax_save_channel() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';
		$allowed = array( 'telegram', 'slack', 'discord', 'pushover', 'teams', 'google_chat', 'webhook' );

		if ( ! in_array( $channel, $allowed, true ) ) {
			wp_send_json_error( __( 'Invalid channel.', 'sendforce-mail-relay' ) );
		}

		$data = array();

		// Channel secrets are NOT run through sanitize_text_field(): it strips
		// tags, removes %xx octets and collapses whitespace, silently corrupting
		// any token containing < > or %. Telegram and Pushover issue tokens from
		// a restricted character set today, so nothing is corrupted yet, but this
		// keeps them consistent with the connection secrets in ajax_save_conn().
		// They are echoed back to the page only as JSON, escaped there with
		// JSON_HEX_TAG (see admin/partials/tabs/alerts-tab.php), never as raw
		// HTML. Trim guards against the trailing newline users pick up when
		// copy-pasting tokens.
		switch ( $channel ) {
			case 'telegram':
				$data['bot_token'] = trim( (string) wp_unslash( $_POST['bot_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data['chat_id']   = sanitize_text_field( wp_unslash( $_POST['chat_id'] ?? '' ) );
				break;
			case 'slack':
			case 'discord':
			case 'teams':
			case 'google_chat':
			case 'webhook':
				$data['webhook_url'] = esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) );
				break;
			case 'pushover':
				$data['user_key']  = trim( (string) wp_unslash( $_POST['user_key'] ?? '' ) );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data['api_token'] = trim( (string) wp_unslash( $_POST['api_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				break;
		}

		SendForce_Alerts::save_channel_settings( $channel, $data );

		wp_send_json_success( __( 'Channel settings saved.', 'sendforce-mail-relay' ) );
	}

	/**
	 * AJAX: Toggle channel enabled state.
	 */
	public function ajax_toggle_channel() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';
		$allowed = array( 'telegram', 'slack', 'discord', 'pushover', 'teams', 'google_chat', 'webhook' );

		if ( ! in_array( $channel, $allowed, true ) ) {
			wp_send_json_error( __( 'Invalid channel.', 'sendforce-mail-relay' ) );
		}

		$enabled = ! empty( $_POST['enabled'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );

		SendForce_Alerts::save_channel_settings( $channel, array( 'enabled' => $enabled ) );

		wp_send_json_success(
			array(
				'enabled' => $enabled,
				'message' => $enabled
					? __( 'Channel enabled.', 'sendforce-mail-relay' )
					: __( 'Channel disabled.', 'sendforce-mail-relay' ),
			)
		);
	}

	/**
	 * AJAX: Send a test alert via a channel.
	 */
	public function ajax_send_test_alert() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';
		$allowed = array( 'telegram', 'slack', 'discord', 'pushover', 'teams', 'google_chat', 'webhook' );

		if ( ! in_array( $channel, $allowed, true ) ) {
			wp_send_json_error( __( 'Invalid channel.', 'sendforce-mail-relay' ) );
		}

		// Build channel settings from live POST values (what the user has in the modal right now).
		// Fall back to saved DB values for any field not posted.
		$saved_settings   = SendForce_Alerts::get_alerts_settings();
		$saved_channel    = $saved_settings['channels'][ $channel ] ?? array();
		$channel_settings = array();

		// Secrets are unslashed and trimmed but not sanitized, for the reasons
		// given in ajax_save_channel(). A value corrupted here would make the
		// test fail against credentials that actually work.
		switch ( $channel ) {
			case 'telegram':
				$channel_settings['bot_token'] = isset( $_POST['bot_token'] ) && '' !== $_POST['bot_token']
					? trim( (string) wp_unslash( $_POST['bot_token'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					: ( $saved_channel['bot_token'] ?? '' );
				$channel_settings['chat_id']   = isset( $_POST['chat_id'] ) && '' !== $_POST['chat_id']
					? sanitize_text_field( wp_unslash( $_POST['chat_id'] ) )
					: ( $saved_channel['chat_id'] ?? '' );
				break;
			case 'slack':
			case 'discord':
			case 'teams':
			case 'google_chat':
			case 'webhook':
				$channel_settings['webhook_url'] = isset( $_POST['webhook_url'] ) && '' !== $_POST['webhook_url']
					? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) )
					: ( $saved_channel['webhook_url'] ?? '' );
				break;
			case 'pushover':
				$channel_settings['user_key']  = isset( $_POST['user_key'] ) && '' !== $_POST['user_key']
					? trim( (string) wp_unslash( $_POST['user_key'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					: ( $saved_channel['user_key'] ?? '' );
				$channel_settings['api_token'] = isset( $_POST['api_token'] ) && '' !== $_POST['api_token']
					? trim( (string) wp_unslash( $_POST['api_token'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					: ( $saved_channel['api_token'] ?? '' );
				break;
		}

		// Now validate that the resolved credentials are not empty.
		switch ( $channel ) {
			case 'telegram':
				if ( empty( $channel_settings['bot_token'] ) || empty( $channel_settings['chat_id'] ) ) {
					wp_send_json_error( __( 'Please enter your Bot Token and Chat ID before sending a test.', 'sendforce-mail-relay' ) );
				}
				break;
			case 'slack':
			case 'discord':
			case 'teams':
			case 'google_chat':
			case 'webhook':
				if ( empty( $channel_settings['webhook_url'] ) ) {
					wp_send_json_error( __( 'Please enter a Webhook URL before sending a test.', 'sendforce-mail-relay' ) );
				}
				break;
			case 'pushover':
				if ( empty( $channel_settings['user_key'] ) || empty( $channel_settings['api_token'] ) ) {
					wp_send_json_error( __( 'Please enter your User Key and API Token before sending a test.', 'sendforce-mail-relay' ) );
				}
				break;
		}

		$test_message = sprintf(
			/* translators: 1: site name, 2: channel name */
			__( '[SendForce Mail Relay] Test notification from %1$s via %2$s — your alert channel is working correctly!', 'sendforce-mail-relay' ),
			get_bloginfo( 'name' ),
			ucfirst( str_replace( '_', ' ', $channel ) )
		);

		$alerts = new SendForce_Alerts();
		$sent   = false;

		switch ( $channel ) {
			case 'telegram':
				$sent = $alerts->notify_telegram( $channel_settings, $test_message );
				break;
			case 'slack':
				$sent = $alerts->notify_slack( $channel_settings, $test_message );
				break;
			case 'discord':
				$sent = $alerts->notify_discord( $channel_settings, $test_message );
				break;
			case 'pushover':
				$sent = $alerts->notify_pushover( $channel_settings, $test_message );
				break;
			case 'teams':
				$sent = $alerts->notify_teams( $channel_settings, $test_message );
				break;
			case 'google_chat':
				$sent = $alerts->notify_google_chat( $channel_settings, $test_message );
				break;
			case 'webhook':
				$sent = $alerts->notify_webhook( $channel_settings, $test_message );
				break;
		}

		if ( $sent ) {
			wp_send_json_success( __( 'Test alert sent successfully! Check the channel for the message.', 'sendforce-mail-relay' ) );
		} else {
			wp_send_json_error( __( 'Failed to deliver the test alert. Please double-check your credentials and try again.', 'sendforce-mail-relay' ) );
		}
	}

	/**
	 * AJAX: Save (create or update) a connection in sendforce_connections.
	 */
	public function ajax_save_connection() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$conn_id     = sanitize_text_field( wp_unslash( $_POST['conn_id'] ?? '' ) );
		$connections = get_option( 'sendforce_connections', array() );

		// A new connection's ID comes from a one-time key the form creates
		// when it opens, so the same request arriving twice (a double click,
		// a browser retry, or Save clicked again after a "Request failed"
		// that did save) updates the connection it already made instead of
		// adding a second one. Without a key (an older cached script) a
		// random ID is used, as before.
		$save_key = sanitize_key( wp_unslash( $_POST['save_key'] ?? '' ) );
		$keyed_id = strlen( $save_key ) >= 16 ? 'conn_' . substr( md5( get_current_user_id() . '|' . $save_key ), 0, 12 ) : '';
		if ( '' === $conn_id && '' !== $keyed_id ) {
			foreach ( $connections as $existing ) {
				if ( isset( $existing['id'] ) && $existing['id'] === $keyed_id ) {
					$conn_id = $keyed_id;
					break;
				}
			}
		}

		$raw_conn_type  = sanitize_text_field( wp_unslash( $_POST['connection_type'] ?? 'smtp' ) );
		$raw_encryption  = sanitize_text_field( wp_unslash( $_POST['encryption'] ?? 'tls' ) );
		$raw_mg_region   = sanitize_text_field( wp_unslash( $_POST['mailgun_region'] ?? 'us' ) );
		$raw_ses_region  = sanitize_text_field( wp_unslash( $_POST['ses_region'] ?? 'us-east-1' ) );

		// The SES Access Key ID is a credential, so it gets the same handling as
		// the password and API key below rather than sanitize_text_field(). AWS
		// issues these as 20 uppercase alphanumerics, so nothing is corrupted
		// today; this keeps every secret on the screen consistent. Unlike the
		// password it is stored in the clear and sent to the admin JS, but only
		// through wp_localize_script(), which core encodes with JSON_HEX_TAG —
		// it is never echoed into HTML.
		$new_ses_access_key = trim( (string) wp_unslash( $_POST['ses_access_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$data = array(
			'label'                => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'provider'             => sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'other' ) ),
			'connection_type'      => in_array( $raw_conn_type, array( 'smtp', 'api' ), true ) ? $raw_conn_type : 'smtp',
			'host'                 => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'port'                 => absint( $_POST['port'] ?? 587 ),
			'encryption'           => in_array( $raw_encryption, array( 'none', 'ssl', 'tls' ), true ) ? $raw_encryption : 'tls',
			'auth'                 => ! empty( $_POST['auth'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['auth'] ) ),
			'username'             => sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) ),
			'ses_region'           => $raw_ses_region,
			'ses_access_key'       => $new_ses_access_key,
			'mailgun_domain'       => sanitize_text_field( wp_unslash( $_POST['mailgun_domain'] ?? '' ) ),
			'mailgun_region'       => in_array( $raw_mg_region, array( 'us', 'eu' ), true ) ? $raw_mg_region : 'us',
			'smtpcom_channel'      => sanitize_text_field( wp_unslash( $_POST['smtpcom_channel'] ?? '' ) ),
			'socketlabs_server_id' => sanitize_text_field( wp_unslash( $_POST['socketlabs_server_id'] ?? '' ) ),
			'outlook_tenant_id'    => sanitize_text_field( wp_unslash( $_POST['outlook_tenant_id'] ?? '' ) ),
			'outlook_client_id'    => sanitize_text_field( wp_unslash( $_POST['outlook_client_id'] ?? '' ) ),
			'gmail_client_id'      => sanitize_text_field( wp_unslash( $_POST['gmail_client_id'] ?? '' ) ),
			// Max emails per minute this connection may send from the queue
			// (0 = unlimited). Clamped so a typo can't stall or flood a connection.
			'rate_limit'           => min( 10000, absint( wp_unslash( $_POST['rate_limit'] ?? 0 ) ) ),
		);

		// Secrets are NOT run through sanitize_text_field(): it strips tags,
		// removes %xx octets and collapses whitespace, silently corrupting any
		// password or API key containing < > or % before it is encrypted. These
		// values are stored encrypted and never rendered as HTML, so only the
		// slashes added by WordPress are removed. Trim guards against the
		// trailing newline users pick up when copy-pasting keys.
		$new_password = trim( (string) wp_unslash( $_POST['password'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_api_key  = trim( (string) wp_unslash( $_POST['api_key'] ?? '' ) );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Server-side guard against the most common Microsoft 365 / Gmail
		// setup mistake: pasting the Azure "Secret ID" column (a GUID) into
		// the Client Secret field instead of the "Value" column. Microsoft
		// client secret values are never GUIDs, so we hard-reject anything
		// that matches the GUID shape for OAuth providers. AADSTS7000215 is
		// the resulting Microsoft error otherwise — and users repeatedly
		// burn 30+ minutes diagnosing it.
		if (
			! empty( $new_api_key )
			&& in_array( $data['provider'] ?? '', array( 'outlook', 'gmail' ), true )
			&& preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $new_api_key )
		) {
			wp_send_json_error(
				__( 'Client Secret looks like a GUID — that is the Secret ID column from Azure, not the Value column. Microsoft client secret values are random strings (containing ~ . _ characters), never GUIDs. Go back to Azure → Certificates & secrets → New client secret → copy the VALUE column (only shown once, immediately after creation), then paste it here.', 'sendforce-mail-relay' )
			);
		}

		if ( empty( $conn_id ) ) {
			// New connection.
			$data['id']          = '' !== $keyed_id ? $keyed_id : 'conn_' . substr( md5( uniqid( '', true ) ), 0, 12 );
			$data['password']    = ! empty( $new_password ) ? SendForce_Mailer::encrypt_password( $new_password ) : '';
			$data['api_key']     = ! empty( $new_api_key ) ? SendForce_Mailer::encrypt_password( $new_api_key ) : '';
			$data['oauth_email'] = '';
			$connections[]       = $data;
		} else {
			// Update existing — preserve OAuth-only fields (oauth_email) that
			// the admin form doesn't post.
			$found = false;
			foreach ( $connections as &$conn ) {
				if ( isset( $conn['id'] ) && $conn['id'] === $conn_id ) {
					$data['id']          = $conn_id;
					$data['password']    = ! empty( $new_password ) ? SendForce_Mailer::encrypt_password( $new_password ) : ( $conn['password'] ?? '' );
					$data['api_key']     = ! empty( $new_api_key ) ? SendForce_Mailer::encrypt_password( $new_api_key ) : ( $conn['api_key'] ?? '' );
					$data['oauth_email'] = $conn['oauth_email'] ?? '';
					// Keep the importer's marker so a later "Import" doesn't
					// add this connection a second time after it was edited.
					if ( ! empty( $conn['import_key'] ) ) {
						$data['import_key']    = $conn['import_key'];
						$data['imported_from'] = $conn['imported_from'] ?? '';
					}
					$conn                = $data;
					$found               = true;
					break;
				}
			}
			unset( $conn );

			if ( ! $found ) {
				wp_send_json_error( __( 'Connection not found.', 'sendforce-mail-relay' ) );
			}
		}

		update_option( 'sendforce_connections', $connections );

		// Credentials just changed — drop the cached scan so the "cannot read
		// your credentials" notice clears immediately after a re-entered
		// password instead of lingering for up to an hour.
		delete_transient( 'sendforce_broken_credentials' );

		// Auto-set as default connection if none is configured yet.
		$settings         = get_option( 'sendforce_settings', array() );
		$auto_set_default = false;
		if ( empty( $settings['default_connection'] ) ) {
			$settings['default_connection'] = $data['id'];
			update_option( 'sendforce_settings', $settings );
			$auto_set_default = true;
		}

		$provider_info  = SendForce_Providers::get_provider( $data['provider'] );
		$provider_label = $provider_info ? $provider_info['label'] : ucfirst( $data['provider'] );

		wp_send_json_success(
			array(
				'id'                => $data['id'],
				'label'             => $data['label'],
				'provider'          => $data['provider'],
				'provider_label'    => $provider_label,
				'connection_type'   => strtoupper( $data['connection_type'] ),
				'has_password'      => ! empty( $data['password'] ),
				'has_api_key'       => ! empty( $data['api_key'] ),
				'auto_set_default'  => $auto_set_default,
			)
		);
	}

	/**
	 * AJAX: Delete a connection from sendforce_connections.
	 */
	public function ajax_delete_connection() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$conn_id     = sanitize_text_field( wp_unslash( $_POST['conn_id'] ?? '' ) );
		$connections = get_option( 'sendforce_connections', array() );
		$connections = array_values(
			array_filter(
				$connections,
				function ( $c ) use ( $conn_id ) {
					return isset( $c['id'] ) && $c['id'] !== $conn_id;
				}
			)
		);
		update_option( 'sendforce_connections', $connections );
		delete_transient( 'sendforce_broken_credentials' );

		// If the deleted connection was set as default or fallback, clear it.
		$settings = get_option( 'sendforce_settings', array() );
		$changed  = false;
		if ( ( $settings['default_connection'] ?? '' ) === $conn_id ) {
			$settings['default_connection'] = '';
			$changed                        = true;
		}
		if ( ( $settings['fallback_connection'] ?? '' ) === $conn_id ) {
			$settings['fallback_connection'] = '';
			$changed                         = true;
		}
		if ( $changed ) {
			update_option( 'sendforce_settings', $settings );
		}

		wp_send_json_success( __( 'Connection deleted.', 'sendforce-mail-relay' ) );
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=sendforce-mail-relay' ),
			__( 'Settings', 'sendforce-mail-relay' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * AJAX: return system / debug info JSON for the "Copy debug info" button.
	 */
	public function ajax_sysinfo() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}
		wp_send_json_success(
			array(
				'json' => SendForce_Sysinfo::collect(),
				'text' => SendForce_Sysinfo::as_text(),
			)
		);
	}

	/**
	 * Stream the email log as CSV or JSON. Honors current filter parameters
	 * (status, search, date range) so admins can export exactly what they see.
	 */
	public function handle_export_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'sendforce-mail-relay' ), 403 );
		}
		check_admin_referer( 'sendforce_export_logs' );

		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		if ( ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			$format = 'csv';
		}

		$args = array(
			'per_page'  => 10000,
			'page'      => 1,
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$result   = SendForce_Logger::get_logs( $args );
		$items    = $result['items'];
		$filename = 'sendforce-logs-' . gmdate( 'Y-m-d-His' );

		nocache_headers();

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
			echo wp_json_encode( $items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body, must not be HTML-escaped.
			exit;
		}

		// CSV.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );

		$columns = array( 'id', 'created_at', 'status', 'to_email', 'subject', 'error', 'headers', 'attachments' );
		$out     = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming download to client; WP_Filesystem is not appropriate.
		fputcsv( $out, $columns, ',', '"', '\\' );
		foreach ( $items as $row ) {
			$line = array();
			foreach ( $columns as $col ) {
				$value  = isset( $row->$col ) ? (string) $row->$col : '';
				$line[] = self::csv_safe( $value );
			}
			fputcsv( $out, $line, ',', '"', '\\' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Pair with fopen above.
		exit;
	}

	/**
	 * Defuse spreadsheet formula injection by prefixing cells whose first
	 * character would otherwise be interpreted as a formula by Excel/Sheets.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_safe( $value ) {
		if ( '' === $value ) {
			return $value;
		}
		$first = $value[0];
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/* ---------------------------------------------------------------- */
	/* Smart Routing — AJAX handlers                                     */
	/* ---------------------------------------------------------------- */

	/**
	 * Common guard for all routing AJAX endpoints.
	 */
	private function routing_ajax_guard() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}
	}

	/**
	 * AJAX: fetch a single route for editing.
	 */
	public function ajax_get_route() {
		$this->routing_ajax_guard();
		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sendforce_routes WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! $row ) {
			wp_send_json_error( __( 'Route not found.', 'sendforce-mail-relay' ) );
		}
		$row['conditions'] = json_decode( $row['conditions'], true );
		wp_send_json_success( $row );
	}

	/**
	 * AJAX: create or update a routing rule.
	 */
	public function ajax_save_route() {
		$this->routing_ajax_guard();
		global $wpdb;
		$id           = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$label        = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$enabled      = ! empty( $_POST['enabled'] ) ? 1 : 0;
		$action_type  = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : 'connection';
		$action_value = isset( $_POST['action_value'] ) ? sanitize_text_field( wp_unslash( $_POST['action_value'] ) ) : '';

		$action_type = in_array( $action_type, array( 'connection', 'roundrobin' ), true ) ? $action_type : 'connection';

		// Sanitize conditions array.
		$raw_conds  = isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$conditions = array();
		if ( is_array( $raw_conds ) ) {
			$allowed_types = array( 'to_domain', 'to_pattern', 'from_email', 'from_domain', 'source_plugin', 'subject_contains', 'time_between', 'weekday' );
			$allowed_ops   = array( 'equals', 'not_equals', 'contains', 'starts_with', 'ends_with' );
			foreach ( $raw_conds as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$ct = isset( $c['type'] )  ? sanitize_key( $c['type'] )  : '';
				$co = isset( $c['op'] )    ? sanitize_key( $c['op'] )    : 'equals';
				$cv = isset( $c['value'] ) ? sanitize_text_field( $c['value'] ) : '';
				if ( ! in_array( $ct, $allowed_types, true ) ) {
					continue;
				}
				if ( ! in_array( $co, $allowed_ops, true ) ) {
					$co = 'equals';
				}
				// Skip blank-value conditions — they would never match anything
				// useful and would cause "always false" rules. The frontend
				// already rejects these, this is defense in depth.
				if ( '' === trim( $cv ) ) {
					continue;
				}
				$conditions[] = array( 'type' => $ct, 'op' => $co, 'value' => $cv );
			}
		}

		if ( empty( $label ) ) {
			$label = __( 'Untitled rule', 'sendforce-mail-relay' );
		}
		if ( empty( $conditions ) ) {
			wp_send_json_error( __( 'Add at least one condition.', 'sendforce-mail-relay' ) );
		}
		if ( empty( $action_value ) ) {
			wp_send_json_error( __( 'Pick a target connection.', 'sendforce-mail-relay' ) );
		}

		$data = array(
			'label'        => $label,
			'enabled'      => $enabled,
			'conditions'   => wp_json_encode( $conditions ),
			'action_type'  => $action_type,
			'action_value' => $action_value,
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $wpdb->prefix . 'sendforce_routes', $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			// Compute next priority (append).
			$next_priority = (int) $wpdb->get_var( "SELECT COALESCE(MAX(priority), 0) + 1 FROM {$wpdb->prefix}sendforce_routes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$data['priority']   = $next_priority;
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $wpdb->prefix . 'sendforce_routes', $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		wp_send_json_success( array( 'reload' => true ) );
	}

	/**
	 * AJAX: delete a routing rule.
	 */
	public function ajax_delete_route() {
		$this->routing_ajax_guard();
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id ) {
			$wpdb->delete( $wpdb->prefix . 'sendforce_routes', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: persist a new ordering of routes.
	 */
	public function ajax_reorder_routes() {
		$this->routing_ajax_guard();
		global $wpdb;
		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! is_array( $order ) ) {
			wp_send_json_error();
		}
		$priority = 1;
		foreach ( $order as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$wpdb->update( $wpdb->prefix . 'sendforce_routes', array( 'priority' => $priority ), array( 'id' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$priority++;
			}
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: flip the master Enable Smart Routing switch.
	 */
	public function ajax_toggle_routing() {
		$this->routing_ajax_guard();
		$enabled = ! empty( $_POST['enabled'] ) ? 1 : 0;
		update_option( 'sendforce_routing_enabled', $enabled );
		wp_send_json_success( array( 'enabled' => (bool) $enabled ) );
	}

	/**
	 * AJAX: run the log auto-clean immediately using the configured retention.
	 */
	public function ajax_cleanup_logs_now() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'sendforce-mail-relay' ) ) );
			return;
		}

		$settings       = get_option( 'sendforce_settings', array() );
		$retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 30;

		if ( 0 === $retention_days ) {
			wp_send_json_error(
				array(
					'message' => __( 'Auto-clean is disabled (retention set to "Never Delete"). Change it in Settings → Delete Logs.', 'sendforce-mail-relay' ),
				)
			);
			return;
		}

		$logger  = new SendForce_Logger();
		$deleted = $logger->cleanup_old_logs();

		wp_send_json_success(
			array(
				'deleted' => $deleted,
				'message' => sprintf(
					/* translators: %d: number of deleted log rows */
					_n( 'Removed %d old log entry.', 'Removed %d old log entries.', $deleted, 'sendforce-mail-relay' ),
					$deleted
				),
			)
		);
	}

	/**
	 * AJAX: trigger the email queue processor on demand. Same handler the
	 * cron event runs, but invoked synchronously from a click on the Queue
	 * tab so users don't have to wait for the next WP-Cron run.
	 */
	public function ajax_process_queue_now() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'sendforce-mail-relay' ) ) );
			return;
		}

		$settings = get_option( 'sendforce_settings', array() );
		if ( empty( $settings['queue_enabled'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Email queue is disabled. Enable it in Settings → Email Queue first.', 'sendforce-mail-relay' ),
				)
			);
			return;
		}

		// Run the same processor the cron event uses. process_queue() already
		// has its own re-entrancy guard via self::$processing, so this is
		// safe to call while a cron run might also be active.
		$queue = new SendForce_Queue();

		$before_stats = SendForce_Queue::get_stats();
		$queue->process_queue();
		$after_stats  = SendForce_Queue::get_stats();

		$sent_count   = max( 0, $after_stats['sent']   - $before_stats['sent'] );
		$failed_count = max( 0, $after_stats['failed'] - $before_stats['failed'] );

		wp_send_json_success(
			array(
				'sent'   => $sent_count,
				'failed' => $failed_count,
				'stats'  => $after_stats,
				'message' => sprintf(
					/* translators: 1: sent count, 2: failed count */
					__( 'Queue processed. Sent: %1$d. Newly failed: %2$d.', 'sendforce-mail-relay' ),
					$sent_count,
					$failed_count
				),
			)
		);
	}

	/**
	 * AJAX: dry-run the routing engine against a sample email.
	 */
	public function ajax_test_route() {
		$this->routing_ajax_guard();
		$to      = isset( $_POST['to'] )      ? sanitize_email( wp_unslash( $_POST['to'] ) )      : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$source  = isset( $_POST['source'] )  ? sanitize_key( wp_unslash( $_POST['source'] ) )    : '';
		$from    = isset( $_POST['from'] )    ? sanitize_email( wp_unslash( $_POST['from'] ) )    : '';

		// Build a context the live router would build for this synthetic input,
		// then call the SAME rule_matches() the production mailer uses. This
		// guarantees the Test tool and the live router never drift.
		$context = SendForce_Router::build_test_context(
			array(
				'to'            => $to,
				'subject'       => $subject,
				'from'          => $from,
				'source_plugin' => $source ?: 'wordpress-core',
			)
		);

		$matched = null;
		foreach ( SendForce_Router::get_active_rules() as $rule ) {
			if ( SendForce_Router::rule_matches( $rule, $context ) ) {
				$matched = $rule;
				break;
			}
		}

		if ( $matched ) {
			$conns = (array) get_option( 'sendforce_connections', array() );
			$conn_label = '(missing)';
			foreach ( $conns as $c ) {
				if ( ( $c['id'] ?? '' ) === $matched['action_value'] ) {
					$conn_label = $c['label'] ?? $c['provider'] ?? '(unnamed)';
					break;
				}
			}
			wp_send_json_success( array(
				'matched'    => true,
				'rule_label' => $matched['label'],
				'connection' => $conn_label,
			) );
		}

		wp_send_json_success( array( 'matched' => false ) );
	}
}
