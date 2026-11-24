<?php
/**
 * Brevo (Sendinblue) API mailer.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Brevo
 */
class SendForce_API_Brevo extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Brevo';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$body = array(
			'sender'  => array(
				'name'  => $this->get_from_name(),
				'email' => $this->get_from_email(),
			),
			'to'      => array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $recipients ),
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$body['htmlContent'] = $message;
			// multipart_text: Brevo accepts both htmlContent + textContent in
			// the same request; the recipient's client picks which to render.
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$body['textContent'] = $plain_alt;
			}
		} else {
			$body['textContent'] = $message;
		}

		// Brevo attachment[]: {name, content (base64)}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachment'] = array_map(
				function ( $a ) {
					return array(
						'name'    => $a['filename'],
						'content' => $a['content_b64'],
					);
				},
				$prepared
			);
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['cc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['replyTo'] = $this->addr_obj( $parsed['reply_to'] );
		}

		$response = $this->make_request(
			'https://api.brevo.com/v3/smtp/email',
			array(
				'api-key'      => $this->get_api_key(),
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			$body
		);

		return $this->is_success( $response, array( 200, 201 ) );
	}
}
