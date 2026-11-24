<?php
/**
 * SendGrid API mailer.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Sendgrid
 */
class SendForce_API_Sendgrid extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SendGrid';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$personalizations = array(
			array(
				'to' => array_map( function ( $email ) {
					return $this->addr_obj( $email );
				}, $recipients ),
			),
		);

		if ( ! empty( $parsed['cc'] ) ) {
			$personalizations[0]['cc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$personalizations[0]['bcc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['bcc'] );
		}

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		// SendGrid's content[] array MUST list text/plain BEFORE text/html
		// when both are present — that's the order specified in their API
		// docs for multipart/alternative behaviour. If multipart_text is on
		// and the message is HTML, prepend the derived plain-text version.
		$content      = array();
		$plain_alt    = $this->get_plain_text_alt( $message, $parsed );
		if ( null !== $plain_alt ) {
			$content[] = array( 'type' => 'text/plain', 'value' => $plain_alt );
		}
		$content[] = array( 'type' => $content_type, 'value' => $message );

		$body = array(
			'personalizations' => $personalizations,
			'from'             => array(
				'email' => $this->get_from_email(),
				'name'  => $this->get_from_name(),
			),
			'subject'          => $subject,
			'content'          => $content,
		);

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['reply_to'] = $this->addr_obj( $parsed['reply_to'] );
		}

		// SendGrid attachments[]: {content (base64), filename, type, disposition}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $a ) {
					return array(
						'content'     => $a['content_b64'],
						'filename'    => $a['filename'],
						'type'        => $a['mime'],
						'disposition' => 'attachment',
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://api.sendgrid.com/v3/mail/send',
			array(
				'Authorization' => 'Bearer ' . $this->strip_auth_prefix( $this->get_api_key(), 'Bearer ' ),
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response, array( 200, 201, 202 ) );
	}
}
