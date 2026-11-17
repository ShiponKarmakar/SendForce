<?php
/**
 * Resend API mailer.
 *
 * Resend requires a User-Agent header on all API requests.
 *
 * @see https://resend.com/docs/api-reference/emails/send-email
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Resend
 */
class SendForce_API_Resend extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Resend';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		$body = array(
			'from'    => $this->get_from_name() . ' <' . $this->get_from_email() . '>',
			'to'      => $recipients,
			'subject' => $subject,
		);

		if ( 'text/html' === $content_type ) {
			$body['html'] = $message;
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$body['text'] = $plain_alt;
			}
		} else {
			$body['text'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['cc'] = $parsed['cc'];
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = $parsed['bcc'];
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['reply_to'] = $parsed['reply_to'];
		}

		// Resend attachments[]: {content (base64), filename}. Content can
		// also be a URL via the `path` key, but we always have local bytes.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $a ) {
					return array(
						'content'  => $a['content_b64'],
						'filename' => $a['filename'],
					);
				},
				$prepared
			);
		}

		// Resend requires a User-Agent header on all API requests.
		$response = $this->make_request(
			'https://api.resend.com/emails',
			array(
				'Authorization' => 'Bearer ' . $this->strip_auth_prefix( $this->get_api_key(), 'Bearer ' ),
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'SendForce-Mail-Relay/' . SENDFORCE_VERSION,
			),
			$body
		);

		return $this->is_success( $response );
	}
}
