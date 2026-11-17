<?php
/**
 * Mailgun API mailer.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Mailgun
 */
class SendForce_API_Mailgun extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Mailgun';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );
		$domain     = $this->settings['mailgun_domain'] ?? '';
		$region     = $this->settings['mailgun_region'] ?? 'us';
		$base_url   = 'eu' === $region ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';

		if ( empty( $domain ) ) {
			return false;
		}

		$fields = array(
			'from'    => $this->get_from_name() . ' <' . $this->get_from_email() . '>',
			'to'      => implode( ', ', $recipients ),
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$fields['html'] = $message;
			// multipart_text: Mailgun accepts both html + text in the same
			// request and adds them as multipart/alternative on the wire.
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$fields['text'] = $plain_alt;
			}
		} else {
			$fields['text'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$fields['cc'] = implode( ', ', $parsed['cc'] );
		}
		if ( ! empty( $parsed['bcc'] ) ) {
			$fields['bcc'] = implode( ', ', $parsed['bcc'] );
		}
		if ( ! empty( $parsed['reply_to'] ) ) {
			$fields['h:Reply-To'] = $parsed['reply_to'];
		}

		// Mailgun attachments require multipart/form-data — the JSON-style
		// /messages endpoint doesn't accept base64 in form fields. When
		// attachments are present we build a multipart/form-data body
		// manually; otherwise we keep the simpler form-urlencoded path
		// (faster, smaller request).
		$files = $this->prepare_attachments_raw( $attachments );
		if ( ! empty( $files ) ) {
			$mp = $this->build_multipart_form_data( $fields, $files, 'attachment' );
			$response = $this->make_request(
				$base_url . '/v3/' . $domain . '/messages',
				array(
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $this->get_api_key() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => $mp['content_type'],
				),
				$mp['body']
			);
		} else {
			// No attachments — keep the existing form-urlencoded path.
			$response = $this->make_request(
				$base_url . '/v3/' . $domain . '/messages',
				array(
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $this->get_api_key() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				http_build_query( $fields )
			);
		}

		return $this->is_success( $response );
	}
}
