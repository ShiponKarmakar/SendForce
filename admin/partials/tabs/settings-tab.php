<?php
/**
 * Settings tab template.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sendforce_settings            = get_option( 'sendforce_settings', array() );
$sendforce_providers           = SendForce_Providers::get_providers();
$sendforce_ses_regions         = SendForce_Providers::get_ses_regions();
$sendforce_default_connection  = $sendforce_settings['default_connection'] ?? '';
$sendforce_fallback_connection = $sendforce_settings['fallback_connection'] ?? '';

// Auto-migrate legacy SMTP settings to a connection entry (one-time, on first visit).
$sendforce_connections     = get_option( 'sendforce_connections', array() );
$sendforce_existing_ids    = array_column( $sendforce_connections, 'id' );
$sendforce_has_conn_primary = in_array( 'conn_primary', $sendforce_existing_ids, true );

if ( empty( $sendforce_connections ) && ! empty( $sendforce_settings['host'] ) && ! $sendforce_has_conn_primary ) {
	$sendforce_legacy_conn = array(
		'id'                   => 'conn_primary',
		'label'                => __( 'Primary Connection', 'sendforce-mail-relay' ),
		'provider'             => $sendforce_settings['provider'] ?? 'other',
		'connection_type'      => $sendforce_settings['connection_type'] ?? 'smtp',
		'host'                 => $sendforce_settings['host'] ?? '',
		'port'                 => $sendforce_settings['port'] ?? 587,
		'encryption'           => $sendforce_settings['encryption'] ?? 'tls',
		'auth'                 => $sendforce_settings['auth'] ?? true,
		'username'             => $sendforce_settings['username'] ?? '',
		'password'             => $sendforce_settings['password'] ?? '',
		'api_key'              => $sendforce_settings['api_key'] ?? '',
		'ses_region'           => $sendforce_settings['ses_region'] ?? 'us-east-1',
		'ses_access_key'       => $sendforce_settings['ses_access_key'] ?? '',
		'mailgun_domain'       => $sendforce_settings['mailgun_domain'] ?? '',
		'mailgun_region'       => $sendforce_settings['mailgun_region'] ?? 'us',
		'smtpcom_channel'      => $sendforce_settings['smtpcom_channel'] ?? '',
		'socketlabs_server_id' => $sendforce_settings['socketlabs_server_id'] ?? '',
	);
	$sendforce_connections = array( $sendforce_legacy_conn );
	update_option( 'sendforce_connections', $sendforce_connections );

	if ( empty( $sendforce_default_connection ) ) {
		$sendforce_default_connection              = 'conn_primary';
		$sendforce_settings['default_connection']  = 'conn_primary';
		update_option( 'sendforce_settings', array_merge( get_option( 'sendforce_settings', array() ), array( 'default_connection' => 'conn_primary' ) ) );
	}
}

// Deduplicate connections by ID (safety guard).
$sendforce_seen_ids    = array();
$sendforce_connections = array_values(
	array_filter(
		$sendforce_connections,
		function ( $c ) use ( &$sendforce_seen_ids ) {
			if ( ! isset( $c['id'] ) || in_array( $c['id'], $sendforce_seen_ids, true ) ) {
				return false;
			}
			$sendforce_seen_ids[] = $c['id'];
			return true;
		}
	)
);
?>

<div class="sendforce-page-header">
	<div>
		<h1><?php esc_html_e( 'Settings', 'sendforce-mail-relay' ); ?></h1>
		<p class="sendforce-subtitle"><?php esc_html_e( 'Manage your email connections and sending preferences', 'sendforce-mail-relay' ); ?></p>
	</div>
</div>

<div class="sendforce-settings-columns">

	<!-- Left column: Connection management (AJAX — not inside options.php form) -->
	<div class="sendforce-settings-main">

		<?php if ( ! empty( $sendforce_connections ) ) : ?>
		<!-- Active Email Connections table — shown only when connections exist -->
		<div class="sendforce-card" id="sendforce-connections-card">
			<div class="sendforce-card-header">
				<h2><?php esc_html_e( 'Active Email Connections', 'sendforce-mail-relay' ); ?></h2>
				<button type="button" id="sendforce-add-connection-btn" class="sendforce-btn sendforce-btn-primary sendforce-btn-sm">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;line-height:1.4;"></span>
					<?php esc_html_e( 'Add Connection', 'sendforce-mail-relay' ); ?>
				</button>
			</div>
			<div class="sendforce-card-body sendforce-no-pad">
				<table class="sendforce-connections-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'sendforce-mail-relay' ); ?></th>
							<th><?php esc_html_e( 'Type', 'sendforce-mail-relay' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="sendforce-connections-tbody">
						<?php foreach ( $sendforce_connections as $sendforce_conn ) :
							$sendforce_conn_provider = $sendforce_conn['provider'] ?? 'other';
							$sendforce_pdata         = SendForce_Providers::get_provider( $sendforce_conn_provider );
							$sendforce_conn_label    = ! empty( $sendforce_conn['label'] ) ? $sendforce_conn['label'] : ( $sendforce_pdata['label'] ?? ucfirst( $sendforce_conn_provider ) );
							$sendforce_is_default    = $sendforce_default_connection === $sendforce_conn['id'];
							$sendforce_is_fallback   = $sendforce_fallback_connection === $sendforce_conn['id'];
							$sendforce_conn_type     = strtoupper( $sendforce_conn['connection_type'] ?? 'SMTP' );
						?>
						<tr data-conn-id="<?php echo esc_attr( $sendforce_conn['id'] ); ?>">
							<td>
								<strong><?php echo esc_html( $sendforce_conn_label ); ?></strong>
								<?php if ( $sendforce_is_default ) : ?>
									<span class="sendforce-conn-badge sendforce-conn-badge-active"><?php esc_html_e( 'Primary', 'sendforce-mail-relay' ); ?></span>
								<?php endif; ?>
								<?php if ( $sendforce_is_fallback ) : ?>
									<span class="sendforce-conn-badge sendforce-conn-badge-fallback"><?php esc_html_e( 'Fallback', 'sendforce-mail-relay' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $sendforce_pdata['label'] ?? ucfirst( $sendforce_conn_provider ) ); ?></td>
							<td><?php echo esc_html( $sendforce_conn_type ); ?></td>
							<td class="sendforce-conn-actions">
								<button type="button" class="sendforce-action-btn sendforce-action-btn-edit sendforce-edit-connection"
									data-id="<?php echo esc_attr( $sendforce_conn['id'] ); ?>"
									aria-label="<?php esc_attr_e( 'Edit connection', 'sendforce-mail-relay' ); ?>"
									title="<?php esc_attr_e( 'Edit', 'sendforce-mail-relay' ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<span class="sendforce-action-btn-label"><?php esc_html_e( 'Edit', 'sendforce-mail-relay' ); ?></span>
								</button>
								<button type="button" class="sendforce-action-btn sendforce-action-btn-delete sendforce-delete-connection"
									data-id="<?php echo esc_attr( $sendforce_conn['id'] ); ?>"
									aria-label="<?php esc_attr_e( 'Delete connection', 'sendforce-mail-relay' ); ?>"
									title="<?php esc_attr_e( 'Delete', 'sendforce-mail-relay' ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									<span class="sendforce-action-btn-label"><?php esc_html_e( 'Delete', 'sendforce-mail-relay' ); ?></span>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div><!-- /#sendforce-connections-card -->
		<?php endif; ?>

		<!-- Add/Edit Connection form.
		     Hidden (via CSS) when connections exist; visible by default when no connections. -->
		<div class="sendforce-card" id="sendforce-connection-form-card"
			<?php if ( ! empty( $sendforce_connections ) ) echo 'style="display:none;"'; ?>>
			<div class="sendforce-card-header">
				<h2 id="sendforce-conn-form-title">
					<?php empty( $sendforce_connections ) ? esc_html_e( 'Choose Your Email Channel', 'sendforce-mail-relay' ) : esc_html_e( 'Add Connection', 'sendforce-mail-relay' ); ?>
				</h2>
				<?php if ( empty( $sendforce_connections ) ) : ?>
				<p class="sendforce-text-muted" style="font-size:13px;margin:0;">
					<?php esc_html_e( 'Select a provider below to configure your first email connection.', 'sendforce-mail-relay' ); ?>
				</p>
				<?php endif; ?>
			</div><!-- /.sendforce-card-header -->
			<div class="sendforce-card-body">
				<input type="hidden" id="sf-conn-id" value="" />

				<!-- Connection Label -->
				<div class="sendforce-field" style="margin-bottom:20px;">
					<label for="sf-conn-label" class="sendforce-label"><?php esc_html_e( 'Connection Name', 'sendforce-mail-relay' ); ?></label>
					<input type="text" id="sf-conn-label" class="sendforce-input" style="max-width:360px;"
						placeholder="<?php esc_attr_e( 'e.g. Primary Gmail', 'sendforce-mail-relay' ); ?>" />
					<span class="sendforce-help"><?php esc_html_e( 'A friendly name to identify this connection.', 'sendforce-mail-relay' ); ?></span>
				</div>

				<!-- Provider Selection -->
				<div class="sendforce-field" style="margin-bottom:8px;">
					<label class="sendforce-label"><?php esc_html_e( 'Provider', 'sendforce-mail-relay' ); ?></label>
				</div>
				<input type="hidden" id="sf-conn-provider" value="other" />
				<div class="sendforce-provider-grid-wrap collapsed" id="sendforce-provider-grid-wrap">
					<div class="sendforce-provider-grid" id="sendforce-provider-grid">
						<?php
						$sendforce_provider_index = 0;
						foreach ( $sendforce_providers as $sendforce_key => $sendforce_provider ) :
							$sendforce_svg_file = SENDFORCE_PLUGIN_DIR . 'assets/images/providers/' . $sendforce_key . '.svg';
							$sendforce_has_svg  = file_exists( $sendforce_svg_file );
							$sendforce_provider_index++;
						?>
							<button type="button"
								class="sendforce-provider-card <?php echo 'other' === $sendforce_key ? 'active' : ''; ?>"
								data-provider="<?php echo esc_attr( $sendforce_key ); ?>"
								data-api="<?php echo ! empty( $sendforce_provider['api_supported'] ) ? '1' : '0'; ?>"
								<?php if ( $sendforce_provider_index > 15 ) { echo 'data-extra="1"'; } ?>>
								<?php if ( $sendforce_has_svg ) : ?>
									<span class="sendforce-provider-icon sendforce-provider-icon-svg">
										<img src="<?php echo esc_url( SENDFORCE_PLUGIN_URL . 'assets/images/providers/' . $sendforce_key . '.svg' ); ?>" alt="<?php echo esc_attr( $sendforce_provider['label'] ); ?>" />
									</span>
								<?php else : ?>
									<span class="sendforce-provider-icon" style="background:<?php echo esc_attr( $sendforce_provider['color'] ); ?>;">
										<?php echo esc_html( $sendforce_provider['icon'] ); ?>
									</span>
								<?php endif; ?>
								<span class="sendforce-provider-name"><?php echo esc_html( $sendforce_provider['label'] ); ?></span>
								<?php if ( ! empty( $sendforce_provider['api_supported'] ) ) : ?>
									<span class="sendforce-provider-api-badge">API</span>
								<?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>
					<?php if ( $sendforce_provider_index > 15 ) : ?>
						<div class="sendforce-provider-expand-wrap">
							<button type="button" class="sendforce-provider-expand-btn" id="sendforce-provider-expand">
								<span class="sendforce-expand-text"><?php esc_html_e( 'Show All Providers', 'sendforce-mail-relay' ); ?></span>
								<svg class="sendforce-expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="6 9 12 15 18 9"></polyline>
								</svg>
							</button>
						</div>
					<?php endif; ?>
				</div>

				<!-- Connection Type tabs (shown only for API-capable providers) -->
				<div class="sendforce-card sendforce-connection-type-card" style="display:none;margin-top:16px;">
					<div class="sendforce-card-body">
						<input type="hidden" id="sf-conn-type" value="smtp" />
						<div class="sendforce-conn-tabs">
							<button type="button" class="sendforce-conn-tab active" data-type="smtp">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'SMTP', 'sendforce-mail-relay' ); ?>
							</button>
							<button type="button" class="sendforce-conn-tab" data-type="api">
								<span class="dashicons dashicons-rest-api"></span>
								<?php esc_html_e( 'API', 'sendforce-mail-relay' ); ?>
							</button>
						</div>
						<p class="sendforce-text-muted sendforce-conn-desc" id="sf-conn-desc">
							<?php esc_html_e( 'Send emails via the traditional SMTP protocol.', 'sendforce-mail-relay' ); ?>
						</p>
					</div>
				</div>

				<!-- SMTP Configuration -->
				<div class="sendforce-card sendforce-smtp-config-form" style="margin-top:16px;">
					<div class="sendforce-card-header">
						<h2><?php esc_html_e( 'SMTP Configuration', 'sendforce-mail-relay' ); ?></h2>
					</div>
					<div class="sendforce-card-body">
						<!-- Custom SMTP fields — visible by default for "Other SMTP", hidden for preset providers -->
						<div class="sendforce-form-grid sendforce-custom-smtp-fields">
							<div class="sendforce-field">
								<label for="sf-conn-host" class="sendforce-label"><?php esc_html_e( 'SMTP Host', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-host" class="sendforce-input" placeholder="smtp.example.com" />
							</div>
							<div class="sendforce-field">
								<label for="sf-conn-port" class="sendforce-label"><?php esc_html_e( 'Port', 'sendforce-mail-relay' ); ?></label>
								<input type="number" id="sf-conn-port" class="sendforce-input" value="587" min="1" max="65535" />
							</div>
							<div class="sendforce-field">
								<label for="sf-conn-enc" class="sendforce-label"><?php esc_html_e( 'Encryption', 'sendforce-mail-relay' ); ?></label>
								<select id="sf-conn-enc" class="sendforce-select">
									<option value="none"><?php esc_html_e( 'None', 'sendforce-mail-relay' ); ?></option>
									<option value="ssl"><?php esc_html_e( 'SSL', 'sendforce-mail-relay' ); ?></option>
									<option value="tls" selected><?php esc_html_e( 'TLS', 'sendforce-mail-relay' ); ?></option>
								</select>
							</div>
							<div class="sendforce-field">
								<label class="sendforce-toggle-label">
									<input type="checkbox" id="sf-conn-auth" class="sendforce-toggle-input" checked />
									<span class="sendforce-toggle"></span>
									<span><?php esc_html_e( 'Authentication Required', 'sendforce-mail-relay' ); ?></span>
								</label>
							</div>
						</div>

						<!-- SES Region (SMTP mode) -->
						<div class="sendforce-form-grid sendforce-ses-region-field" style="display:none;">
							<div class="sendforce-field sendforce-field-full">
								<label for="sf-conn-ses-region" class="sendforce-label"><?php esc_html_e( 'SES Region', 'sendforce-mail-relay' ); ?></label>
								<select id="sf-conn-ses-region" class="sendforce-select">
									<?php foreach ( $sendforce_ses_regions as $sendforce_rk => $sendforce_rn ) : ?>
										<option value="<?php echo esc_attr( $sendforce_rk ); ?>"><?php echo esc_html( $sendforce_rn . ' / ' . $sendforce_rk ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<!-- Username / Password -->
						<div class="sendforce-form-grid" style="margin-top:12px;">
							<div class="sendforce-field">
								<label for="sf-conn-username" class="sendforce-label" id="sf-conn-username-label">
									<?php esc_html_e( 'Username', 'sendforce-mail-relay' ); ?>
								</label>
								<input type="text" id="sf-conn-username" class="sendforce-input" autocomplete="off" />
								<span id="sf-conn-username-help" class="sendforce-help" style="display:none;"></span>
							</div>
							<div class="sendforce-field">
								<label for="sf-conn-password" class="sendforce-label" id="sf-conn-password-label">
									<?php esc_html_e( 'Password', 'sendforce-mail-relay' ); ?>
								</label>
								<input type="password" id="sf-conn-password" class="sendforce-input" autocomplete="new-password" />
								<span id="sf-conn-password-help" class="sendforce-help" style="display:none;">
									<?php esc_html_e( 'Saved. Leave blank to keep current password.', 'sendforce-mail-relay' ); ?>
								</span>
							</div>
						</div>
					</div>
				</div><!-- /.sendforce-smtp-config-form -->

				<!-- API Configuration -->
				<div class="sendforce-card sendforce-api-config-form" style="display:none;margin-top:16px;">
					<div class="sendforce-card-header">
						<h2><?php esc_html_e( 'API Configuration', 'sendforce-mail-relay' ); ?></h2>
					</div>
					<div class="sendforce-card-body">
						<div class="sendforce-form-grid">
							<div class="sendforce-field sendforce-field-full">
								<label for="sf-conn-apikey" class="sendforce-label" id="sf-conn-apikey-label">
									<?php esc_html_e( 'API Key', 'sendforce-mail-relay' ); ?>
								</label>
								<input type="password" id="sf-conn-apikey" class="sendforce-input" autocomplete="new-password" />
								<span id="sf-conn-apikey-help" class="sendforce-help" style="display:none;">
									<?php esc_html_e( 'Saved. Leave blank to keep current API key.', 'sendforce-mail-relay' ); ?>
								</span>
								<span id="sf-conn-apikey-guid-warning" class="sendforce-help" style="display:none;color:#b32d2e;font-weight:600;margin-top:4px;">
									<span class="dashicons dashicons-warning" style="vertical-align:text-bottom;"></span>
									<?php esc_html_e( 'This looks like a GUID. For Microsoft 365, make sure you copied the Client Secret VALUE column (random characters with ~ and .), not the Secret ID column (a GUID). Microsoft client secrets are never GUIDs.', 'sendforce-mail-relay' ); ?>
								</span>
							</div>

							<!-- SES: Access Key ID -->
							<div class="sendforce-field sf-conn-api-ses" style="display:none;">
								<label for="sf-conn-ses-access-key" class="sendforce-label"><?php esc_html_e( 'Access Key ID', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-ses-access-key" class="sendforce-input" />
							</div>
							<div class="sendforce-field sf-conn-api-ses" style="display:none;">
								<label for="sf-conn-ses-api-region" class="sendforce-label"><?php esc_html_e( 'Region', 'sendforce-mail-relay' ); ?></label>
								<select id="sf-conn-ses-api-region" class="sendforce-select">
									<?php foreach ( $sendforce_ses_regions as $sendforce_rk => $sendforce_rn ) : ?>
										<option value="<?php echo esc_attr( $sendforce_rk ); ?>"><?php echo esc_html( $sendforce_rn . ' / ' . $sendforce_rk ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Mailgun -->
							<div class="sendforce-field sf-conn-api-mailgun" style="display:none;">
								<label for="sf-conn-mailgun-domain" class="sendforce-label"><?php esc_html_e( 'Domain Name', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-mailgun-domain" class="sendforce-input" placeholder="mg.example.com" />
							</div>
							<div class="sendforce-field sf-conn-api-mailgun" style="display:none;">
								<label for="sf-conn-mailgun-region" class="sendforce-label"><?php esc_html_e( 'Region', 'sendforce-mail-relay' ); ?></label>
								<select id="sf-conn-mailgun-region" class="sendforce-select">
									<option value="us"><?php esc_html_e( 'US', 'sendforce-mail-relay' ); ?></option>
									<option value="eu"><?php esc_html_e( 'EU', 'sendforce-mail-relay' ); ?></option>
								</select>
							</div>

							<!-- SMTP.com Channel -->
							<div class="sendforce-field sf-conn-api-smtpcom" style="display:none;">
								<label for="sf-conn-smtpcom-channel" class="sendforce-label"><?php esc_html_e( 'Channel', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-smtpcom-channel" class="sendforce-input" placeholder="default" />
							</div>

							<!-- SocketLabs Server ID -->
							<div class="sendforce-field sf-conn-api-socketlabs" style="display:none;">
								<label for="sf-conn-socketlabs-id" class="sendforce-label"><?php esc_html_e( 'Server ID', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-socketlabs-id" class="sendforce-input" />
							</div>

							<!-- Microsoft 365: Tenant ID -->
							<div class="sendforce-field sf-conn-api-outlook" style="display:none;">
								<label for="sf-conn-outlook-tenant-id" class="sendforce-label"><?php esc_html_e( 'Tenant ID', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-outlook-tenant-id" class="sendforce-input" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
							</div>

							<!-- Microsoft 365: Application (Client) ID -->
							<div class="sendforce-field sf-conn-api-outlook" style="display:none;">
								<label for="sf-conn-outlook-client-id" class="sendforce-label"><?php esc_html_e( 'Application (Client) ID', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-outlook-client-id" class="sendforce-input" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
							</div>

							<!-- Gmail: Client ID -->
							<div class="sendforce-field sf-conn-api-gmail" style="display:none;">
								<label for="sf-conn-gmail-client-id" class="sendforce-label"><?php esc_html_e( 'Client ID', 'sendforce-mail-relay' ); ?></label>
								<input type="text" id="sf-conn-gmail-client-id" class="sendforce-input" placeholder="xxxx.apps.googleusercontent.com" />
							</div>

							<!-- Gmail / Microsoft 365: Connect with OAuth -->
							<?php
							$sendforce_oauth_redirect = SendForce_OAuth::get_redirect_uri();
							// JS origin = just protocol + host, no path. Used by Google's
							// "Authorised JavaScript origins" field which rejects paths.
							$sendforce_oauth_js_origin = untrailingslashit( site_url() );
							?>
							<div class="sendforce-field sendforce-field-full sf-conn-oauth" style="display:none;">
								<div class="sendforce-oauth-box">
									<div class="sendforce-oauth-status" id="sf-conn-oauth-status">
										<span class="sendforce-oauth-status-text"><?php esc_html_e( 'Not connected.', 'sendforce-mail-relay' ); ?></span>
									</div>
									<div class="sendforce-oauth-actions">
										<button type="button" id="sf-conn-oauth-connect" class="sendforce-btn sendforce-btn-primary">
											<?php esc_html_e( 'Connect with provider', 'sendforce-mail-relay' ); ?>
										</button>
										<button type="button" id="sf-conn-oauth-disconnect" class="sendforce-btn sendforce-btn-outline" style="display:none;">
											<?php esc_html_e( 'Disconnect', 'sendforce-mail-relay' ); ?>
										</button>
									</div>
								</div>

								<details class="sendforce-oauth-help" style="margin-top:14px;">
									<summary style="cursor:pointer;font-weight:600;color:var(--sf-text-secondary);"><?php esc_html_e( 'Setup help — where do I get the Client ID and Client Secret?', 'sendforce-mail-relay' ); ?></summary>

									<div class="sf-conn-api-gmail" style="margin-top:10px;">
										<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Gmail / Google Workspace', 'sendforce-mail-relay' ); ?></strong></p>
										<ol style="margin:0 0 10px 18px;padding:0;">
											<li><?php echo wp_kses_post( __( 'Open <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> and create (or pick) a project.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( 'Enable the <strong>Gmail API</strong> for that project (APIs &amp; Services → Library → search "Gmail API" → Enable).', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( 'Configure the <strong>OAuth consent screen</strong> (APIs &amp; Services → OAuth consent screen, or Google Auth platform → Branding on newer accounts): pick <strong>External</strong> user type, fill in App name + your email, save. Then under <strong>Audience → Test users</strong> add every Gmail/Workspace address you intend to send from. Without this, sign-in will return <code>access_denied</code> "App is being tested".', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( 'Open <strong>APIs &amp; Services → Credentials → + Create Credentials → OAuth client ID</strong>. Application type: <strong>Web application</strong>. Name it anything.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php esc_html_e( 'In Authorised JavaScript origins, add (origin only — no path, no trailing slash):', 'sendforce-mail-relay' ); ?>
												<input type="text" readonly value="<?php echo esc_attr( $sendforce_oauth_js_origin ); ?>" class="sendforce-input" style="margin-top:4px;font-size:12px;" onclick="this.select();" />
											</li>
											<li><?php esc_html_e( 'In Authorised redirect URIs, add this full URL:', 'sendforce-mail-relay' ); ?>
												<input type="text" readonly value="<?php echo esc_attr( $sendforce_oauth_redirect ); ?>" class="sendforce-input" style="margin-top:4px;font-size:12px;" onclick="this.select();" />
											</li>
											<li><?php echo wp_kses_post( __( 'Click <strong>Create</strong>. A modal shows your <strong>Client ID</strong> (ends with <code>.apps.googleusercontent.com</code>) and <strong>Client Secret</strong> (starts with <code>GOCSPX-</code>). Copy both into the fields above, save, then click "Connect with provider".', 'sendforce-mail-relay' ) ); ?></li>
										</ol>
									</div>

									<div class="sf-conn-api-outlook" style="margin-top:10px;">
										<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Microsoft 365 / Outlook', 'sendforce-mail-relay' ); ?></strong></p>
										<ol style="margin:0 0 10px 18px;padding:0;">
											<li><?php echo wp_kses_post( __( 'Open <a href="https://entra.microsoft.com/" target="_blank" rel="noopener">Microsoft Entra Admin Center</a> → <strong>App registrations → + New registration</strong>.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( 'Set <strong>Supported account types</strong> (single-tenant for your organisation only, multi-tenant for any).', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php esc_html_e( 'Under Redirect URI, pick platform "Web" and paste this exact URL:', 'sendforce-mail-relay' ); ?>
												<input type="text" readonly value="<?php echo esc_attr( $sendforce_oauth_redirect ); ?>" class="sendforce-input" style="margin-top:4px;font-size:12px;" onclick="this.select();" />
											</li>
											<li><?php echo wp_kses_post( __( 'After creation, copy the <strong>Application (client) ID</strong> and <strong>Directory (tenant) ID</strong> from the Overview page into the fields above.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( '<strong>API permissions → + Add a permission → Microsoft Graph → Delegated permissions</strong>: add <code>Mail.Send</code>, <code>User.Read</code>, and <code>offline_access</code>. Click <strong>Grant admin consent</strong> if you have admin rights.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( '<strong>Certificates &amp; secrets → + New client secret</strong> → name it, click Add. <strong style="color:#b32d2e;">CRITICAL:</strong> Azure shows two columns side-by-side — <strong>Value</strong> and <strong>Secret ID</strong>. Copy the <strong>Value</strong> column (random characters with <code>~</code>, <code>.</code>, <code>_</code> — typically 40+ chars). The Secret ID column is a GUID like <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code> — that is NOT the secret. Microsoft hides the Value forever once you navigate away, so copy it immediately.', 'sendforce-mail-relay' ) ); ?></li>
											<li><?php echo wp_kses_post( __( 'Paste the Value into the Client Secret field above. Save the connection. Then click "Connect with provider". (If you accidentally paste the Secret ID GUID, SendForce will reject the save with a clear error.)', 'sendforce-mail-relay' ) ); ?></li>
										</ol>
									</div>
								</details>
							</div>
						</div>
					</div>
				</div><!-- /.sendforce-api-config-form -->

				<!-- Sending limit (queue pacing) -->
				<div class="sendforce-field" style="margin-top:20px;">
					<label for="sf-conn-rate-limit" class="sendforce-label"><?php esc_html_e( 'Max emails per minute', 'sendforce-mail-relay' ); ?></label>
					<input type="number" id="sf-conn-rate-limit" class="sendforce-input" style="max-width:160px;" min="0" max="10000" step="1" value="0" />
					<span class="sendforce-help"><?php esc_html_e( 'Paces how fast the Email Queue sends through this connection — useful for providers with strict rate limits, such as the Gmail API. 0 = no limit. Only applies when the Email Queue is enabled; emails sent immediately are not paced.', 'sendforce-mail-relay' ); ?></span>
				</div>

				<!-- Save / Cancel buttons -->
				<div class="sendforce-form-actions" style="margin-top:20px;">
					<button type="button" id="sendforce-conn-save" class="sendforce-btn sendforce-btn-primary sendforce-btn-lg">
						<?php esc_html_e( 'Save Connection', 'sendforce-mail-relay' ); ?>
					</button>
					<button type="button" id="sendforce-conn-cancel" class="sendforce-btn" style="margin-left:8px;">
						<?php esc_html_e( 'Cancel', 'sendforce-mail-relay' ); ?>
					</button>
					<span id="sendforce-conn-notice" class="sendforce-notice-inline" style="display:none;margin-left:12px;"></span>
				</div>

			</div>
		</div><!-- /#sendforce-connection-form-card -->

		<!-- Sender Identity card — bottom of left column, linked to the sidebar options.php form -->
		<div class="sendforce-card sendforce-sender-card">
			<div class="sendforce-card-header">
				<div>
					<h2><?php esc_html_e( 'Sender Identity', 'sendforce-mail-relay' ); ?></h2>
					<p class="sendforce-text-muted" style="font-size:13px;margin:4px 0 0;">
						<?php esc_html_e( 'Override the default WordPress sender name and email address.', 'sendforce-mail-relay' ); ?>
					</p>
				</div>
			</div>
			<div class="sendforce-card-body">
				<div class="sendforce-sender-grid">
					<div class="sendforce-field">
						<label for="sendforce-from-name" class="sendforce-label"><?php esc_html_e( 'From Name', 'sendforce-mail-relay' ); ?></label>
						<input type="text" id="sendforce-from-name"
							name="sendforce_settings[from_name]"
							value="<?php echo esc_attr( $sendforce_settings['from_name'] ?? '' ); ?>"
							class="sendforce-input"
							form="sendforce-general-form"
							placeholder="<?php echo esc_attr( get_option( 'blogname' ) ); ?>" />
						<span class="sendforce-help"><?php esc_html_e( 'Displayed as the sender name in the inbox.', 'sendforce-mail-relay' ); ?></span>
					</div>
					<div class="sendforce-field">
						<label for="sendforce-from-email" class="sendforce-label"><?php esc_html_e( 'From Email', 'sendforce-mail-relay' ); ?></label>
						<input type="email" id="sendforce-from-email"
							name="sendforce_settings[from_email]"
							value="<?php echo esc_attr( $sendforce_settings['from_email'] ?? '' ); ?>"
							class="sendforce-input"
							form="sendforce-general-form"
							placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
						<span class="sendforce-help"><?php esc_html_e( 'Displayed as the sender address in the inbox.', 'sendforce-mail-relay' ); ?></span>
					</div>
				</div>
				<div class="sendforce-sender-actions">
					<button type="submit" form="sendforce-general-form" class="sendforce-btn sendforce-btn-primary">
						<?php esc_html_e( 'Save Sender Settings', 'sendforce-mail-relay' ); ?>
					</button>
				</div>
			</div>
		</div><!-- /.sendforce-sender-card -->

		<?php
		// Always reachable here, even after the conflict notice is dismissed.
		// Behaviour (preview → import) comes from assets/js/conflict-notice.js.
		$sendforce_import_sources = SendForce_Importer::available_sources();
		if ( ! empty( $sendforce_import_sources ) ) :
		?>
		<div class="sendforce-card sendforce-import-card" id="sendforce-import-card">
			<div class="sendforce-card-header">
				<div>
					<h2><?php esc_html_e( 'Import from another plugin', 'sendforce-mail-relay' ); ?></h2>
					<p class="sendforce-text-muted" style="font-size:13px;margin:4px 0 0;">
						<?php esc_html_e( 'Copy connections and sender settings from an SMTP plugin that is active on this site. You see a preview first, and nothing already in SendForce is replaced.', 'sendforce-mail-relay' ); ?>
					</p>
				</div>
			</div>
			<div class="sendforce-card-body">
				<ul class="sendforce-conflict-list">
					<?php foreach ( $sendforce_import_sources as $sendforce_import_slug => $sendforce_import_name ) : ?>
					<?php $sendforce_import_done = SendForce_Importer::already_imported( $sendforce_import_slug ); ?>
					<li>
						<strong><?php echo esc_html( $sendforce_import_name ); ?></strong>
						<?php if ( $sendforce_import_done ) : ?>
							<span class="sendforce-import-badge is-muted"><?php esc_html_e( 'Imported', 'sendforce-mail-relay' ); ?></span>
						<?php endif; ?>
						<span class="sendforce-conflict-actions">
							<button type="button" class="sendforce-conflict-import<?php echo $sendforce_import_done ? ' is-secondary' : ''; ?>"
								data-source="<?php echo esc_attr( $sendforce_import_slug ); ?>"
								data-done-label="<?php esc_attr_e( 'Check again', 'sendforce-mail-relay' ); ?>">
								<?php echo $sendforce_import_done ? esc_html__( 'Check again', 'sendforce-mail-relay' ) : esc_html__( 'Import settings', 'sendforce-mail-relay' ); ?>
							</button>
						</span>
						<div class="sendforce-import-panel" data-source="<?php echo esc_attr( $sendforce_import_slug ); ?>" aria-live="polite" hidden></div>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div><!-- /#sendforce-import-card -->
		<?php endif; ?>

	</div><!-- /.sendforce-settings-main -->

	<!-- Right column: General Settings (options.php form) -->
	<div class="sendforce-settings-sidebar">
		<form id="sendforce-general-form" method="post" action="options.php" class="sendforce-settings-form">
			<?php settings_fields( 'sendforce_settings_group' ); ?>

			<div class="sendforce-card">
				<div class="sendforce-card-header">
					<h2><?php esc_html_e( 'General Settings', 'sendforce-mail-relay' ); ?></h2>
				</div>
				<div class="sendforce-card-body">

					<!-- Primary Connection -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Primary Connection', 'sendforce-mail-relay' ); ?></p>
						<select name="sendforce_settings[default_connection]" class="sendforce-select sendforce-select-full">
							<option value="" <?php selected( $sendforce_default_connection, '' ); ?>><?php esc_html_e( '— None —', 'sendforce-mail-relay' ); ?></option>
							<?php foreach ( $sendforce_connections as $sendforce_conn ) :
								$sendforce_conn_provider = $sendforce_conn['provider'] ?? 'other';
								$sendforce_pdata = SendForce_Providers::get_provider( $sendforce_conn_provider );
								$sendforce_lbl   = ! empty( $sendforce_conn['label'] ) ? $sendforce_conn['label'] : ( $sendforce_pdata['label'] ?? ucfirst( $sendforce_conn_provider ) );
							?>
							<option value="<?php echo esc_attr( $sendforce_conn['id'] ); ?>" <?php selected( $sendforce_default_connection, $sendforce_conn['id'] ); ?>>
								<?php echo esc_html( $sendforce_lbl ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<span class="sendforce-help"><?php esc_html_e( 'Connection used to send all emails.', 'sendforce-mail-relay' ); ?></span>
					</div>

					<!-- Fallback Connection -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Fallback Connection', 'sendforce-mail-relay' ); ?></p>
						<select name="sendforce_settings[fallback_connection]" class="sendforce-select sendforce-select-full">
							<option value="" <?php selected( $sendforce_fallback_connection, '' ); ?>><?php esc_html_e( 'None', 'sendforce-mail-relay' ); ?></option>
							<?php foreach ( $sendforce_connections as $sendforce_conn ) :
								$sendforce_conn_provider = $sendforce_conn['provider'] ?? 'other';
								$sendforce_pdata = SendForce_Providers::get_provider( $sendforce_conn_provider );
								$sendforce_lbl   = ! empty( $sendforce_conn['label'] ) ? $sendforce_conn['label'] : ( $sendforce_pdata['label'] ?? ucfirst( $sendforce_conn_provider ) );
							?>
							<option value="<?php echo esc_attr( $sendforce_conn['id'] ); ?>" <?php selected( $sendforce_fallback_connection, $sendforce_conn['id'] ); ?>>
								<?php echo esc_html( $sendforce_lbl ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<span class="sendforce-help"><?php esc_html_e( 'Used if the primary connection fails.', 'sendforce-mail-relay' ); ?></span>
					</div>

					<hr class="sendforce-gs-divider">

					<!-- Log Emails -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Log Emails', 'sendforce-mail-relay' ); ?></p>
						<label class="sendforce-toggle-label">
							<input type="checkbox" name="sendforce_settings[log_enabled]" value="1"
								<?php checked( $sendforce_settings['log_enabled'] ?? true ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Log All Emails for Reporting', 'sendforce-mail-relay' ); ?></span>
						</label>
					</div>

					<!-- Delete Logs -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Delete Logs', 'sendforce-mail-relay' ); ?></p>
						<select name="sendforce_settings[log_retention_days]" class="sendforce-select sendforce-select-full">
							<option value="7"  <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 7 ); ?>><?php esc_html_e( 'After 7 Days', 'sendforce-mail-relay' ); ?></option>
							<option value="14" <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 14 ); ?>><?php esc_html_e( 'After 14 Days', 'sendforce-mail-relay' ); ?></option>
							<option value="30" <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 30 ); ?>><?php esc_html_e( 'After 30 Days', 'sendforce-mail-relay' ); ?></option>
							<option value="60" <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 60 ); ?>><?php esc_html_e( 'After 60 Days', 'sendforce-mail-relay' ); ?></option>
							<option value="90" <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 90 ); ?>><?php esc_html_e( 'After 90 Days', 'sendforce-mail-relay' ); ?></option>
							<option value="0"  <?php selected( $sendforce_settings['log_retention_days'] ?? 30, 0 ); ?>><?php esc_html_e( 'Never Delete', 'sendforce-mail-relay' ); ?></option>
						</select>
					</div>

					<!-- Email Simulation -->
					<?php /* id is the target of the header badge and the Email Test notice, so both land on the toggle rather than the top of this long page. */ ?>
					<div class="sendforce-gs-section" id="sendforce-simulation">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Email Simulation', 'sendforce-mail-relay' ); ?></p>
						<label class="sendforce-toggle-label">
							<input type="checkbox" name="sendforce_settings[simulation_mode]" value="1"
								<?php checked( ! empty( $sendforce_settings['simulation_mode'] ) ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Disable sending — log only, no email sent.', 'sendforce-mail-relay' ); ?></span>
						</label>
					</div>

					<!-- Multi-Part Plain Text -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Multi-Part Plain Text', 'sendforce-mail-relay' ); ?></p>
						<label class="sendforce-toggle-label">
							<input type="checkbox" name="sendforce_settings[multipart_text]" value="1"
								<?php checked( ! empty( $sendforce_settings['multipart_text'] ) ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Add plain text version of HTML emails.', 'sendforce-mail-relay' ); ?></span>
						</label>
					</div>

					<hr class="sendforce-gs-divider">

					<!-- Queue -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Email Queue', 'sendforce-mail-relay' ); ?></p>
						<label class="sendforce-toggle-label" style="margin-bottom:12px;">
							<input type="checkbox" name="sendforce_settings[queue_enabled]" value="1"
								<?php checked( $sendforce_settings['queue_enabled'] ?? false ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Enable Email Queue', 'sendforce-mail-relay' ); ?></span>
						</label>
						<label class="sendforce-label" style="margin-top:10px;display:block;"><?php esc_html_e( 'Batch Size', 'sendforce-mail-relay' ); ?></label>
						<input type="number" name="sendforce_settings[batch_size]" value="<?php echo esc_attr( $sendforce_settings['batch_size'] ?? 50 ); ?>" class="sendforce-input" min="1" max="500" />
						<span class="sendforce-help"><?php esc_html_e( 'Emails per cron run.', 'sendforce-mail-relay' ); ?></span>

						<label class="sendforce-label" style="margin-top:14px;display:block;"><?php esc_html_e( 'Max Retry Attempts', 'sendforce-mail-relay' ); ?></label>
						<input type="number" name="sendforce_settings[max_attempts]" value="<?php echo esc_attr( $sendforce_settings['max_attempts'] ?? 3 ); ?>" class="sendforce-input" min="1" max="10" />
						<span class="sendforce-help"><?php esc_html_e( 'How many times a failed email is retried before being marked failed.', 'sendforce-mail-relay' ); ?></span>
					</div>

					<hr class="sendforce-gs-divider">

					<!-- Developer Debug -->
					<div class="sendforce-gs-section">
						<p class="sendforce-gs-section-title"><?php esc_html_e( 'Developer Debug', 'sendforce-mail-relay' ); ?></p>
						<label class="sendforce-toggle-label">
							<input type="checkbox" name="sendforce_settings[developer_debug]" value="1"
								<?php checked( ! empty( $sendforce_settings['developer_debug'] ) ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Mirror SMTP and API debug output to debug.log', 'sendforce-mail-relay' ); ?></span>
						</label>
						<span class="sendforce-help"><?php esc_html_e( 'Requires WP_DEBUG and WP_DEBUG_LOG to be enabled in wp-config.php. Secrets are redacted before writing.', 'sendforce-mail-relay' ); ?></span>
					</div>

					<!-- Log Email Body -->
					<div class="sendforce-gs-section">
						<label class="sendforce-toggle-label">
							<input type="checkbox" name="sendforce_settings[log_email_body]" value="1"
								<?php checked( $sendforce_settings['log_email_body'] ?? true ); ?> class="sendforce-toggle-input" />
							<span class="sendforce-toggle"></span>
							<span><?php esc_html_e( 'Log Email Body', 'sendforce-mail-relay' ); ?></span>
						</label>
						<span class="sendforce-help"><?php esc_html_e( 'Disable for privacy.', 'sendforce-mail-relay' ); ?></span>
					</div>

					<div style="margin-top:20px;">
						<button type="submit" class="sendforce-btn sendforce-btn-primary" style="width:100%;">
							<?php esc_html_e( 'Save Settings', 'sendforce-mail-relay' ); ?>
						</button>
					</div>

				</div>
			</div>
		</form>
	</div><!-- /.sendforce-settings-sidebar -->

</div><!-- /.sendforce-settings-columns -->
