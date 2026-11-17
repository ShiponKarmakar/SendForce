<?php
/**
 * About tab template.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="sendforce-about-wrap">

	<!-- Hero -->
	<div class="sendforce-about-hero">
		<div class="sendforce-about-hero-inner">
			<div class="sendforce-about-logo-wrap">
				<img src="<?php echo esc_url( SENDFORCE_PLUGIN_URL . 'assets/images/logo.svg' ); ?>"
					alt="<?php esc_attr_e( 'SendForce Mail Relay', 'sendforce-mail-relay' ); ?>"
					class="sendforce-about-logo" />
			</div>
			<div class="sendforce-about-hero-text">
				<h1 class="sendforce-about-plugin-name">
					<?php esc_html_e( 'SendForce Mail Relay', 'sendforce-mail-relay' ); ?>
					<span class="sendforce-about-version">v<?php echo esc_html( SENDFORCE_VERSION ); ?></span>
				</h1>
				<p class="sendforce-about-tagline">
					<?php esc_html_e( 'Reliable email delivery for WordPress — built with care by ZendForce LLC.', 'sendforce-mail-relay' ); ?>
				</p>
				<div class="sendforce-about-hero-actions">
					<a href="https://wordpress.org/support/plugin/sendforce-mail-relay/" target="_blank" rel="noopener noreferrer" class="sendforce-btn sendforce-btn-primary">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
						<?php esc_html_e( 'Get Support', 'sendforce-mail-relay' ); ?>
					</a>
					<a href="https://wordpress.org/plugins/sendforce-mail-relay/#reviews" target="_blank" rel="noopener noreferrer" class="sendforce-btn sendforce-btn-outline-star">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="margin-right:6px;vertical-align:-2px;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php esc_html_e( 'Rate the Plugin', 'sendforce-mail-relay' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<div class="sendforce-about-body">

		<!-- Left column -->
		<div class="sendforce-about-main">

			<!-- About the Company -->
			<div class="sendforce-card sendforce-about-card">
				<div class="sendforce-card-header">
					<h2><?php esc_html_e( 'About ZendForce LLC', 'sendforce-mail-relay' ); ?></h2>
				</div>
				<div class="sendforce-card-body sendforce-about-company-body">
					<div class="sendforce-about-company-logo">
						<div class="sendforce-about-company-icon">ZF</div>
					</div>
					<div class="sendforce-about-company-text">
						<p><?php esc_html_e( 'ZendForce LLC is a software company focused on building powerful, developer-friendly WordPress plugins that solve real-world problems for businesses of all sizes.', 'sendforce-mail-relay' ); ?></p>
						<p><?php esc_html_e( 'We believe great tools should be simple to use, reliable under pressure, and built on clean code. SendForce Mail Relay is our commitment to giving every WordPress site a professional-grade email delivery experience — without the complexity.', 'sendforce-mail-relay' ); ?></p>
						<p><?php esc_html_e( 'Our team is passionate about open-source, user experience, and delivering software you can depend on.', 'sendforce-mail-relay' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Feature highlights -->
			<div class="sendforce-card sendforce-about-card">
				<div class="sendforce-card-header">
					<h2><?php esc_html_e( 'What SendForce Mail Relay Does', 'sendforce-mail-relay' ); ?></h2>
				</div>
				<div class="sendforce-card-body">
					<div class="sendforce-about-features-grid">

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-green">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
							</div>
							<div>
								<h4><?php esc_html_e( 'Multiple SMTP Connections', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'Add unlimited SMTP or API connections and set a primary with automatic fallback.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-blue">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
							</div>
							<div>
								<h4><?php esc_html_e( 'Email Logging', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'Full email activity log with status tracking, date filtering, and body preview.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-purple">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
							</div>
							<div>
								<h4><?php esc_html_e( '18+ Provider Presets', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'One-click configuration for Google, Microsoft 365, SendGrid, Mailgun, SES, Brevo, and more.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-orange">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
							</div>
							<div>
								<h4><?php esc_html_e( 'Email Queue', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'Queue and batch-send emails via WP-Cron to avoid rate limits and server timeouts.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-teal">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"></path></svg>
							</div>
							<div>
								<h4><?php esc_html_e( 'Smart Alerts', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'Get notified via Telegram, Slack, Discord, or Pushover when email delivery fails.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

						<div class="sendforce-about-feature">
							<div class="sendforce-about-feature-icon sendforce-about-fi-red">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
							</div>
							<div>
								<h4><?php esc_html_e( 'Test & Simulate', 'sendforce-mail-relay' ); ?></h4>
								<p><?php esc_html_e( 'Send test emails and use simulation mode to verify setup without sending live mail.', 'sendforce-mail-relay' ); ?></p>
							</div>
						</div>

					</div>
				</div>
			</div>

		</div>

		<!-- Right column -->
		<div class="sendforce-about-sidebar">

			<!-- Plugin info -->
			<div class="sendforce-card sendforce-about-card">
				<div class="sendforce-card-header">
					<h2><?php esc_html_e( 'Plugin Details', 'sendforce-mail-relay' ); ?></h2>
				</div>
				<div class="sendforce-card-body sendforce-about-meta-list">
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'Version', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value"><?php echo esc_html( SENDFORCE_VERSION ); ?></span>
					</div>
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'Author', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value">
							<a href="https://profiles.wordpress.org/shiponkarmakar/" target="_blank" rel="noopener noreferrer">Shipon Karmakar</a>
						</span>
					</div>
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'Company', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value">ZendForce LLC</span>
					</div>
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'License', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value">
							<a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener noreferrer">GPL v2+</a>
						</span>
					</div>
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'WordPress', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value"><?php esc_html_e( '5.9+', 'sendforce-mail-relay' ); ?></span>
					</div>
					<div class="sendforce-about-meta-row">
						<span class="sendforce-about-meta-label"><?php esc_html_e( 'PHP', 'sendforce-mail-relay' ); ?></span>
						<span class="sendforce-about-meta-value"><?php esc_html_e( '7.4+', 'sendforce-mail-relay' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Links -->
			<div class="sendforce-card sendforce-about-card">
				<div class="sendforce-card-header">
					<h2><?php esc_html_e( 'Helpful Links', 'sendforce-mail-relay' ); ?></h2>
				</div>
				<div class="sendforce-card-body sendforce-about-links">
					<a href="https://wordpress.org/plugins/sendforce-mail-relay/" target="_blank" rel="noopener noreferrer" class="sendforce-about-link">
						<div class="sendforce-about-link-icon sendforce-about-fi-blue">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
						</div>
						<span><?php esc_html_e( 'WordPress.org Plugin Page', 'sendforce-mail-relay' ); ?></span>
						<svg class="sendforce-about-link-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
					<a href="https://wordpress.org/support/plugin/sendforce-mail-relay/" target="_blank" rel="noopener noreferrer" class="sendforce-about-link">
						<div class="sendforce-about-link-icon sendforce-about-fi-green">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
						</div>
						<span><?php esc_html_e( 'Support Forum', 'sendforce-mail-relay' ); ?></span>
						<svg class="sendforce-about-link-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
					<a href="https://wordpress.org/plugins/sendforce-mail-relay/#reviews" target="_blank" rel="noopener noreferrer" class="sendforce-about-link">
						<div class="sendforce-about-link-icon sendforce-about-fi-orange">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						</div>
						<span><?php esc_html_e( 'Leave a Review ⭐', 'sendforce-mail-relay' ); ?></span>
						<svg class="sendforce-about-link-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
					<a href="https://wordpress.org/plugins/sendforce-mail-relay/changelog/" target="_blank" rel="noopener noreferrer" class="sendforce-about-link">
						<div class="sendforce-about-link-icon sendforce-about-fi-purple">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
						</div>
						<span><?php esc_html_e( 'Changelog', 'sendforce-mail-relay' ); ?></span>
						<svg class="sendforce-about-link-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
				</div>
			</div>

			<!-- Rate Us CTA -->
			<div class="sendforce-card sendforce-about-rate-card">
				<div class="sendforce-about-rate-stars">⭐⭐⭐⭐⭐</div>
				<h3><?php esc_html_e( 'Enjoying SendForce Mail Relay?', 'sendforce-mail-relay' ); ?></h3>
				<p><?php esc_html_e( 'Your review helps other WordPress users discover the plugin and motivates us to keep improving it. It only takes 30 seconds!', 'sendforce-mail-relay' ); ?></p>
				<a href="https://wordpress.org/plugins/sendforce-mail-relay/#reviews" target="_blank" rel="noopener noreferrer"
					class="sendforce-btn sendforce-btn-primary" style="width:100%;justify-content:center;">
					<?php esc_html_e( 'Rate SendForce Mail Relay', 'sendforce-mail-relay' ); ?>
				</a>
			</div>

		</div><!-- /.sendforce-about-sidebar -->
	</div><!-- /.sendforce-about-body -->
</div><!-- /.sendforce-about-wrap -->
