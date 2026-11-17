<?php
/**
 * Postmark API mailer.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Postmark
 */
class SendForce_API_Postmark extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Postmark';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$body = array(
			'From'    => $this->get_from_name() . ' <' . $this->get_from_email() . '>',
			'To'      => implode( ', ', $recipients ),
			'Subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$body['HtmlBody'] = $message;
			// Postmark accepts both TextBody + HtmlBody together — it sends
			// as multipart/alternative on the wire.
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$body['TextBody'] = $plain_alt;
			}
		} else {
			$body['TextBody'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['Cc'] = implode( ', ', $parsed['cc'] );
		}
		if ( ! empty( $parsed['bcc'] ) ) {
			$body['Bcc'] = implode( ', ', $parsed['bcc'] );
		}
		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['ReplyTo'] = $parsed['reply_to'];
		}

		// Postmark Attachments[]: {Name, Content (base64), ContentType}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['Attachments'] = array_map(
				function ( $a ) {
					return array(
						'Name'        => $a['filename'],
						'Content'     => $a['content_b64'],
						'ContentType' => $a['mime'],
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.postmarkapp.com/email',
			array(
				'X-Postmark-Server-Token' => $this->get_api_key(),
				'Content-Type'            => 'application/json',
				'Accept'                  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
