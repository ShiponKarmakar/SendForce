<?php
/**
 * Gmail / Google Workspace API mailer.
 *
 * Uses OAuth 2.0 Client Credentials with a refresh token to send emails
 * via the Gmail API.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Gmail
 */
class SendForce_API_Gmail extends SendForce_API_Mailer {

	/**
	 * This mailbox can only send as itself, so a From chosen by the sending
	 * plugin is not applied (see SendForce_API_Mailer::apply_message_from).
	 *
	 * @var bool
	 */
	protected $from_bound_to_account = true;

	/**
	 * Get the provider display name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Gmail';
	}

	/**
	 * Send an email via Gmail API.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Email subject.
	 * @param string       $message     Email body.
	 * @param string|array $headers     Email headers.
	 * @param array        $attachments Attachments.
	 * @return bool
	 */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$client_id     = $this->settings['gmail_client_id'] ?? '';
		$client_secret = SendForce_Mailer::decrypt_password( $this->settings['api_key'] ?? '' );
		$refresh_token = SendForce_Mailer::decrypt_password( $this->settings['password'] ?? '' );
		$from_email    = $this->get_from_email();
		$from_name     = $this->get_from_name();

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
			$this->last_debug = array(
				'provider'  => $this->get_name(),
				'url'       => '(not attempted)',
				'resp_code' => 0,
				'resp_body' => empty( $refresh_token ) && ! empty( $client_id ) && ! empty( $client_secret )
					? __( 'This connection is not signed in yet. Open it in Settings and click "Connect with provider".', 'sendforce-mail-relay' )
					: __( 'The Client ID or Client Secret is missing. Enter both in Settings, save the connection, then click "Connect with provider".', 'sendforce-mail-relay' ),
				'timestamp' => current_time( 'mysql' ),
			);
			return false;
		}

		// Get a fresh access token using the refresh token.
		$access_token = $this->get_access_token( $client_id, $client_secret, $refresh_token );
		if ( ! $access_token ) {
			// $this->last_debug was populated by get_access_token() with the
			// raw token-endpoint response (typically invalid_grant when the
			// refresh token has been revoked or expired).
			return false;
		}

		// Parse headers and recipients.
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		// Build raw RFC 2822 message via the shared helper. Bcc IS included
		// in MIME headers because Gmail extracts the actual recipient list
		// from the raw message (unlike SES, which has a separate envelope).
		$raw = $this->build_raw_mime(
			array(
				'from_email'   => $from_email,
				'from_name'    => $from_name,
				'to'           => $recipients,
				'cc'           => $parsed['cc'],
				'bcc'          => $parsed['bcc'],
				'reply_to'     => $parsed['reply_to'],
				'subject'      => $subject,
				'message'      => $message,
				'content_type' => $parsed['content_type'],
				'attachments'  => is_array( $attachments ) ? $attachments : array(),
				'include_bcc'  => true,
			)
		);

		// URL-safe base64 encode as required by Gmail API.
		$encoded = rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Gmail API requires URL-safe base64.

		// Send via Gmail API. Use the base class's make_request() so the
		// request/response are captured into $this->last_debug — without that,
		// errors like invalidArgument (malformed raw email), failedPrecondition
		// (delegation issues) or quotaExceeded never surface to the user and
		// they only see a generic "Failed to send email via Gmail API".
		$url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

		$response = $this->make_request(
			$url,
			array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			array( 'raw' => $encoded )
		);

		return $this->is_success( $response );
	}

	/**
	 * Get an OAuth 2.0 access token using a refresh token.
	 *
	 * @param string $client_id     Google OAuth Client ID.
	 * @param string $client_secret Google OAuth Client Secret.
	 * @param string $refresh_token OAuth Refresh Token.
	 * @return string|false Access token or false on failure.
	 */
	private function get_access_token( $client_id, $client_secret, $refresh_token ) {
		$url      = 'https://oauth2.googleapis.com/token';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_debug = array(
				'provider'  => $this->get_name(),
				'url'       => $url,
				'resp_code' => 0,
				/* translators: %s: network error message */
				'resp_body' => sprintf( __( 'Could not reach the sign-in server: %s', 'sendforce-mail-relay' ), $response->get_error_message() ),
				'timestamp' => current_time( 'mysql' ),
			);
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->last_debug = array(
				'provider'  => $this->get_name(),
				'url'       => $url,
				'resp_code' => $code,
				'resp_body' => mb_strimwidth( (string) wp_remote_retrieve_body( $response ), 0, 4000, '…' ),
				'timestamp' => current_time( 'mysql' ),
			);
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['access_token'] ?? false;
	}
}
