<?php
/**
 * Zoho ZeptoMail API mailer.
 *
 * Uses the ZeptoMail transactional email API with a Send Mail Token.
 *
 * @see https://www.zoho.com/zeptomail/help/api/email-sending.html
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Zoho
 */
class SendForce_API_Zoho extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Zoho ZeptoMail';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$body = array(
			'from'    => array(
				'address' => $this->get_from_email(),
				'name'    => $this->get_from_name(),
			),
			'to'      => array_map( function ( $email ) {
				return array( 'email_address' => array( 'address' => $email ) );
			}, $recipients ),
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$body['htmlbody'] = $message;
		} else {
			$body['textbody'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['cc'] = array_map( function ( $email ) {
				return array( 'email_address' => array( 'address' => $email ) );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = array_map( function ( $email ) {
				return array( 'email_address' => array( 'address' => $email ) );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['reply_to'] = array(
				'address' => $parsed['reply_to'],
			);
		}

		// Attachments. ZeptoMail takes them base64-encoded on the payload; they
		// were previously dropped without a word, the send still succeeding.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $file ) {
					return array(
						'name'      => $file['filename'],
						'content'   => $file['content_b64'],
						'mime_type' => $file['mime'],
					);
				},
				$prepared
			);
		}

		// Tolerate users pasting the full Authorization header value from
		// ZeptoMail's docs (e.g. "Zoho-enczapikey wSsVR60o…"). Without this
		// strip, we'd end up sending "Authorization: Zoho-enczapikey
		// Zoho-enczapikey wSsVR60o…" which ZeptoMail rejects with
		// TM_4001 / SERR_157 "Invalid API Token found".
		$token = $this->strip_auth_prefix( $this->get_api_key(), 'Zoho-enczapikey ' );

		$response = $this->make_request(
			'https://api.zeptomail.com/v1.1/email',
			array(
				'Authorization' => 'Zoho-enczapikey ' . $token,
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response, array( 200, 201 ) );
	}
}
