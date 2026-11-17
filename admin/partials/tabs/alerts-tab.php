<?php
/**
 * Alerts tab template.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_alerts_settings = SendForce_Alerts::get_alerts_settings();
$sendforce_summary_enabled = ! empty( $sendforce_alerts_settings['summary_enabled'] );
$sendforce_summary_emails  = $sendforce_alerts_settings['summary_emails'] ?? '{site_admin}';
$sendforce_summary_days    = $sendforce_alerts_settings['summary_days'] ?? array();
$sendforce_channels        = $sendforce_alerts_settings['channels'] ?? array();

$sendforce_all_days = array(
	'mon' => __( 'Mon', 'sendforce-mail-relay' ),
	'tue' => __( 'Tue', 'sendforce-mail-relay' ),
	'wed' => __( 'Wed', 'sendforce-mail-relay' ),
	'thu' => __( 'Thu', 'sendforce-mail-relay' ),
	'fri' => __( 'Fri', 'sendforce-mail-relay' ),
	'sat' => __( 'Sat', 'sendforce-mail-relay' ),
	'sun' => __( 'Sun', 'sendforce-mail-relay' ),
);

// Channel logos live in /assets/images/channels/<filename>.svg. Each SVG uses
// fill="currentColor" so .sendforce-channel-icon's CSS color (#fff) decides
// the glyph color, and the colored background of the circle comes from `color`.
$sendforce_channel_meta = array(
	'telegram' => array(
		'label' => 'Telegram',
		'color' => '#229ED9',
		'icon'  => 'telegram.svg',
		'fields' => array(
			'bot_token' => array(
				'label'       => __( 'Bot Token', 'sendforce-mail-relay' ),
				'type'        => 'text',
				'placeholder' => '123456:ABC-DEF...',
			),
			'chat_id'   => array(
				'label'       => __( 'Chat ID', 'sendforce-mail-relay' ),
				'type'        => 'text',
				'placeholder' => '-100123456789',
			),
		),
	),
	'slack'    => array(
		'label' => 'Slack',
		'color' => '#4A154B',
		'icon'  => 'slack.svg',
		'fields' => array(
			'webhook_url' => array(
				'label'       => __( 'Webhook URL', 'sendforce-mail-relay' ),
				'type'        => 'url',
				'placeholder' => 'https://hooks.slack.com/services/...',
			),
		),
	),
	'discord'  => array(
		'label' => 'Discord',
		'color' => '#5865F2',
		'icon'  => 'discord.svg',
		'fields' => array(
			'webhook_url' => array(
				'label'       => __( 'Webhook URL', 'sendforce-mail-relay' ),
				'type'        => 'url',
				'placeholder' => 'https://discord.com/api/webhooks/...',
			),
		),
	),
	'pushover' => array(
		'label' => 'Pushover',
		'color' => '#E8002D',
		'icon'  => 'pushover.svg',
		'fields' => array(
			'user_key'  => array(
				'label'       => __( 'User Key', 'sendforce-mail-relay' ),
				'type'        => 'text',
				'placeholder' => 'your_user_key',
			),
			'api_token' => array(
				'label'       => __( 'App API Token', 'sendforce-mail-relay' ),
				'type'        => 'text',
				'placeholder' => 'your_app_token',
			),
		),
	),
	'teams'       => array(
		'label'  => 'Microsoft Teams',
		'color'  => '#6264A7',
		'icon'   => 'teams.svg',
		'fields' => array(
			'webhook_url' => array(
				'label'       => __( 'Webhook URL', 'sendforce-mail-relay' ),
				'type'        => 'url',
				'placeholder' => 'https://outlook.office.com/webhook/...',
			),
		),
	),
	'google_chat' => array(
		'label'  => 'Google Chat',
		'color'  => '#00AC47',
		'icon'   => 'google-chat.svg',
		'fields' => array(
			'webhook_url' => array(
				'label'       => __( 'Webhook URL', 'sendforce-mail-relay' ),
				'type'        => 'url',
				'placeholder' => 'https://chat.googleapis.com/v1/spaces/...',
			),
		),
	),
	'webhook'     => array(
		'label'  => 'Custom Webhook',
		'color'  => '#6B7280',
		'icon'   => 'webhook.svg',
		'fields' => array(
			'webhook_url' => array(
				'label'       => __( 'Webhook URL', 'sendforce-mail-relay' ),
				'type'        => 'url',
				'placeholder' => 'https://example.com/webhook',
			),
		),
	),
);

// Whitelist for wp_kses when echoing the icon SVGs below.
$sendforce_channel_svg_kses = array(
	'svg'    => array( 'viewbox' => true, 'viewBox' => true, 'xmlns' => true, 'xmlns:xlink' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'aria-label' => true, 'role' => true, 'focusable' => true, 'width' => true, 'height' => true ),
	'path'   => array( 'd' => true, 'fill' => true, 'fill-rule' => true, 'clip-rule' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
	'circle' => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
	'g'      => array( 'fill' => true, 'stroke' => true, 'transform' => true ),
);

/**
 * Read a channel SVG file and return its contents. Cached per request so we
 * don't hit disk multiple times even though only ~7 channels render today.
 *
 * @param string $filename Just the basename (e.g. "telegram.svg") — NOT a path.
 * @return string SVG markup, or '' when the file is missing.
 */
if ( ! function_exists( 'sendforce_get_channel_svg' ) ) {
	function sendforce_get_channel_svg( $filename ) {
		static $cache = array();
		$filename = basename( $filename ); // defense-in-depth: no path traversal.
		if ( isset( $cache[ $filename ] ) ) {
			return $cache[ $filename ];
		}
		$path = SENDFORCE_PLUGIN_DIR . 'assets/images/channels/' . $filename;
		$cache[ $filename ] = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $cache[ $filename ];
	}
}
?>

<div class="sendforce-page-header">
	<div>
		<h2><?php esc_html_e( 'Alerts', 'sendforce-mail-relay' ); ?></h2>
		<p class="sendforce-page-subtitle"><?php esc_html_e( 'Configure summary reports and real-time error notifications.', 'sendforce-mail-relay' ); ?></p>
	</div>
</div>

<div class="sendforce-alerts-layout">

	<!-- ===== LEFT: Summary Email ===== -->
	<div class="sendforce-card">
		<div class="sendforce-card-header">
			<h3><?php esc_html_e( 'Summary Email', 'sendforce-mail-relay' ); ?></h3>
		</div>
		<div class="sendforce-card-body">
			<form method="post" action="options.php" class="sendforce-alerts-summary-form">
				<?php settings_fields( 'sendforce_alerts_group' ); ?>

				<!-- Enable toggle -->
				<div class="sendforce-form-row sendforce-form-row-toggle">
					<label class="sendforce-toggle-label" for="sendforce_summary_enabled">
						<span class="sendforce-toggle-wrap">
							<input
								type="checkbox"
								id="sendforce_summary_enabled"
								name="sendforce_alerts[summary_enabled]"
								value="1"
								<?php checked( $sendforce_summary_enabled, true ); ?>
								class="sendforce-toggle-input"
							>
							<span class="sendforce-toggle-slider"></span>
						</span>
						<span class="sendforce-toggle-text"><?php esc_html_e( 'Enable Email Summary', 'sendforce-mail-relay' ); ?></span>
					</label>
				</div>

				<!-- Notification email addresses -->
				<div class="sendforce-form-row" style="margin-top:20px;">
					<label class="sendforce-form-label" for="sendforce_summary_emails">
						<?php esc_html_e( 'Notification Email Addresses', 'sendforce-mail-relay' ); ?>
					</label>
					<textarea
						id="sendforce_summary_emails"
						name="sendforce_alerts[summary_emails]"
						class="sendforce-input sendforce-textarea"
						rows="2"
						placeholder="<?php esc_attr_e( '{site_admin}', 'sendforce-mail-relay' ); ?>"
					><?php echo esc_textarea( $sendforce_summary_emails ); ?></textarea>
					<p class="sendforce-help">
						<?php esc_html_e( 'One email per line, or comma-separated. Use {site_admin} as a token for the WordPress admin email address.', 'sendforce-mail-relay' ); ?>
					</p>
				</div>

				<!-- Notification days -->
				<div class="sendforce-form-row" style="margin-top:20px;">
					<label class="sendforce-form-label">
						<?php esc_html_e( 'Notification Days', 'sendforce-mail-relay' ); ?>
					</label>
					<div class="sendforce-alerts-days">
						<?php foreach ( $sendforce_all_days as $sendforce_day_key => $sendforce_day_label ) : ?>
							<label class="sendforce-day-checkbox">
								<input
									type="checkbox"
									name="sendforce_alerts[summary_days][]"
									value="<?php echo esc_attr( $sendforce_day_key ); ?>"
									<?php checked( in_array( $sendforce_day_key, $sendforce_summary_days, true ), true ); ?>
								>
								<span class="sendforce-day-label"><?php echo esc_html( $sendforce_day_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div style="margin-top:24px;">
					<button type="submit" name="submit" class="sendforce-btn sendforce-btn-primary">
						<?php esc_html_e( 'Save Settings', 'sendforce-mail-relay' ); ?>
					</button>
				</div>

			</form>
		</div>
	</div>

	<!-- ===== RIGHT: Error Notification Channels ===== -->
	<div class="sendforce-card">
		<div class="sendforce-card-header">
			<h3><?php esc_html_e( 'Email Sending Error Notifications', 'sendforce-mail-relay' ); ?></h3>
			<p class="sendforce-card-subtitle" style="margin:4px 0 0;font-size:13px;color:var(--sf-text-secondary);">
				<?php esc_html_e( 'Get instant alerts when an email fails to send. Configure one or more channels below.', 'sendforce-mail-relay' ); ?>
			</p>
		</div>
		<div class="sendforce-card-body" style="padding-top:0;">

			<div class="sendforce-channels-list">
				<?php foreach ( $sendforce_channel_meta as $sendforce_channel_key => $sendforce_meta ) :
					$sendforce_ch_settings = $sendforce_channels[ $sendforce_channel_key ] ?? array();
					$sendforce_ch_enabled  = ! empty( $sendforce_ch_settings['enabled'] );
				?>
				<div class="sendforce-channel-row" data-channel="<?php echo esc_attr( $sendforce_channel_key ); ?>">
					<div
						class="sendforce-channel-icon"
						style="background:<?php echo esc_attr( $sendforce_meta['color'] ); ?>;"
						aria-label="<?php echo esc_attr( $sendforce_meta['label'] ); ?>"
					>
						<?php echo wp_kses( sendforce_get_channel_svg( $sendforce_meta['icon'] ), $sendforce_channel_svg_kses ); ?>
					</div>
					<div class="sendforce-channel-info">
						<span class="sendforce-channel-name"><?php echo esc_html( $sendforce_meta['label'] ); ?></span>
					</div>
					<div class="sendforce-channel-status">
						<label class="sendforce-toggle-label sendforce-toggle-label-sm" title="<?php esc_attr_e( 'Enable/Disable', 'sendforce-mail-relay' ); ?>">
							<span class="sendforce-toggle-wrap">
								<input
									type="checkbox"
									class="sendforce-toggle-input sendforce-channel-toggle"
									data-channel="<?php echo esc_attr( $sendforce_channel_key ); ?>"
									<?php checked( $sendforce_ch_enabled, true ); ?>
								>
								<span class="sendforce-toggle-slider"></span>
							</span>
						</label>
					</div>
					<div class="sendforce-channel-actions">
						<button
							type="button"
							class="sendforce-btn sendforce-btn-primary sendforce-btn-sm sendforce-configure-channel"
							data-channel="<?php echo esc_attr( $sendforce_channel_key ); ?>"
							data-label="<?php echo esc_attr( $sendforce_meta['label'] ); ?>"
							data-color="<?php echo esc_attr( $sendforce_meta['color'] ); ?>"
						>
							<?php esc_html_e( 'Configure', 'sendforce-mail-relay' ); ?>
						</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

		</div>
	</div>

</div><!-- .sendforce-alerts-layout -->

<!-- ===== Channel Configuration Modal ===== -->
<div id="sendforce-channel-modal" class="sendforce-modal" style="display:none;">
	<div class="sendforce-modal-overlay"></div>
	<div class="sendforce-modal-content sendforce-modal-content-sm">
		<div class="sendforce-modal-header">
			<h3 id="sendforce-channel-modal-title"><?php esc_html_e( 'Configure Channel', 'sendforce-mail-relay' ); ?></h3>
			<button type="button" class="sendforce-modal-close sendforce-channel-modal-close">&times;</button>
		</div>
		<div class="sendforce-modal-body">
			<input type="hidden" id="sendforce-channel-key" value="">

			<!-- Telegram fields -->
			<div class="sendforce-channel-fields" data-for="telegram" style="display:none;">
				<div class="sendforce-form-row" style="margin-bottom:16px;">
					<label class="sendforce-form-label" for="sendforce-telegram-bot-token">
						<?php esc_html_e( 'Bot Token', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="text"
						id="sendforce-telegram-bot-token"
						class="sendforce-input"
						placeholder="123456:ABC-DEF..."
					>
				</div>
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-telegram-chat-id">
						<?php esc_html_e( 'Chat ID', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="text"
						id="sendforce-telegram-chat-id"
						class="sendforce-input"
						placeholder="-100123456789"
					>
				</div>
			</div>

			<!-- Slack fields -->
			<div class="sendforce-channel-fields" data-for="slack" style="display:none;">
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-slack-webhook">
						<?php esc_html_e( 'Webhook URL', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="url"
						id="sendforce-slack-webhook"
						class="sendforce-input"
						placeholder="https://hooks.slack.com/services/..."
					>
				</div>
			</div>

			<!-- Discord fields -->
			<div class="sendforce-channel-fields" data-for="discord" style="display:none;">
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-discord-webhook">
						<?php esc_html_e( 'Webhook URL', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="url"
						id="sendforce-discord-webhook"
						class="sendforce-input"
						placeholder="https://discord.com/api/webhooks/..."
					>
				</div>
			</div>

			<!-- Pushover fields -->
			<div class="sendforce-channel-fields" data-for="pushover" style="display:none;">
				<div class="sendforce-form-row" style="margin-bottom:16px;">
					<label class="sendforce-form-label" for="sendforce-pushover-user-key">
						<?php esc_html_e( 'User Key', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="text"
						id="sendforce-pushover-user-key"
						class="sendforce-input"
						placeholder="your_user_key"
					>
				</div>
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-pushover-api-token">
						<?php esc_html_e( 'App API Token', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="text"
						id="sendforce-pushover-api-token"
						class="sendforce-input"
						placeholder="your_app_token"
					>
				</div>
			</div>

			<!-- Microsoft Teams fields -->
			<div class="sendforce-channel-fields" data-for="teams" style="display:none;">
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-teams-webhook">
						<?php esc_html_e( 'Webhook URL', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="url"
						id="sendforce-teams-webhook"
						class="sendforce-input"
						placeholder="https://outlook.office.com/webhook/..."
					>
				</div>
			</div>

			<!-- Google Chat fields -->
			<div class="sendforce-channel-fields" data-for="google_chat" style="display:none;">
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-google-chat-webhook">
						<?php esc_html_e( 'Webhook URL', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="url"
						id="sendforce-google-chat-webhook"
						class="sendforce-input"
						placeholder="https://chat.googleapis.com/v1/spaces/..."
					>
				</div>
			</div>

			<!-- Custom Webhook fields -->
			<div class="sendforce-channel-fields" data-for="webhook" style="display:none;">
				<div class="sendforce-form-row">
					<label class="sendforce-form-label" for="sendforce-webhook-url">
						<?php esc_html_e( 'Webhook URL', 'sendforce-mail-relay' ); ?>
					</label>
					<input
						type="url"
						id="sendforce-webhook-url"
						class="sendforce-input"
						placeholder="https://example.com/webhook"
					>
				</div>
			</div>

			<div id="sendforce-channel-modal-notice" style="display:none;margin-top:14px;" class="sendforce-notice"></div>
		</div>
		<div class="sendforce-modal-footer">
			<button type="button" id="sendforce-channel-test" class="sendforce-btn sendforce-btn-outline sendforce-btn-sm">
				<?php esc_html_e( 'Send Test', 'sendforce-mail-relay' ); ?>
			</button>
			<button type="button" id="sendforce-channel-save" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm">
				<?php esc_html_e( 'Save', 'sendforce-mail-relay' ); ?>
			</button>
		</div>
	</div>
</div>

<?php
// Pre-populate saved channel values as JSON for the admin JS.
// Done via wp_add_inline_script so it counts as a properly enqueued asset
// (no raw <script> tag in the page output).
//
// The JSON_HEX_* flags are the escaping for this output. Channel settings hold
// user-supplied secrets that are deliberately not tag-stripped on input (see
// SendForce_Admin::ajax_save_channel()), so the angle brackets have to be
// neutralised here instead: without JSON_HEX_TAG a value containing
// `<!--<script>` can confuse the HTML parser into swallowing the closing
// </script> tag. This matches how core encodes wp_localize_script() data.
wp_add_inline_script(
	'sendforce-admin',
	'var sendForceChannelData = ' . wp_json_encode( $sendforce_channels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
	'before'
);
?>
