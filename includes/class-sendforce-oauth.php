<?php
/**
 * OAuth 2.0 authorization-code flow handler for Gmail and Microsoft 365.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_OAuth
 *
 * Handles the user-consent OAuth dance for Gmail and Microsoft 365 (delegated
 * permissions, refresh-token-based). The refresh token, once issued, is stored
 * encrypted in the connection's `password` field and consumed by the existing
 * `class-sendforce-api-gmail.php` / `class-sendforce-api-outlook.php` code paths.
 *
 * Bring-your-own-credentials model: the user provides their own OAuth Client
 * ID + Client Secret (created in their Google Cloud / Azure AD project). This
 * plugin never owns or proxies the OAuth app — the user retains full control.
 */
class SendForce_OAuth {

	const QUERY_PARAM = 'sf_oauth';
	const NONCE_TTL   = 600; // 10-minute consent window.
	const TRANSIENT_PREFIX = 'sendforce_oauth_state_';

	/**
	 * Register WordPress hooks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_handle_callback' ) );
		add_action( 'wp_ajax_sendforce_start_oauth', array( $this, 'ajax_start_oauth' ) );
		add_action( 'wp_ajax_sendforce_disconnect_oauth', array( $this, 'ajax_disconnect_oauth' ) );
	}

	/**
	 * Return the WordPress admin URL that providers should redirect to after
	 * the user consents. This URL must be registered as an "Authorized redirect
	 * URI" in the user's Google Cloud Console / Azure AD app.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		// Build via add_query_arg() on top of a clean admin.php URL so any other
		// plugin filtering `admin_url` (e.g. security or caching plugins) can't
		// strip the query string from the path. Some `admin_url` filters drop
		// or rewrite query strings passed in the path argument, which produces
		// a truncated redirect_uri sent to the OAuth provider and an
		// AADSTS50011 / redirect_uri_mismatch error at Microsoft.
		return add_query_arg(
			array(
				'page'            => 'sendforce-mail-relay',
				'tab'             => 'settings',
				self::QUERY_PARAM => 'callback',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * AJAX: kick off the OAuth flow. Stores a tamper-proof state value in a
	 * transient and returns the authorization URL the JS should redirect to.
	 */
	public function ajax_start_oauth() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}

		$conn_id  = isset( $_POST['conn_id'] )  ? sanitize_text_field( wp_unslash( $_POST['conn_id'] ) )  : '';
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) )       : '';

		if ( ! in_array( $provider, array( 'gmail', 'outlook' ), true ) ) {
			wp_send_json_error( __( 'OAuth is only supported for Gmail and Microsoft 365.', 'sendforce-mail-relay' ) );
		}
		if ( empty( $conn_id ) ) {
			wp_send_json_error( __( 'Save the connection first, then click Connect.', 'sendforce-mail-relay' ) );
		}

		$conn = $this->find_connection( $conn_id );
		if ( ! $conn ) {
			wp_send_json_error( __( 'Connection not found. Save it first.', 'sendforce-mail-relay' ) );
		}

		// Pull credentials.
		$client_id     = '';
		$client_secret = '';
		$tenant_id     = '';
		if ( 'gmail' === $provider ) {
			$client_id     = (string) ( $conn['gmail_client_id'] ?? '' );
			$client_secret = SendForce_Mailer::decrypt_password( (string) ( $conn['api_key'] ?? '' ) );
		} else {
			$client_id     = (string) ( $conn['outlook_client_id'] ?? '' );
			$client_secret = SendForce_Mailer::decrypt_password( (string) ( $conn['api_key'] ?? '' ) );
			$tenant_id     = (string) ( $conn['outlook_tenant_id'] ?? '' );
			if ( empty( $tenant_id ) ) {
				$tenant_id = 'common'; // Multi-tenant fallback.
			}
		}

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( __( 'Enter your Client ID and Client Secret first, save the connection, then click Connect.', 'sendforce-mail-relay' ) );
		}

		// Build a tamper-proof state value.
		$state = wp_generate_password( 24, false, false );
		set_transient(
			self::TRANSIENT_PREFIX . $state,
			array(
				'conn_id'  => $conn_id,
				'provider' => $provider,
				'user_id'  => get_current_user_id(),
			),
			self::NONCE_TTL
		);

		$url = $this->build_authorize_url( $provider, $client_id, $tenant_id, $state );
		wp_send_json_success( array( 'authorize_url' => $url ) );
	}

	/**
	 * AJAX: disconnect (clear stored refresh token) for a connection.
	 */
	public function ajax_disconnect_oauth() {
		check_ajax_referer( 'sendforce_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'sendforce-mail-relay' ) );
		}
		$conn_id = isset( $_POST['conn_id'] ) ? sanitize_text_field( wp_unslash( $_POST['conn_id'] ) ) : '';
		$conn    = $this->find_connection( $conn_id );
		if ( ! $conn ) {
			wp_send_json_error( __( 'Connection not found.', 'sendforce-mail-relay' ) );
		}

		$this->save_refresh_token( $conn_id, '' );
		$this->save_connected_email( $conn_id, '' );

		wp_send_json_success( array( 'message' => __( 'Disconnected.', 'sendforce-mail-relay' ) ) );
	}

	/**
	 * Detect a callback from Google / Microsoft on the settings page and
	 * exchange the auth code for a refresh token.
	 */
	public function maybe_handle_callback() {
		if ( ! isset( $_GET[ self::QUERY_PARAM ] ) || 'callback' !== $_GET[ self::QUERY_PARAM ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- callback is OAuth, validated below via state.
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state IS the nonce; verified next.
		$code  = isset( $_GET['code'] )  ? sanitize_text_field( wp_unslash( $_GET['code'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $state ) ) {
			$this->redirect_with_notice( 'error', __( 'OAuth callback missing state parameter.', 'sendforce-mail-relay' ) );
		}

		$payload = get_transient( self::TRANSIENT_PREFIX . $state );
		delete_transient( self::TRANSIENT_PREFIX . $state );
		if ( ! is_array( $payload ) || empty( $payload['conn_id'] ) || empty( $payload['provider'] ) ) {
			$this->redirect_with_notice( 'error', __( 'OAuth state expired or invalid. Try connecting again.', 'sendforce-mail-relay' ) );
		}
		if ( get_current_user_id() !== (int) ( $payload['user_id'] ?? 0 ) ) {
			$this->redirect_with_notice( 'error', __( 'OAuth callback was for a different user.', 'sendforce-mail-relay' ) );
		}

		// User cancelled or provider returned an error.
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$this->redirect_with_notice( 'error', sprintf(
				/* translators: %s: provider error message */
				__( 'OAuth was cancelled or rejected: %s', 'sendforce-mail-relay' ),
				$err
			) );
		}

		if ( empty( $code ) ) {
			$this->redirect_with_notice( 'error', __( 'OAuth callback missing authorization code.', 'sendforce-mail-relay' ) );
		}

		$conn_id  = $payload['conn_id'];
		$provider = $payload['provider'];

		$conn = $this->find_connection( $conn_id );
		if ( ! $conn ) {
			$this->redirect_with_notice( 'error', __( 'Original connection no longer exists.', 'sendforce-mail-relay' ) );
		}

		// Pull credentials again (the user may have edited them between flow start and callback).
		if ( 'gmail' === $provider ) {
			$client_id     = (string) ( $conn['gmail_client_id'] ?? '' );
			$client_secret = SendForce_Mailer::decrypt_password( (string) ( $conn['api_key'] ?? '' ) );
			$tenant_id     = '';
		} else {
			$client_id     = (string) ( $conn['outlook_client_id'] ?? '' );
			$client_secret = SendForce_Mailer::decrypt_password( (string) ( $conn['api_key'] ?? '' ) );
			$tenant_id     = (string) ( $conn['outlook_tenant_id'] ?? '' );
			if ( empty( $tenant_id ) ) {
				$tenant_id = 'common';
			}
		}

		$result = $this->exchange_code( $provider, $code, $client_id, $client_secret, $tenant_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		if ( empty( $result['refresh_token'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Provider did not return a refresh token. Make sure prompt=consent is set and the app has offline access enabled.', 'sendforce-mail-relay' ) );
		}

		$this->save_refresh_token( $conn_id, $result['refresh_token'] );

		// Best-effort lookup of the connected user's email so the UI can show it.
		$email = $this->fetch_account_email( $provider, $result['access_token'] ?? '' );
		if ( $email ) {
			$this->save_connected_email( $conn_id, $email );
		}

		$this->redirect_with_notice(
			'success',
			$email
				? sprintf(
					/* translators: %s: email address */
					__( 'Connected as %s.', 'sendforce-mail-relay' ),
					$email
				)
				: __( 'Connected successfully.', 'sendforce-mail-relay' )
		);
	}

	/**
	 * Build the provider-specific authorization URL.
	 *
	 * @param string $provider     gmail|outlook
	 * @param string $client_id    OAuth client ID.
	 * @param string $tenant_id    Azure tenant id (outlook only).
	 * @param string $state        Tamper-proof state.
	 * @return string
	 */
	private function build_authorize_url( $provider, $client_id, $tenant_id, $state ) {
		// IMPORTANT: WordPress's add_query_arg() does NOT URL-encode values it
		// inserts. Using it for the OAuth authorize URL would leave the `&`
		// characters inside our redirect_uri value un-escaped, and Microsoft /
		// Google would parse the trailing `&tab=settings&sf_oauth=callback`
		// as additional top-level query params — truncating the redirect_uri
		// at the first `&` and producing AADSTS50011 / redirect_uri_mismatch.
		// http_build_query() with PHP_QUERY_RFC3986 correctly percent-encodes
		// every value, so the redirect_uri arrives intact.
		if ( 'gmail' === $provider ) {
			$endpoint = 'https://accounts.google.com/o/oauth2/v2/auth';
			$params   = array(
				'client_id'     => $client_id,
				'redirect_uri'  => self::get_redirect_uri(),
				'response_type' => 'code',
				'scope'         => 'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/userinfo.email',
				'access_type'   => 'offline',
				'prompt'        => 'consent', // Forces refresh_token re-issue on re-auth.
				'state'         => $state,
			);
		} else {
			// Microsoft 365.
			$endpoint = 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id ) . '/oauth2/v2.0/authorize';
			$params   = array(
				'client_id'     => $client_id,
				'redirect_uri'  => self::get_redirect_uri(),
				'response_type' => 'code',
				'response_mode' => 'query',
				'scope'         => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read offline_access',
				'state'         => $state,
				'prompt'        => 'consent',
			);
		}

		return $endpoint . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Exchange the authorization code for tokens.
	 *
	 * @return array|WP_Error array with refresh_token / access_token / expires_in, or WP_Error.
	 */
	private function exchange_code( $provider, $code, $client_id, $client_secret, $tenant_id ) {
		if ( 'gmail' === $provider ) {
			$endpoint = 'https://oauth2.googleapis.com/token';
		} else {
			$endpoint = 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id ) . '/oauth2/v2.0/token';
		}

		$body = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => self::get_redirect_uri(),
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 20,
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sendforce_oauth_http', $response->get_error_message() );
		}

		$code_status = (int) wp_remote_retrieve_response_code( $response );
		$decoded     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code_status || ! is_array( $decoded ) ) {
			$msg = is_array( $decoded ) && ! empty( $decoded['error_description'] )
				? $decoded['error_description']
				: __( 'Token exchange failed.', 'sendforce-mail-relay' );
			return new WP_Error( 'sendforce_oauth_token', sprintf( '[%d] %s', $code_status, $msg ) );
		}

		return $decoded;
	}

	/**
	 * Look up the email of the consenting account so we can show it in the UI.
	 * Best-effort — failure is silent.
	 */
	private function fetch_account_email( $provider, $access_token ) {
		if ( empty( $access_token ) ) {
			return '';
		}

		if ( 'gmail' === $provider ) {
			$response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			) );
		} else {
			$response = wp_remote_get( 'https://graph.microsoft.com/v1.0/me', array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			) );
		}

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 'gmail' === $provider ) {
			return is_array( $body ) ? sanitize_email( $body['email'] ?? '' ) : '';
		}
		return is_array( $body ) ? sanitize_email( $body['mail'] ?? $body['userPrincipalName'] ?? '' ) : '';
	}

	/**
	 * Find a connection row in sendforce_connections by id.
	 */
	private function find_connection( $conn_id ) {
		$conns = (array) get_option( 'sendforce_connections', array() );
		foreach ( $conns as $c ) {
			if ( isset( $c['id'] ) && $c['id'] === $conn_id ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Persist the encrypted refresh token to a connection row.
	 */
	private function save_refresh_token( $conn_id, $refresh_token ) {
		$conns = (array) get_option( 'sendforce_connections', array() );
		foreach ( $conns as &$c ) {
			if ( isset( $c['id'] ) && $c['id'] === $conn_id ) {
				$c['password'] = '' === $refresh_token
					? ''
					: SendForce_Mailer::encrypt_password( $refresh_token );
				break;
			}
		}
		unset( $c );
		update_option( 'sendforce_connections', $conns );
	}

	/**
	 * Persist the connected account email (for display only).
	 */
	private function save_connected_email( $conn_id, $email ) {
		$conns = (array) get_option( 'sendforce_connections', array() );
		foreach ( $conns as &$c ) {
			if ( isset( $c['id'] ) && $c['id'] === $conn_id ) {
				$c['oauth_email'] = $email;
				break;
			}
		}
		unset( $c );
		update_option( 'sendforce_connections', $conns );
	}

	/**
	 * Finish the OAuth callback. The "Connect with provider" button opens
	 * the consent screen in a popup window since 1.0.24 — when that's the
	 * case we want to close the popup and tell the parent window to
	 * refresh. When the popup was blocked the JS falls back to a full-page
	 * redirect, in which case we need to redirect the current window back
	 * to the settings tab. We can't reliably distinguish the two cases
	 * server-side, so we emit a small HTML page that does both:
	 *   - If `window.opener` exists: postMessage to it and close self.
	 *   - Else: window.location.replace() to the settings tab fallback URL.
	 *
	 * @param string $type    "success" or "error".
	 * @param string $message Notice text.
	 */
	private function redirect_with_notice( $type, $message ) {
		$fallback_url = add_query_arg(
			array(
				'page'             => 'sendforce-mail-relay',
				'tab'              => 'settings',
				'sf_oauth_status'  => $type,
				'sf_oauth_message' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		$payload = array(
			'type'    => 'sendforce_oauth',
			'status'  => $type,
			'message' => $message,
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php esc_html_e( 'Completing connection…', 'sendforce-mail-relay' ); ?></title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; text-align: center; padding: 60px 20px; color: #1d2327; background: #f0f0f1; margin: 0; }
.box { max-width: 420px; margin: 0 auto; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 32px 24px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
h1 { font-size: 18px; margin: 0 0 8px; }
p { margin: 0; color: #50575e; font-size: 14px; }
.spinner { width: 32px; height: 32px; margin: 0 auto 16px; border: 3px solid #dcdcde; border-top-color: #2271b1; border-radius: 50%; animation: spin .9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="box">
<div class="spinner" aria-hidden="true"></div>
<h1><?php esc_html_e( 'Completing connection…', 'sendforce-mail-relay' ); ?></h1>
<p><?php esc_html_e( 'You can close this window if it does not close automatically.', 'sendforce-mail-relay' ); ?></p>
</div>
<script>
(function() {
	<?php // JSON_HEX_* flags are the escaping for this inline-script context: the message can carry provider error text, and without JSON_HEX_TAG a value containing "<!--<script>" can make the HTML parser swallow this block's closing tag. ?>
	var payload  = <?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	var fallback = <?php echo wp_json_encode( $fallback_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	var inPopup  = false;

	// Channel 1: localStorage. Most reliable across the cross-origin trip
	// because the browser shares localStorage per-origin and fires a storage
	// event in OTHER same-origin windows when the value changes — even if
	// `window.opener` was severed by Cross-Origin-Opener-Policy.
	try {
		localStorage.setItem( 'sendforce_oauth_result', JSON.stringify( payload ) );
		inPopup = true;
	} catch ( e ) {}

	// Channel 2: postMessage. Best-effort fallback for browsers/configs
	// where window.opener survives the cross-origin navigation.
	try {
		if ( window.opener && ! window.opener.closed ) {
			window.opener.postMessage( payload, window.location.origin );
			inPopup = true;
		}
	} catch ( e ) {}

	if ( inPopup ) {
		// Give channels 1 and 2 a beat to be received by the parent, then
		// close ourselves. The parent's polling loop will reload the
		// settings tab if the storage event somehow didn't fire.
		setTimeout( function() {
			try { window.close(); } catch ( e ) {}
			// Some Chromium versions refuse to close popups they didn't
			// open — fall through to the redirect so the user isn't stuck
			// on this splash screen.
			setTimeout( function() { window.location.replace( fallback ); }, 800 );
		}, 250 );
		return;
	}

	// Not opened as a popup (popup-blocked fallback flow). Just redirect
	// the current window back to the settings tab.
	window.location.replace( fallback );
})();
</script>
</body>
</html>
		<?php
		exit;
	}
}
