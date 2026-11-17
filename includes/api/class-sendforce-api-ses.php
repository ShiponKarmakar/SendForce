<?php
/**
 * Amazon SES API mailer (v2 via HTTP).
 *
 * Uses AWS SES v2 SendEmail API with Signature Version 4.
 *
 * @see https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_SES
 */
class SendForce_API_SES extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Amazon SES';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );
		$region     = $this->settings['ses_region'] ?? 'us-east-1';
		// Access Key ID — stored in ses_access_key (dedicated field, avoids collision with SMTP username).
		// Falls back to username for backward compatibility with older saved settings.
		$access_key = $this->settings['ses_access_key'] ?? $this->settings['username'] ?? '';
		$secret_key = SendForce_Mailer::decrypt_password( $this->settings['api_key'] ?? '' );

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			return false;
		}

		// Collect all destination addresses for the Destination field.
		$all_to  = $recipients;
		$all_cc  = ! empty( $parsed['cc'] ) ? $parsed['cc'] : array();
		$all_bcc = ! empty( $parsed['bcc'] ) ? $parsed['bcc'] : array();

		// Build raw RFC 2822 message via the shared helper. Bcc is OMITTED
		// from the MIME headers because SES handles Bcc delivery via the
		// Destination.BccAddresses API field below — including it twice
		// would expose Bcc addresses to other recipients.
		$raw = $this->build_raw_mime(
			array(
				'from_email'   => $this->get_from_email(),
				'from_name'    => $this->get_from_name(),
				'to'           => $recipients,
				'cc'           => $all_cc,
				'bcc'          => $all_bcc,
				'reply_to'     => $parsed['reply_to'],
				'subject'      => $subject,
				'message'      => $message,
				'content_type' => $parsed['content_type'],
				'attachments'  => is_array( $attachments ) ? $attachments : array(),
				'include_bcc'  => false,
			)
		);

		// AWS SES v2 SendEmail API.
		$host      = "email.{$region}.amazonaws.com";
		$url       = "https://{$host}/v2/email/outbound-emails";
		$service   = 'ses';
		$date      = gmdate( 'Ymd\THis\Z' );
		$datestamp = gmdate( 'Ymd' );

		// Build request payload with explicit Destination for BCC support.
		$destination = array(
			'ToAddresses' => $all_to,
		);
		if ( ! empty( $all_cc ) ) {
			$destination['CcAddresses'] = $all_cc;
		}
		if ( ! empty( $all_bcc ) ) {
			$destination['BccAddresses'] = $all_bcc;
		}

		$payload = wp_json_encode(
			array(
				'Destination' => $destination,
				'Content'     => array(
					'Raw' => array(
						'Data' => base64_encode( $raw ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					),
				),
			)
		);

		// AWS Signature Version 4.
		$canonical_uri     = '/v2/email/outbound-emails';
		$canonical_query   = '';
		$content_hash      = hash( 'sha256', $payload );
		$canonical_headers = "content-type:application/json\nhost:{$host}\nx-amz-date:{$date}\n";
		$signed_headers    = 'content-type;host;x-amz-date';

		$canonical_request = "POST\n{$canonical_uri}\n{$canonical_query}\n{$canonical_headers}\n{$signed_headers}\n{$content_hash}";

		$scope           = "{$datestamp}/{$region}/{$service}/aws4_request";
		$string_to_sign  = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash( 'sha256', $canonical_request );

		// Derive signing key.
		$k_date    = hash_hmac( 'sha256', $datestamp, 'AWS4' . $secret_key, true );
		$k_region  = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

		$signature     = hash_hmac( 'sha256', $string_to_sign, $k_signing );
		$authorization = "AWS4-HMAC-SHA256 Credential={$access_key}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

		// Use the base class's make_request() so request/response are captured
		// into $this->last_debug. Without this, get_last_debug() returns empty
		// and the mailer surfaces only the generic "Failed to send email via
		// Amazon SES API" message, hiding the real SES error like
		// MessageRejected, MailFromDomainNotVerifiedException, Throttling, or
		// SendingPausedException that users need to diagnose the failure.
		$response = $this->make_request(
			$url,
			array(
				'Content-Type'  => 'application/json',
				'Host'          => $host,
				'X-Amz-Date'    => $date,
				'Authorization' => $authorization,
			),
			$payload
		);

		return $this->is_success( $response );
	}
}
