<?php
/**
 * Email Test tab template.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_settings    = get_option( 'sendforce_settings', array() );
$sendforce_connections = get_option( 'sendforce_connections', array() );
$sendforce_default_id  = $sendforce_settings['default_connection'] ?? '';

// Find the active connection.
$sendforce_active_conn = array();
foreach ( $sendforce_connections as $sendforce_conn ) {
	if ( isset( $sendforce_conn['id'] ) && $sendforce_conn['id'] === $sendforce_default_id ) {
		$sendforce_active_conn = $sendforce_conn;
		break;
	}
}
// Fall back to first connection if default not set but connections exist.
if ( empty( $sendforce_active_conn ) && ! empty( $sendforce_connections ) ) {
	$sendforce_active_conn = reset( $sendforce_connections );
}

$sendforce_provider        = $sendforce_active_conn['provider'] ?? 'other';
$sendforce_host            = $sendforce_active_conn['host'] ?? '';
$sendforce_connection_type = $sendforce_active_conn['connection_type'] ?? 'smtp';
$sendforce_is_configured   = ! empty( $sendforce_active_conn );
$sendforce_current_user    = wp_get_current_user();
$sendforce_default_email   = $sendforce_current_user->user_email ?? get_option( 'admin_email' );

$sendforce_provider_data = SendForce_Providers::get_provider( $sendforce_provider );
$sendforce_provider_name = $sendforce_provider_data['label'] ?? 'Unknown';
?>

<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Email Test', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'Send a test email to verify your SMTP or API configuration is working correctly.', 'sendforce-mail-relay' ); ?></p>
	</div>
</div>

<div class="sendforce-email-test-layout">

	<!-- Send Test Email Card -->
	<div class="sendforce-card sendforce-email-test-main">
		<div class="sendforce-card-header">
			<h2><?php esc_html_e( 'Send Test Email', 'sendforce-mail-relay' ); ?></h2>
		</div>
		<div class="sendforce-card-body">

			<?php if ( ! empty( $sendforce_settings['simulation_mode'] ) ) : ?>
				<?php /* Said up front, rather than only after the send is refused. */ ?>
				<div class="sendforce-notice sendforce-notice-warning">
					<span class="dashicons dashicons-hidden"></span>
					<div>
						<strong><?php esc_html_e( 'Simulation Mode is on', 'sendforce-mail-relay' ); ?></strong>
						<p><?php esc_html_e( 'Emails are written to the log but never handed to your provider, so a test send cannot reach an inbox. Turn Simulation Mode off in Settings to send for real.', 'sendforce-mail-relay' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' ) ) . '#sendforce-simulation'; ?>" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm">
							<?php esc_html_e( 'Turn off Simulation Mode', 'sendforce-mail-relay' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! $sendforce_is_configured ) : ?>
				<div class="sendforce-notice sendforce-notice-warning">
					<span class="dashicons dashicons-warning"></span>
					<div>
						<strong><?php esc_html_e( 'Not Configured', 'sendforce-mail-relay' ); ?></strong>
						<p><?php esc_html_e( 'Your SMTP settings have not been saved yet. Please configure your connection in Settings before sending a test email.', 'sendforce-mail-relay' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' ) ); ?>" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm">
							<?php esc_html_e( 'Go to Settings', 'sendforce-mail-relay' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

			<!-- Current connection summary -->
			<div class="sendforce-test-connection-summary">
				<div class="sendforce-test-conn-item">
					<span class="sendforce-test-conn-label"><?php esc_html_e( 'Provider', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-test-conn-value">
						<span class="sendforce-provider-dot sendforce-provider-dot-<?php echo esc_attr( $sendforce_is_configured ? 'ok' : 'error' ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $sendforce_provider_name ); ?>
					</span>
				</div>
				<div class="sendforce-test-conn-item">
					<span class="sendforce-test-conn-label"><?php esc_html_e( 'Mode', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-test-conn-value">
						<span class="sendforce-badge <?php echo 'api' === $sendforce_connection_type ? 'sendforce-badge-blue' : 'sendforce-badge-gray'; ?>">
							<?php echo 'api' === $sendforce_connection_type ? esc_html__( 'API', 'sendforce-mail-relay' ) : esc_html__( 'SMTP', 'sendforce-mail-relay' ); ?>
						</span>
					</span>
				</div>
				<?php if ( 'smtp' === $sendforce_connection_type && ! empty( $sendforce_host ) ) : ?>
					<div class="sendforce-test-conn-item">
						<span class="sendforce-test-conn-label"><?php esc_html_e( 'Host', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-test-conn-value sendforce-monospace"><?php echo esc_html( $sendforce_host . ':' . ( $sendforce_active_conn['port'] ?? 587 ) ); ?></span>
					</div>
					<div class="sendforce-test-conn-item">
						<span class="sendforce-test-conn-label"><?php esc_html_e( 'Encryption', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-test-conn-value sendforce-monospace"><?php echo esc_html( strtoupper( $sendforce_active_conn['encryption'] ?? 'TLS' ) ); ?></span>
					</div>
				<?php endif; ?>
				<div class="sendforce-test-conn-item">
					<span class="sendforce-test-conn-label"><?php esc_html_e( 'Status', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-test-conn-value">
						<?php if ( $sendforce_is_configured ) : ?>
							<span class="sendforce-status-pill sendforce-status-pill-green">
								<span class="sendforce-status-dot"></span>
								<?php esc_html_e( 'Configured', 'sendforce-mail-relay' ); ?>
							</span>
						<?php else : ?>
							<span class="sendforce-status-pill sendforce-status-pill-red">
								<span class="sendforce-status-dot"></span>
								<?php esc_html_e( 'Not Configured', 'sendforce-mail-relay' ); ?>
							</span>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<!-- Test form -->
			<div class="sendforce-test-form">
				<div class="sendforce-field">
					<label for="sendforce_test_email" class="sendforce-label">
						<?php esc_html_e( 'Send Test Email To', 'sendforce-mail-relay' ); ?>
					</label>
					<div class="sendforce-test-input-row">
						<input
							type="email"
							id="sendforce_test_email"
							class="sendforce-input"
							value="<?php echo esc_attr( $sendforce_default_email ); ?>"
							placeholder="you@example.com"
							/>
						<button
							type="button"
							id="sendforce_send_test"
							class="sendforce-btn sendforce-btn-primary"
						>
							<span class="dashicons dashicons-email sendforce-btn-icon"></span>
							<?php esc_html_e( 'Send Test', 'sendforce-mail-relay' ); ?>
						</button>
					</div>
					<span class="sendforce-help"><?php esc_html_e( 'Enter the email address where you want to receive the test email.', 'sendforce-mail-relay' ); ?></span>
				</div>
			</div>

			<!-- Result area -->
			<div id="sendforce_test_result_wrap" style="display:none;">
				<div id="sendforce_test_result_banner" class="sendforce-test-result-banner">
					<span class="sendforce-test-result-icon" id="sendforce_test_result_icon"></span>
					<span id="sendforce_test_result_headline"></span>
				</div>

				<!-- Full API response panel — shown when the test failed and the
				     provider returned an HTTP response body we can display. -->
				<div class="sendforce-test-detail-wrap" id="sendforce_test_detail_wrap" style="display:none;">
					<div class="sendforce-test-detail-header">
						<span class="dashicons dashicons-warning"></span>
						<span><?php esc_html_e( 'Provider response', 'sendforce-mail-relay' ); ?></span>
						<button type="button" class="sendforce-btn sendforce-btn-xs sendforce-btn-outline sendforce-test-detail-copy" id="sendforce_test_detail_copy">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'sendforce-mail-relay' ); ?>
						</button>
					</div>
					<pre class="sendforce-test-detail-body" id="sendforce_test_detail_body"></pre>
				</div>

				<div class="sendforce-smtp-log-wrap" id="sendforce_smtp_log_wrap" style="display:none;">
					<div class="sendforce-smtp-log-header">
						<span class="dashicons dashicons-editor-code"></span>
						<?php esc_html_e( 'SMTP Debug Log', 'sendforce-mail-relay' ); ?>
					</div>
					<pre class="sendforce-smtp-log" id="sendforce_smtp_log"></pre>
				</div>
			</div>

		</div>
	</div>

	<!-- Tips Sidebar -->
	<div class="sendforce-email-test-sidebar">

		<!-- Checklist card -->
		<div class="sendforce-card">
			<div class="sendforce-card-header">
				<h3><?php esc_html_e( 'Troubleshooting Tips', 'sendforce-mail-relay' ); ?></h3>
			</div>
			<div class="sendforce-card-body">
				<ul class="sendforce-tip-list">
					<li>
						<span class="dashicons dashicons-yes-alt sendforce-tip-icon sendforce-tip-green"></span>
						<?php esc_html_e( 'Check your spam / junk folder if email is not in inbox.', 'sendforce-mail-relay' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt sendforce-tip-icon sendforce-tip-green"></span>
						<?php esc_html_e( 'Ensure the From Email matches your sending domain (SPF/DKIM).', 'sendforce-mail-relay' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt sendforce-tip-icon sendforce-tip-green"></span>
						<?php esc_html_e( 'For SMTP: port 587 with TLS is recommended over port 25.', 'sendforce-mail-relay' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt sendforce-tip-icon sendforce-tip-green"></span>
						<?php esc_html_e( 'For API: verify your API key has send permissions.', 'sendforce-mail-relay' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt sendforce-tip-icon sendforce-tip-green"></span>
						<?php esc_html_e( 'Use a dedicated transactional email provider for better deliverability.', 'sendforce-mail-relay' ); ?>
					</li>
				</ul>
			</div>
		</div>

		<!-- What this test sends -->
		<div class="sendforce-card">
			<div class="sendforce-card-header">
				<h3><?php esc_html_e( 'What Gets Sent', 'sendforce-mail-relay' ); ?></h3>
			</div>
			<div class="sendforce-card-body">
				<div class="sendforce-test-preview">
					<div class="sendforce-test-preview-row">
						<span class="sendforce-test-preview-label"><?php esc_html_e( 'From', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-test-preview-value sendforce-monospace">
							<?php
							$sendforce_from_name  = ! empty( $sendforce_settings['from_name'] ) ? $sendforce_settings['from_name'] : get_bloginfo( 'name' );
							$sendforce_from_email = ! empty( $sendforce_settings['from_email'] ) ? $sendforce_settings['from_email'] : get_option( 'admin_email' );
							echo esc_html( $sendforce_from_name . ' <' . $sendforce_from_email . '>' );
							?>
						</span>
					</div>
					<div class="sendforce-test-preview-row">
						<span class="sendforce-test-preview-label"><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-test-preview-value">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: Site name */
									__( 'SendForce Mail Relay Test Email from %s', 'sendforce-mail-relay' ),
									get_bloginfo( 'name' )
								)
							);
							?>
						</span>
					</div>
					<div class="sendforce-test-preview-row">
						<span class="sendforce-test-preview-label"><?php esc_html_e( 'Content', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-test-preview-value sendforce-text-muted">
							<?php esc_html_e( 'Plain-text confirmation message with timestamp.', 'sendforce-mail-relay' ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Copy Debug Info card -->
		<div class="sendforce-card">
			<div class="sendforce-card-header">
				<h2><?php esc_html_e( 'Copy Debug Info', 'sendforce-mail-relay' ); ?></h2>
			</div>
			<div class="sendforce-card-body">
				<p style="margin-top:0;"><?php esc_html_e( 'Generate a sanitized snapshot of your environment, settings, and recent activity. Paste it into a support ticket — passwords and API keys are never included.', 'sendforce-mail-relay' ); ?></p>
				<button type="button" id="sendforce-copy-debug" class="sendforce-btn sendforce-btn-primary" style="width:100%;justify-content:center;">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy Debug Info', 'sendforce-mail-relay' ); ?>
				</button>
				<textarea id="sendforce-debug-output" rows="10" readonly style="display:none;width:100%;margin-top:10px;font-family:monospace;font-size:11px;"></textarea>
				<p id="sendforce-debug-status" class="sendforce-text-muted" style="margin-bottom:0;font-size:12px;"></p>
			</div>
		</div>

	</div><!-- /.sendforce-email-test-sidebar -->

</div><!-- /.sendforce-email-test-layout -->
