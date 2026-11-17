<?php
/**
 * Moosend API mailer.
 *
 * @see https://moosendapp.docs.apiary.io/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Moosend
 */
class SendForce_API_Moosend extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Moosend';
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

		// Moosend's API treats unset IsContentHtml inconsistently — set it
		// explicitly in BOTH paths so the rendering matches what the user sent.
		$body['Content']       = $message;
		$body['IsContentHtml'] = ( 'text/html' === $parsed['content_type'] );

		if ( ! empty( $parsed['cc'] ) ) {
			$body['Cc'] = implode( ', ', $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['Bcc'] = implode( ', ', $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['ReplyTo'] = $parsed['reply_to'];
		}

		// Moosend Attachments[]: {Name, Data (base64), ContentType}.
		// Note: Moosend's transactional API has a single Content field so
		// multipart_text (separate text + html) isn't representable in the
		// JSON schema. Whatever Content type the user picked is what's sent.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['Attachments'] = array_map(
				function ( $a ) {
					return array(
						'Name'        => $a['filename'],
						'Data'        => $a['content_b64'],
						'ContentType' => $a['mime'],
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.moosend.com/v3/emails/send.json?apikey=' . rawurlencode( $this->get_api_key() ),
			array( 'Content-Type' => 'application/json' ),
			$body
		);

		return $this->is_success( $response );
	}
}
