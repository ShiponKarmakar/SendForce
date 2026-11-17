<?php
/**
 * Main dashboard shell template with top navigation.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sendforce_allowed_tabs = array( 'dashboard', 'settings', 'email-log', 'queue', 'email-test', 'alerts', 'routing', 'upcoming', 'changelog', 'setup' );
if ( ! in_array( $sendforce_current_tab, $sendforce_allowed_tabs, true ) ) {
	$sendforce_current_tab = 'dashboard';
}

// Tabs are grouped by purpose — Overview | Setup | Activity | What's New.
// A 'group_start' flag renders a thin divider before that item so the bar
// reads as organized clusters rather than one flat list.
$sendforce_nav_items = array(
	'dashboard'  => array(
		'label' => __( 'Dashboard', 'sendforce-mail-relay' ),
		'icon'  => 'dashicons-dashboard',
	),
	'settings'   => array(
		'label'       => __( 'Settings', 'sendforce-mail-relay' ),
		'icon'        => 'dashicons-admin-generic',
		'group_start' => true,
	),
	'routing'    => array(
		'label' => __( 'Routing', 'sendforce-mail-relay' ),
		'icon'  => 'dashicons-randomize',
	),
	'email-test' => array(
		'label' => __( 'Email Test', 'sendforce-mail-relay' ),
		'icon'  => 'dashicons-email',
	),
	'email-log'  => array(
		'label'       => __( 'Email Log', 'sendforce-mail-relay' ),
		'icon'        => 'dashicons-list-view',
		'group_start' => true,
	),
	'queue'      => array(
		'label' => __( 'Queue', 'sendforce-mail-relay' ),
		'icon'  => 'dashicons-update',
	),
	'alerts'     => array(
		'label' => __( 'Alerts', 'sendforce-mail-relay' ),
		'icon'  => 'dashicons-bell',
	),
	'upcoming'   => array(
		'label'       => __( 'What\'s New', 'sendforce-mail-relay' ),
		'icon'        => 'dashicons-megaphone',
		'group_start' => true,
	),
);
?>
<div class="sendforce-app">
	<!-- Top Header Bar -->
	<header class="sendforce-topbar">
		<div class="sendforce-topbar-inner">
			<div class="sendforce-topbar-left">
				<div class="sendforce-logo">
					<img
						src="<?php echo esc_url( SENDFORCE_PLUGIN_URL . 'assets/images/logo.svg' ); ?>"
						alt="<?php esc_attr_e( 'SendForce Mail Relay', 'sendforce-mail-relay' ); ?>"
						class="sendforce-logo-img"
					/>
				</div>
				<nav class="sendforce-nav">
					<?php foreach ( $sendforce_nav_items as $sendforce_tab_key => $sendforce_tab_info ) : ?>
							<?php if ( ! empty( $sendforce_tab_info['group_start'] ) ) : ?>
								<span class="sendforce-nav-divider" aria-hidden="true"></span>
							<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=' . $sendforce_tab_key ) ); ?>"
						   class="sendforce-nav-item <?php echo esc_attr( $sendforce_current_tab === $sendforce_tab_key ? 'active' : '' ); ?>">
							<span class="dashicons <?php echo esc_attr( $sendforce_tab_info['icon'] ); ?>"></span>
							<span class="sendforce-nav-label"><?php echo esc_html( $sendforce_tab_info['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
			<div class="sendforce-topbar-right">
				<span class="sendforce-version"><?php echo esc_html( 'v' . SENDFORCE_VERSION ); ?></span>
			</div>
		</div>
	</header>

	<!-- Main Content -->
	<main class="sendforce-main">
		<div class="sendforce-content">
			<?php settings_errors( 'sendforce' ); ?>

			<?php
			switch ( $sendforce_current_tab ) {
				case 'settings':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/settings-tab.php';
					break;
				case 'email-log':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/log-tab.php';
					break;
				case 'queue':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/queue-tab.php';
					break;
				case 'email-test':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/email-test-tab.php';
					break;
				case 'alerts':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/alerts-tab.php';
					break;
				case 'routing':
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/routing-tab.php';
					break;
				case 'setup': // Reached from the activation redirect; not in the nav.
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/setup-tab.php';
					break;
				case 'upcoming':
				case 'changelog': // Back-compat: old direct URLs still resolve.
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/upcoming-tab.php';
					break;
default:
					include SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/dashboard-tab.php';
					break;
			}
			?>
		</div>
	</main>
</div>

<!-- Confirm delete modal -->
<div id="sendforce-confirm-modal" class="sendforce-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sendforce-confirm-title">
	<div class="sendforce-modal-overlay sendforce-confirm-overlay"></div>
	<div class="sendforce-confirm-dialog">
		<div class="sendforce-confirm-icon">
			<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<polyline points="3 6 5 6 21 6"></polyline>
				<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
				<path d="M10 11v6"></path><path d="M14 11v6"></path>
				<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
			</svg>
		</div>
		<h3 id="sendforce-confirm-title" class="sendforce-confirm-title">
			<?php esc_html_e( 'Delete Connection', 'sendforce-mail-relay' ); ?>
		</h3>
		<p class="sendforce-confirm-message">
			<?php esc_html_e( 'Are you sure you want to delete this connection? This action cannot be undone.', 'sendforce-mail-relay' ); ?>
		</p>
		<div class="sendforce-confirm-actions">
			<button type="button" id="sendforce-confirm-cancel" class="sendforce-btn sendforce-btn-ghost sendforce-btn-lg">
				<?php esc_html_e( 'Cancel', 'sendforce-mail-relay' ); ?>
			</button>
			<button type="button" id="sendforce-confirm-delete" class="sendforce-btn sendforce-btn-danger sendforce-btn-lg">
				<svg id="sendforce-confirm-btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px;">
					<polyline points="3 6 5 6 21 6"></polyline>
					<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
					<path d="M10 11v6"></path><path d="M14 11v6"></path>
					<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
				</svg>
				<span class="sendforce-confirm-btn-label"><?php esc_html_e( 'Delete', 'sendforce-mail-relay' ); ?></span>
			</button>
		</div>
	</div>
</div>

<!-- Log detail modal -->
<div id="sendforce-log-modal" class="sendforce-modal" style="display:none;">
	<div class="sendforce-modal-overlay"></div>
	<div class="sendforce-modal-content">
		<div class="sendforce-modal-header">
			<h3><?php esc_html_e( 'Email Details', 'sendforce-mail-relay' ); ?></h3>
			<div class="sendforce-modal-header-actions">
				<button type="button" id="sendforce-log-copy-snapshot" class="sendforce-btn sendforce-btn-outline sendforce-btn-xs" title="<?php esc_attr_e( 'Copy a redacted diagnostic snapshot of this log entry — useful when filing a support ticket. Includes status, error, headers and the raw API response with secrets stripped.', 'sendforce-mail-relay' ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy diagnostic snapshot', 'sendforce-mail-relay' ); ?>
				</button>
				<button type="button" class="sendforce-modal-close">&times;</button>
			</div>
		</div>
		<div class="sendforce-modal-body">
			<div class="sendforce-detail-grid">
				<div class="sendforce-detail-row">
					<span class="sendforce-detail-label"><?php esc_html_e( 'To', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value" id="sendforce-log-to"></span>
				</div>
				<div class="sendforce-detail-row">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value" id="sendforce-log-subject"></span>
				</div>
				<div class="sendforce-detail-row">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Status', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value" id="sendforce-log-status"></span>
				</div>
				<div class="sendforce-detail-row">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Date', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value" id="sendforce-log-date"></span>
				</div>
				<div class="sendforce-detail-row">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Headers', 'sendforce-mail-relay' ); ?></span>
					<pre class="sendforce-detail-value sendforce-pre" id="sendforce-log-headers"></pre>
				</div>
				<div class="sendforce-detail-row" id="sendforce-log-fallback-row" style="display:none;">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Delivery', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value">
						<span class="sendforce-conn-badge sendforce-conn-badge-fallback"><?php esc_html_e( 'Recovered via fallback connection', 'sendforce-mail-relay' ); ?></span>
					</span>
				</div>
				<div class="sendforce-detail-row" id="sendforce-log-error-row" style="display:none;">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Error', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-detail-value sendforce-text-danger" id="sendforce-log-error"></span>
				</div>
				<div class="sendforce-detail-row sendforce-detail-full">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Message', 'sendforce-mail-relay' ); ?></span>
					<div class="sendforce-message-body" id="sendforce-log-message"></div>
				</div>
				<div class="sendforce-detail-row sendforce-detail-full" id="sendforce-log-debug-row" style="display:none;">
					<span class="sendforce-detail-label"><?php esc_html_e( 'Raw API request / response', 'sendforce-mail-relay' ); ?></span>
					<pre class="sendforce-detail-value sendforce-pre" id="sendforce-log-debug" style="max-height:300px;overflow:auto;font-size:11px;"></pre>
				</div>
			</div>
		</div>
	</div>
</div>
