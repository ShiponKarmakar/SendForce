<?php
/**
 * Elastic Email API mailer.
 *
 * @see https://elasticemail.com/developers/api-documentation/rest-api
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Elasticemail
 */
class SendForce_API_Elasticemail extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Elastic Email';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$body = array(
			'Recipients' => array(
				'To' => $recipients,
			),
			'Content'    => array(
				'From'    => $this->get_from_email(),
				'Subject' => $subject,
			),
		);

		if ( $this->get_from_name() ) {
			$body['Content']['From'] = $this->get_from_name() . ' <' . $this->get_from_email() . '>';
		}

		$body_parts = array();
		$plain_alt  = $this->get_plain_text_alt( $message, $parsed );
		if ( null !== $plain_alt ) {
			$body_parts[] = array( 'ContentType' => 'PlainText', 'Content' => $plain_alt );
		}
		if ( 'text/html' === $parsed['content_type'] ) {
			$body_parts[] = array( 'ContentType' => 'HTML', 'Content' => $message );
		} else {
			$body_parts[] = array( 'ContentType' => 'PlainText', 'Content' => $message );
		}
		$body['Content']['Body'] = $body_parts;

		if ( ! empty( $parsed['cc'] ) ) {
			$body['Recipients']['CC'] = $parsed['cc'];
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['Recipients']['BCC'] = $parsed['bcc'];
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['Content']['ReplyTo'] = $parsed['reply_to'];
		}

		// Elastic Email v4 Attachments[]: {BinaryContent (base64), Name, ContentType}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['Content']['Attachments'] = array_map(
				function ( $a ) {
					return array(
						'BinaryContent' => $a['content_b64'],
						'Name'          => $a['filename'],
						'ContentType'   => $a['mime'],
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.elasticemail.com/v4/emails/transactional',
			array(
				'X-ElasticEmail-ApiKey' => $this->get_api_key(),
				'Content-Type'          => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
