<?php
/**
 * Microsoft 365 (Outlook) Graph API mailer.
 *
 * Uses OAuth 2.0 Client Credentials flow to send emails
 * via the Microsoft Graph API.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Outlook
 */
class SendForce_API_Outlook extends SendForce_API_Mailer {

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
		return 'Microsoft 365';
	}

	/**
	 * Send an email via Microsoft Graph API.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Email subject.
	 * @param string       $message     Email body.
	 * @param string|array $headers     Email headers.
	 * @param array        $attachments Attachments.
	 * @return bool
	 */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$tenant_id     = $this->settings['outlook_tenant_id'] ?? '';
		$client_id     = $this->settings['outlook_client_id'] ?? '';
		$client_secret = SendForce_Mailer::decrypt_password( $this->settings['api_key'] ?? '' );
		$refresh_token = SendForce_Mailer::decrypt_password( $this->settings['password'] ?? '' );
		$from_email    = $this->get_from_email();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->last_debug = array(
				'provider'  => $this->get_name(),
				'url'       => '(not attempted)',
				'resp_code' => 0,
				'resp_body' => __( 'The Application (Client) ID or Client Secret is missing. Enter both in Settings and save the connection.', 'sendforce-mail-relay' ),
				'timestamp' => current_time( 'mysql' ),
			);
			return false;
		}

		// Two flows supported, picked automatically:
		//
		//  - When a refresh_token is present, use the user-delegated
		//    authorization_code flow (issued by the "Connect with Microsoft"
		//    button in Settings). Sends from the consenting user's mailbox.
		//  - Otherwise, fall back to the legacy client_credentials flow which
		//    requires a tenant_id and Application-permission `Mail.Send`.
		if ( ! empty( $refresh_token ) ) {
			$access_token = $this->get_access_token_via_refresh_token( $tenant_id, $client_id, $client_secret, $refresh_token );
		} elseif ( ! empty( $tenant_id ) ) {
			$access_token = $this->get_access_token_via_client_credentials( $tenant_id, $client_id, $client_secret );
		} else {
			$this->last_debug = array(
				'provider'  => $this->get_name(),
				'url'       => '(token endpoint)',
				'resp_code' => 0,
				'resp_body' => __( 'This connection is not signed in yet. Open it in Settings and click "Connect with provider", or enter a Tenant ID to use app-only access.', 'sendforce-mail-relay' ),
				'timestamp' => current_time( 'mysql' ),
			);
			return false;
		}
		if ( ! $access_token ) {
			// $this->last_debug was populated by get_access_token_*() with the
			// raw token-endpoint response (typically AADSTS70008 / invalid_grant
			// when the refresh token has been revoked or the consent expired).
			return false;
		}

		// Parse headers and recipients.
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		// Build the Graph API message payload.
		$content_type = 'text/html' === $parsed['content_type'] ? 'html' : 'text';

		$email_message = array(
			'subject' => $subject,
			'body'    => array(
				'contentType' => $content_type,
				'content'     => $message,
			),
			'from'    => array(
				'emailAddress' => array(
					'address' => $from_email,
					'name'    => $this->get_from_name(),
				),
			),
			'toRecipients' => array_map(
				function ( $email ) {
					return array( 'emailAddress' => array( 'address' => $email ) );
				},
				$recipients
			),
		);

		// CC recipients.
		if ( ! empty( $parsed['cc'] ) ) {
			$email_message['ccRecipients'] = array_map(
				function ( $email ) {
					return array( 'emailAddress' => array( 'address' => $email ) );
				},
				$parsed['cc']
			);
		}

		// BCC recipients.
		if ( ! empty( $parsed['bcc'] ) ) {
			$email_message['bccRecipients'] = array_map(
				function ( $email ) {
					return array( 'emailAddress' => array( 'address' => $email ) );
				},
				$parsed['bcc']
			);
		}

		// Reply-To.
		if ( ! empty( $parsed['reply_to'] ) ) {
			$email_message['replyTo'] = array(
				array(
					'emailAddress' => array( 'address' => $parsed['reply_to'] ),
				),
			);
		}

		// Attachments. Graph takes them inline on the message as fileAttachment
		// resources; without this they are silently dropped — the request still
		// returns HTTP 202 and the mail arrives without them.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			// sendMail carries attachments in the request body, and Graph caps
			// that request at 4 MB. Past roughly 3 MB of base64 it answers 413
			// RequestEntityTooLarge, which reads like a server fault rather than
			// "this file is too big", so say so plainly instead. Larger files
			// need a draft message plus an upload session, which this mailer
			// does not implement.
			$encoded_bytes = 0;
			foreach ( $prepared as $file ) {
				$encoded_bytes += strlen( $file['content_b64'] );
			}
			if ( $encoded_bytes > 3 * MB_IN_BYTES ) {
				$this->last_debug = array(
					'provider'  => $this->get_name(),
					'url'       => '(not attempted)',
					'resp_code' => 0,
					'resp_body' => sprintf(
						/* translators: 1: total attachment size, 2: comma-separated file names */
						__( 'Attachments total %1$s, over the 3 MB that Microsoft 365 accepts in one send: %2$s. Send a download link instead, or use an SMTP connection for this message.', 'sendforce-mail-relay' ),
						size_format( $encoded_bytes ),
						implode( ', ', wp_list_pluck( $prepared, 'filename' ) )
					),
					'timestamp' => current_time( 'mysql' ),
				);
				return false;
			}

			$email_message['attachments'] = array_map(
				function ( $file ) {
					return array(
						'@odata.type'  => '#microsoft.graph.fileAttachment',
						'name'         => $file['filename'],
						'contentType'  => $file['mime'],
						'contentBytes' => $file['content_b64'],
					);
				},
				$prepared
			);
		}

		$body = array(
			'message'         => $email_message,
			'saveToSentItems' => true,
		);

		// Send via Microsoft Graph API. The delegated flow (refresh_token)
		// uses /me/sendMail; the app-only flow (client_credentials) needs
		// /users/{email}/sendMail because there's no signed-in user.
		$url = ! empty( $refresh_token )
			? 'https://graph.microsoft.com/v1.0/me/sendMail'
			: 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $from_email ) . '/sendMail';

		// Use the base class's make_request() instead of calling wp_remote_post
		// directly, so the request/response are captured into $this->last_debug.
		// Without this, get_last_debug() returns an empty array and the mailer
		// surfaces only the generic "Failed to send email via Microsoft 365 API"
		// message — losing the actual Graph error (ErrorAccessDenied,
		// MailboxNotEnabledForRESTAPI, AADSTS70008 token expired, etc.) that
		// users need to diagnose the failure.
		$response = $this->make_request(
			$url,
			array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// Graph API returns 202 Accepted on success.
		return in_array( $code, array( 200, 201, 202 ), true );
	}

	/**
	 * App-only flow. Used when no refresh_token is stored (legacy / IT-managed
	 * tenant setup with admin-consented Application permissions).
	 *
	 * @param string $tenant_id    Azure AD Tenant ID.
	 * @param string $client_id    Application (Client) ID.
	 * @param string $client_secret Client Secret.
	 * @return string|false Access token or false on failure.
	 */
	private function get_access_token_via_client_credentials( $tenant_id, $client_id, $client_secret ) {
		$url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id ) . '/oauth2/v2.0/token';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'scope'         => 'https://graph.microsoft.com/.default',
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

	/**
	 * Delegated flow. Used after the user clicks "Connect with Microsoft" and
	 * grants consent — the resulting refresh_token is stored encrypted in the
	 * connection's password field.
	 *
	 * @param string $tenant_id    Azure AD Tenant ID (or "common" for multi-tenant).
	 * @param string $client_id    Application (Client) ID.
	 * @param string $client_secret Client Secret.
	 * @param string $refresh_token Long-lived refresh token issued via OAuth consent.
	 * @return string|false Short-lived access token, or false on failure.
	 */
	private function get_access_token_via_refresh_token( $tenant_id, $client_id, $client_secret, $refresh_token ) {
		$tenant = ! empty( $tenant_id ) ? $tenant_id : 'common';
		$url    = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'scope'         => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read offline_access',
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
