<?php
/**
 * "What's Next" tab — public roadmap of upcoming features.
 *
 * A pure presentation tab — no settings, no AJAX. Doubles as marketing
 * (shows users the plugin is actively developed) and as a feedback channel
 * (users can vote / suggest via the link at the bottom).
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Roadmap data — keep this list sorted by release. The tab renders three
 * groups: "Coming next" (the next release), "Planned" (later releases),
 * "Researching" (ideas being scoped).
 *
 * Each entry: title, blurb, dashicon, status (next|planned|research). No dates or
 * version numbers: the cards make no timing promises that can go stale.
 */
$sendforce_roadmap = array(

	// ---- Setup & deliverability ----
	array(
		'title'  => __( 'Setup Wizard', 'sendforce-mail-relay' ),
		'blurb'  => __( 'A guided 3-step wizard for new installs — pick your provider, paste credentials, send a test.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-welcome-learn-more',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Domain Health Checker', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Real-time SPF, DKIM, DMARC, MX, PTR, TLS and blocklist (Spamhaus, Barracuda) status right on the dashboard.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-shield',
		'status' => 'planned',
	),

	// ---- Quick wins ----
	array(
		'title'  => __( 'Resend email from log', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Re-send any logged email with one click — single send or bulk. A frequently requested feature.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-update-alt',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Forward emails from log', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Forward any logged email to an alternate address. Useful for archiving, support handoffs, or sending a copy to your phone.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-share-alt2',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Disable default WordPress notifications', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Granular toggles to silence specific WordPress notifications you don\'t want sent — admin email change confirmations, password resets, comment notifications, and more.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-no-alt',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Weekly digest email', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Daily summary already exists — adds a weekly cadence option for less inbox noise. Choose what stats and channels you want included.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-email-alt2',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Backup Connection discovery polish', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Auto-failover already ships in v1.0 but most users don\'t know. Adds a dashboard banner when no fallback is configured, plus a FALLBACK badge in the connection list.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-backup',
		'status' => 'planned',
	),

	// ---- Branded emails + Test Mode ----
	array(
		'title'  => __( 'Branded WordPress email templates', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Replace default WordPress emails (password reset, welcome, WooCommerce orders) with branded HTML — your logo, theme color, header and footer. Visual editor with live preview.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-art',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Test Mode (catch-all)', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Redirect every outgoing email to a single staging address with the original recipient in the subject prefix. Critical for staging sites where test orders shouldn\'t email real customers.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-controls-pause',
		'status' => 'planned',
	),

	// ---- Migration + sending caps + SMS ----
	array(
		'title'  => __( 'More plugins to import from', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Importing from WP Mail SMTP, FluentSMTP and Easy WP SMTP shipped in 1.1.3. Next: Post SMTP Mailer, SMTP Mailer and WP SMTP.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-migrate',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Hourly, daily & monthly sending caps', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Per-minute pacing per connection shipped in 1.1.0. Next: hour, day and month caps, so a connection never exceeds a provider quota such as Gmail\'s daily sending limit. Overflow stays queued for the next window.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-clock',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'SMS / Twilio alerts', 'sendforce-mail-relay' ),
		'blurb'  => __( 'A new alert channel via Twilio SMS — get a text message when your mail starts failing, even when you\'re away from your desk.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-smartphone',
		'status' => 'planned',
	),

	// ---- Reputation ----
	array(
		'title'  => __( 'Bounce tracking & blocklist', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Webhook receivers for SES, SendGrid, Mailgun, and Postmark. Auto-block addresses with repeated hard bounces to protect your sender reputation.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-warning',
		'status' => 'planned',
	),
	array(
		'title'  => __( 'Setup Health Dashboard', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Proactive insights: "Your bounce rate is 12% — industry average is 2%", "Your Zoho token expires in 7 days", "You\'ve used 4,500 of 5,000 SendGrid free-tier sends this month."', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-chart-line',
		'status' => 'planned',
	),

	// ---- Researching (parking lot) ----
	array(
		'title'  => __( 'Network-wide settings for Multisite', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Multisite networks are supported since 1.1.2, with each site configured on its own. Next: one shared connection set from Network Admin, plus network-wide log management for agencies running many sites.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-networking',
		'status' => 'research',
	),
	array(
		'title'  => __( 'Per-plugin routing UI', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Visual routing controls embedded directly into WooCommerce, WPForms, and Gravity Forms — pick a connection per form, per email type, per workflow.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-randomize',
		'status' => 'research',
	),
	array(
		'title'  => __( 'IP warming wizard', 'sendforce-mail-relay' ),
		'blurb'  => __( 'A guided ramp-up assistant for new SES, Mailgun, and dedicated-IP accounts — gradually increases volume to build sender reputation safely.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-chart-area',
		'status' => 'research',
	),
	array(
		'title'  => __( 'REST API for external apps', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Send mail through your configured SendForce connections from external apps — useful for headless setups, mobile apps, and custom integrations.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-rest-api',
		'status' => 'research',
	),
	array(
		'title'  => __( 'Email preview queue', 'sendforce-mail-relay' ),
		'blurb'  => __( 'A manual approval step before emails leave the server — useful for compliance-sensitive workflows or staging environments where someone needs to sign off on outgoing mail.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-visibility',
		'status' => 'research',
	),
	array(
		'title'  => __( 'A/B testing for subject lines', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Split-test subject lines and From identities for outgoing transactional emails. See which version gets more opens, automatically pick the winner.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-chart-pie',
		'status' => 'research',
	),
	array(
		'title'  => __( 'AI content audits', 'sendforce-mail-relay' ),
		'blurb'  => __( 'Run outgoing emails through a spam-score and readability check before they send. Flag risky phrasing, broken links, missing alt text, and more.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-lightbulb',
		'status' => 'research',
	),
	array(
		'title'  => __( 'Pro tier launch', 'sendforce-mail-relay' ),
		'blurb'  => __( 'A potential paid tier built on top of the free plugin — likely wedge: bounce tracking + multisite + priority support. Free plugin keeps everything it has today.', 'sendforce-mail-relay' ),
		'icon'   => 'dashicons-star-filled',
		'status' => 'research',
	),
);

// Group entries by status for rendering.
$sendforce_groups = array(
	'next'     => array(
		'label'  => __( 'Coming next', 'sendforce-mail-relay' ),
		'sub'    => __( 'Shipping in the next release.', 'sendforce-mail-relay' ),
		'items'  => array(),
	),
	'planned'  => array(
		'label'  => __( 'Roadmap', 'sendforce-mail-relay' ),
		'sub'    => __( 'Planned for the next few releases.', 'sendforce-mail-relay' ),
		'items'  => array(),
	),
	'research' => array(
		'label'  => __( 'Researching', 'sendforce-mail-relay' ),
		'sub'    => __( 'Ideas we\'re looking at. Tell us if you need any of these.', 'sendforce-mail-relay' ),
		'items'  => array(),
	),
);
foreach ( $sendforce_roadmap as $sendforce_item ) {
	$sendforce_groups[ $sendforce_item['status'] ]['items'][] = $sendforce_item;
}
?>

<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Roadmap', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php
			/* translators: %s: plugin version, e.g. 1.0.19 */
			echo esc_html( sprintf( __( 'You\'re running v%s. Below is what we\'re working on next.', 'sendforce-mail-relay' ), SENDFORCE_VERSION ) );
		?></p>
	</div>
</div>

<div class="sendforce-card sendforce-upcoming-hero">
	<div class="sendforce-upcoming-hero-icon">
		<span class="dashicons dashicons-megaphone"></span>
	</div>
	<div class="sendforce-upcoming-hero-body">
		<h2><?php esc_html_e( 'Missing something you need?', 'sendforce-mail-relay' ); ?></h2>
		<p><?php esc_html_e( 'Open a thread on the support forum — most items below started there.', 'sendforce-mail-relay' ); ?></p>
	</div>
</div>

<?php foreach ( $sendforce_groups as $sendforce_status => $sendforce_group ) :
	if ( empty( $sendforce_group['items'] ) ) {
		continue;
	}
	?>
	<div class="sendforce-upcoming-section">
		<div class="sendforce-upcoming-section-header">
			<h2><?php echo esc_html( $sendforce_group['label'] ); ?></h2>
			<p class="sendforce-text-muted"><?php echo esc_html( $sendforce_group['sub'] ); ?></p>
		</div>

		<div class="sendforce-upcoming-grid">
			<?php foreach ( $sendforce_group['items'] as $sendforce_item ) : ?>
				<div class="sendforce-upcoming-card sendforce-upcoming-card--<?php echo esc_attr( $sendforce_status ); ?>">
					<div class="sendforce-upcoming-card-head">
						<span class="sendforce-upcoming-card-icon">
							<span class="dashicons <?php echo esc_attr( $sendforce_item['icon'] ); ?>"></span>
						</span>
						<span class="sendforce-upcoming-card-status sendforce-upcoming-card-status--<?php echo esc_attr( $sendforce_status ); ?>">
							<?php
							switch ( $sendforce_status ) {
								case 'next':
									esc_html_e( 'Coming next', 'sendforce-mail-relay' );
									break;
								case 'planned':
									esc_html_e( 'Roadmap', 'sendforce-mail-relay' );
									break;
								case 'research':
									esc_html_e( 'Researching', 'sendforce-mail-relay' );
									break;
							}
							?>
						</span>
					</div>
					<h3 class="sendforce-upcoming-card-title"><?php echo esc_html( $sendforce_item['title'] ); ?></h3>
					<p class="sendforce-upcoming-card-blurb"><?php echo esc_html( $sendforce_item['blurb'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endforeach; ?>

<!-- Changelog (collapsible, FAQ-style accordion) -->
<?php
// Pull the changelog parser/renderer helper. The tab file is included from
// dashboard-page.php so SENDFORCE_PLUGIN_DIR is available here.
if ( ! function_exists( 'sendforce_parse_changelog' ) ) {
	require_once SENDFORCE_PLUGIN_DIR . 'admin/partials/tabs/changelog-tab.php';
}
$sendforce_changelog_inline = sendforce_parse_changelog();
?>
<?php if ( ! empty( $sendforce_changelog_inline ) ) : ?>
	<div class="sendforce-upcoming-section sendforce-changelog-section">
		<div class="sendforce-upcoming-section-header">
			<h2><?php esc_html_e( 'Changelog', 'sendforce-mail-relay' ); ?></h2>
			<p class="sendforce-text-muted">
				<?php
				echo esc_html( sprintf(
					/* translators: %d: total number of release entries */
					_n( '%d release shipped so far. Click any version to see what changed.', '%d releases shipped so far. Click any version to see what changed.', count( $sendforce_changelog_inline ), 'sendforce-mail-relay' ),
					count( $sendforce_changelog_inline )
				) );
				?>
			</p>
		</div>

		<div class="sendforce-changelog-accordion">
			<?php foreach ( $sendforce_changelog_inline as $sendforce_idx => $sendforce_release ) :
				$sendforce_is_current = version_compare( $sendforce_release['version'], SENDFORCE_VERSION, '==' );
				// Auto-open only the current installed version. Everything else
				// stays collapsed for minimal vertical space — users click any
				// version they want to read.
				$sendforce_open_attr  = $sendforce_is_current ? ' open' : '';
				?>
				<details class="sendforce-changelog-item<?php echo esc_attr( $sendforce_is_current ? ' sendforce-changelog-item--current' : '' ); ?>"<?php echo esc_attr( $sendforce_open_attr ); ?>>
					<summary class="sendforce-changelog-summary">
						<span class="sendforce-changelog-summary-chevron" aria-hidden="true">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						</span>
						<span class="sendforce-changelog-summary-version">v<?php echo esc_html( $sendforce_release['version'] ); ?></span>
						<?php if ( $sendforce_is_current ) : ?>
							<span class="sendforce-changelog-current-badge"><?php esc_html_e( 'Current', 'sendforce-mail-relay' ); ?></span>
						<?php endif; ?>
						<span class="sendforce-changelog-summary-count">
							<?php
							/* translators: %d: number of changes in this release */
							echo esc_html( sprintf( _n( '%d change', '%d changes', count( $sendforce_release['bullets'] ), 'sendforce-mail-relay' ), count( $sendforce_release['bullets'] ) ) );
							?>
						</span>
					</summary>
					<?php if ( ! empty( $sendforce_release['bullets'] ) ) : ?>
						<ul class="sendforce-changelog-bullets">
							<?php foreach ( $sendforce_release['bullets'] as $sendforce_bullet ) : ?>
								<li><?php echo sendforce_format_changelog_line( $sendforce_bullet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already passed through wp_kses_post. ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		</div>

		<p class="sendforce-changelog-footnote">
			<a href="https://wordpress.org/plugins/sendforce-mail-relay/#developers" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View full changelog on WordPress.org', 'sendforce-mail-relay' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>
		</p>
	</div>
<?php endif; ?>

<!-- Footer: feedback CTA -->
<div class="sendforce-card sendforce-upcoming-cta">
	<div class="sendforce-upcoming-cta-inner">
		<div>
			<h2><?php esc_html_e( 'Have feedback?', 'sendforce-mail-relay' ); ?></h2>
			<p><?php esc_html_e( 'Bug reports, feature requests, or just letting us know what works — all of it helps.', 'sendforce-mail-relay' ); ?></p>
		</div>
		<div class="sendforce-upcoming-cta-actions">
			<a href="https://wordpress.org/support/plugin/sendforce-mail-relay/" target="_blank" rel="noopener noreferrer" class="sendforce-btn sendforce-btn-primary">
				<span class="dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'Suggest a feature', 'sendforce-mail-relay' ); ?>
			</a>
			<a href="https://wordpress.org/support/plugin/sendforce-mail-relay/reviews/" target="_blank" rel="noopener noreferrer" class="sendforce-btn sendforce-btn-outline">
				<span class="dashicons dashicons-star-filled"></span>
				<?php esc_html_e( 'Leave a review', 'sendforce-mail-relay' ); ?>
			</a>
		</div>
	</div>
</div>
