<?php
/**
 * MailerSend API mailer.
 *
 * @see https://developers.mailersend.com/api/v1/email.html
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Mailersend
 */
class SendForce_API_Mailersend extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'MailerSend';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		$body = array(
			'from' => array(
				'email' => $this->get_from_email(),
				'name'  => $this->get_from_name(),
			),
			'to'      => array_map( function ( $email ) {
				return array( 'email' => $email );
			}, $recipients ),
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
			$body['cc'] = array_map( function ( $email ) {
				return array( 'email' => $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = array_map( function ( $email ) {
				return array( 'email' => $email );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['reply_to'] = array( 'email' => $parsed['reply_to'] );
		}

		// MailerSend attachments[]: {content (base64), filename, disposition}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $a ) {
					return array(
						'content'     => $a['content_b64'],
						'filename'    => $a['filename'],
						'disposition' => 'attachment',
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.mailersend.com/v1/email',
			array(
				'Authorization' => 'Bearer ' . $this->strip_auth_prefix( $this->get_api_key(), 'Bearer ' ),
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response, array( 200, 201, 202 ) );
	}
}
