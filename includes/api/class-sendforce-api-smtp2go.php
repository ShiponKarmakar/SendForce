<?php
/**
 * SMTP2GO API mailer.
 *
 * @see https://developers.smtp2go.com/docs/send-an-email
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_SMTP2GO
 */
class SendForce_API_SMTP2GO extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SMTP2GO';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$body = array(
			'api_key' => $this->get_api_key(),
			'sender'  => $this->get_from_name() . ' <' . $this->get_from_email() . '>',
			'to'      => $recipients,
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$body['html_body'] = $message;
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$body['text_body'] = $plain_alt;
			}
		} else {
			$body['text_body'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['cc'] = $parsed['cc'];
		}
		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = $parsed['bcc'];
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['custom_headers'] = array(
				array(
					'header' => 'Reply-To',
					'value'  => $parsed['reply_to'],
				),
			);
		}

		// SMTP2GO attachments[]: {filename, fileblob (base64), mimetype}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $a ) {
					return array(
						'filename' => $a['filename'],
						'fileblob' => $a['content_b64'],
						'mimetype' => $a['mime'],
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.smtp2go.com/v3/email/send',
			array( 'Content-Type' => 'application/json' ),
			$body
		);

		return $this->is_success( $response );
	}
}
