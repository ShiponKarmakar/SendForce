<?php
/**
 * Dashboard tab — professional layout.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_stats         = SendForce_Admin::get_dashboard_stats();
$sendforce_weekly        = SendForce_Admin::get_weekly_totals();
$sendforce_recent_emails = SendForce_Admin::get_recent_emails( 7 );
$sendforce_sysinfo       = SendForce_Admin::get_system_info();
$sendforce_settings      = get_option( 'sendforce_settings', array() );
$sendforce_connections   = get_option( 'sendforce_connections', array() );
$sendforce_is_configured = ! empty( $sendforce_connections );
?>

<!-- Header -->
<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Dashboard', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'Overview of your email activity', 'sendforce-mail-relay' ); ?></p>
	</div>
	<div class="sendforce-header-actions">
		<?php if ( $sendforce_is_configured ) : ?>
			<?php $sendforce_conn_count = count( $sendforce_connections ); ?>
			<span class="sendforce-connection-pill sendforce-pill-connected">
				<span class="sendforce-pill-dot"></span>
				<?php /* translators: %d: number of active connections */ ?>
				<?php echo esc_html( sprintf( _n( '%d Connection Active', '%d Connections Active', $sendforce_conn_count, 'sendforce-mail-relay' ), $sendforce_conn_count ) ); ?>
			</span>
		<?php else : ?>
			<span class="sendforce-connection-pill sendforce-pill-disconnected">
				<span class="sendforce-pill-dot"></span>
				<?php esc_html_e( 'Not Connected', 'sendforce-mail-relay' ); ?>
			</span>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' ) ); ?>" class="sendforce-btn sendforce-btn-outline">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'Settings', 'sendforce-mail-relay' ); ?>
		</a>
	</div>
</div>

<!-- Setup Guide (if not configured) -->
<?php if ( ! $sendforce_is_configured ) : ?>
	<div class="sendforce-setup-card">
		<div class="sendforce-setup-icon">
			<span class="dashicons dashicons-email-alt"></span>
		</div>
		<div class="sendforce-setup-content">
			<h2><?php esc_html_e( 'Get Started with SendForce Mail Relay', 'sendforce-mail-relay' ); ?></h2>
			<p><?php esc_html_e( 'Configure your SMTP server to start sending reliable emails from WordPress.', 'sendforce-mail-relay' ); ?></p>
			<ol class="sendforce-setup-steps">
				<li><?php esc_html_e( 'Go to Settings and select your SMTP provider', 'sendforce-mail-relay' ); ?></li>
				<li><?php esc_html_e( 'Enter your SMTP credentials (host, username, password)', 'sendforce-mail-relay' ); ?></li>
				<li><?php esc_html_e( 'Set your sender name and email address', 'sendforce-mail-relay' ); ?></li>
				<li><?php esc_html_e( 'Send a test email to verify everything works', 'sendforce-mail-relay' ); ?></li>
			</ol>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=settings' ) ); ?>" class="sendforce-btn sendforce-btn-primary">
				<?php esc_html_e( 'Configure SMTP Now', 'sendforce-mail-relay' ); ?>
			</a>
		</div>
	</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="sendforce-stats-grid">
	<div class="sendforce-stat-card">
		<div class="sendforce-stat-icon sendforce-stat-icon-sent">
			<span class="dashicons dashicons-yes-alt"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( number_format_i18n( $sendforce_stats['total_sent'] ) ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Emails Sent', 'sendforce-mail-relay' ); ?></span>
			<span class="sendforce-stat-sub">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: count */
						_n( '%d this week', '%d this week', $sendforce_weekly['sent'], 'sendforce-mail-relay' ),
						$sendforce_weekly['sent']
					)
				);
				?>
			</span>
		</div>
	</div>

	<div class="sendforce-stat-card">
		<div class="sendforce-stat-icon sendforce-stat-icon-failed">
			<span class="dashicons dashicons-dismiss"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( number_format_i18n( $sendforce_stats['total_failed'] ) ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?></span>
			<span class="sendforce-stat-sub">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: count */
						_n( '%d this week', '%d this week', $sendforce_weekly['failed'], 'sendforce-mail-relay' ),
						$sendforce_weekly['failed']
					)
				);
				?>
			</span>
		</div>
	</div>

	<div class="sendforce-stat-card">
		<div class="sendforce-stat-icon sendforce-stat-icon-queued">
			<span class="dashicons dashicons-clock"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( number_format_i18n( $sendforce_stats['queued'] ) ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'In Queue', 'sendforce-mail-relay' ); ?></span>
			<span class="sendforce-stat-sub">
				<?php echo ! empty( $sendforce_settings['queue_enabled'] ) ? esc_html__( 'Queue active', 'sendforce-mail-relay' ) : esc_html__( 'Queue disabled', 'sendforce-mail-relay' ); ?>
			</span>
		</div>
	</div>

	<div class="sendforce-stat-card">
		<div class="sendforce-stat-icon sendforce-stat-icon-rate">
			<span class="dashicons dashicons-chart-pie"></span>
		</div>
		<div class="sendforce-stat-info">
			<span class="sendforce-stat-number"><?php echo esc_html( $sendforce_stats['success_rate'] . '%' ); ?></span>
			<span class="sendforce-stat-label"><?php esc_html_e( 'Success Rate', 'sendforce-mail-relay' ); ?></span>
			<span class="sendforce-stat-sub">
				<?php esc_html_e( 'All time', 'sendforce-mail-relay' ); ?>
			</span>
		</div>
	</div>
</div>

<!-- Email Statistics Chart -->
<div class="sendforce-card sendforce-stat-chart-card">
	<div class="sendforce-card-header">
		<div class="sendforce-chart-header-left">
			<h2><?php esc_html_e( 'Email Statistics', 'sendforce-mail-relay' ); ?></h2>
			<div class="sendforce-chart-legend-inline">
				<span class="sendforce-legend-item">
					<span class="sendforce-legend-dot sendforce-legend-sent"></span>
					<?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?>
				</span>
				<span class="sendforce-legend-item">
					<span class="sendforce-legend-dot sendforce-legend-failed"></span>
					<?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?>
				</span>
			</div>
		</div>
		<div class="sendforce-chart-header-right">
			<select id="sendforce-chart-range" class="sendforce-select sendforce-select-sm">
				<option value="7"><?php esc_html_e( 'Last 7 Days', 'sendforce-mail-relay' ); ?></option>
				<option value="30"><?php esc_html_e( 'Last 30 Days', 'sendforce-mail-relay' ); ?></option>
				<option value="all"><?php esc_html_e( 'Last 90 Days', 'sendforce-mail-relay' ); ?></option>
			</select>
		</div>
	</div>
	<div class="sendforce-card-body sendforce-chart-body">
		<div class="sendforce-linechart-wrap">
			<canvas id="sendforce-email-chart"></canvas>
		</div>
		<div class="sendforce-chart-footer">
			<div class="sendforce-chart-foot-stat">
				<span class="dashicons dashicons-email-alt sendforce-chart-foot-icon"></span>
				<span>
					<span id="sendforce-stat-total" class="sendforce-chart-foot-num"><?php echo esc_html( $sendforce_stats['total_sent'] + $sendforce_stats['total_failed'] ); ?></span>
					<span class="sendforce-chart-foot-label"><?php esc_html_e( 'Total', 'sendforce-mail-relay' ); ?></span>
				</span>
			</div>
			<div class="sendforce-chart-foot-divider"></div>
			<div class="sendforce-chart-foot-stat">
				<span class="sendforce-chart-foot-dot sendforce-chart-foot-dot-sent"></span>
				<span>
					<span id="sendforce-stat-sent" class="sendforce-chart-foot-num sendforce-chart-foot-num-sent"><?php echo esc_html( $sendforce_stats['total_sent'] ); ?></span>
					<span class="sendforce-chart-foot-label"><?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?></span>
				</span>
			</div>
			<div class="sendforce-chart-foot-divider"></div>
			<div class="sendforce-chart-foot-stat">
				<span class="sendforce-chart-foot-dot sendforce-chart-foot-dot-failed"></span>
				<span>
					<span id="sendforce-stat-failed" class="sendforce-chart-foot-num sendforce-chart-foot-num-failed"><?php echo esc_html( $sendforce_stats['total_failed'] ); ?></span>
					<span class="sendforce-chart-foot-label"><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?></span>
				</span>
			</div>
		</div>
	</div>
</div>

<!-- Two Column Layout -->
<div class="sendforce-dashboard-columns">
	<!-- Left: Recent Emails -->
	<div class="sendforce-dashboard-main">
		<div class="sendforce-card">
			<div class="sendforce-card-header">
				<h2><?php esc_html_e( 'Recent Emails', 'sendforce-mail-relay' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=email-log' ) ); ?>" class="sendforce-link">
					<?php esc_html_e( 'View All', 'sendforce-mail-relay' ); ?> &rarr;
				</a>
			</div>

			<?php if ( empty( $sendforce_recent_emails ) ) : ?>
				<div class="sendforce-empty-state">
					<span class="dashicons dashicons-email"></span>
					<p><?php esc_html_e( 'No emails have been sent yet.', 'sendforce-mail-relay' ); ?></p>
				</div>
			<?php else : ?>
				<div class="sendforce-table-wrap">
					<table class="sendforce-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Recipient', 'sendforce-mail-relay' ); ?></th>
								<th><?php esc_html_e( 'Subject', 'sendforce-mail-relay' ); ?></th>
								<th><?php esc_html_e( 'Status', 'sendforce-mail-relay' ); ?></th>
								<th><?php esc_html_e( 'Date', 'sendforce-mail-relay' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sendforce_recent_emails as $sendforce_email ) : ?>
								<tr>
									<td class="sendforce-td-email"><?php echo esc_html( $sendforce_email->to_email ); ?></td>
									<td><?php echo esc_html( wp_trim_words( $sendforce_email->subject, 6 ) ); ?></td>
									<td>
										<span class="sendforce-badge sendforce-badge-<?php echo esc_attr( $sendforce_email->status ); ?>">
											<?php echo esc_html( ucfirst( $sendforce_email->status ) ); ?>
										</span>
									</td>
									<td class="sendforce-td-date">
										<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $sendforce_email->created_at ) ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Right: Quick Test + System Info -->
	<div class="sendforce-dashboard-side">
		<!-- Quick Test -->
		<div class="sendforce-card sendforce-card-compact">
			<h3><?php esc_html_e( 'Quick Test', 'sendforce-mail-relay' ); ?></h3>
			<p class="sendforce-text-muted"><?php esc_html_e( 'Verify your SMTP configuration', 'sendforce-mail-relay' ); ?></p>
			<div class="sendforce-inline-form">
				<input type="email" id="sendforce_test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="sendforce-input" placeholder="<?php esc_attr_e( 'Recipient email', 'sendforce-mail-relay' ); ?>" />
				<button type="button" id="sendforce_send_test" class="sendforce-btn sendforce-btn-primary">
					<?php esc_html_e( 'Send', 'sendforce-mail-relay' ); ?>
				</button>
			</div>
			<div id="sendforce_test_result_wrap" style="display:none;margin-top:12px;">
				<div id="sendforce_test_result_banner" class="sendforce-test-result-banner">
					<span class="sendforce-test-result-icon" id="sendforce_test_result_icon"></span>
					<span id="sendforce_test_result_headline"></span>
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

		<!-- System Info -->
		<div class="sendforce-card sendforce-card-compact">
			<h3><?php esc_html_e( 'System Info', 'sendforce-mail-relay' ); ?></h3>
			<div class="sendforce-sysinfo">
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'SMTP Server', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value">
						<?php if ( $sendforce_is_configured ) : ?>
							<span class="sendforce-sysinfo-ok"><?php echo esc_html( $sendforce_sysinfo['smtp_host'] . ':' . $sendforce_sysinfo['smtp_port'] ); ?></span>
						<?php else : ?>
							<span class="sendforce-sysinfo-warn"><?php esc_html_e( 'Not configured', 'sendforce-mail-relay' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'Encryption', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value"><?php echo esc_html( $sendforce_sysinfo['smtp_enc'] ); ?></span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'Queue', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value">
						<?php if ( $sendforce_sysinfo['queue_enabled'] ) : ?>
							<span class="sendforce-sysinfo-ok"><?php esc_html_e( 'Enabled', 'sendforce-mail-relay' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'Disabled', 'sendforce-mail-relay' ); ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'Log Retention', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value">
						<?php
						$sendforce_ret = absint( $sendforce_sysinfo['log_retention'] );
						echo 0 === $sendforce_ret ? esc_html__( 'Forever', 'sendforce-mail-relay' ) : esc_html( $sendforce_ret . ' ' . __( 'days', 'sendforce-mail-relay' ) );
						?>
					</span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'Total Logged', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value"><?php echo esc_html( number_format_i18n( $sendforce_sysinfo['total_logs'] ) ); ?></span>
				</div>
				<div class="sendforce-sysinfo-divider"></div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'WordPress', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value"><?php echo esc_html( $sendforce_sysinfo['wp_version'] ); ?></span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'PHP', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value"><?php echo esc_html( $sendforce_sysinfo['php_version'] ); ?></span>
				</div>
				<div class="sendforce-sysinfo-row">
					<span class="sendforce-sysinfo-label"><?php esc_html_e( 'SendForce Mail Relay', 'sendforce-mail-relay' ); ?></span>
					<span class="sendforce-sysinfo-value"><?php echo esc_html( 'v' . SENDFORCE_VERSION ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
