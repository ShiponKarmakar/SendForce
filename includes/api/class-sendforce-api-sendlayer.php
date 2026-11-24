<?php
/**
 * SendLayer API mailer.
 *
 * @see https://developers.sendlayer.com/api-reference/endpoint/email
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Sendlayer
 */
class SendForce_API_Sendlayer extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SendLayer';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		$body = array(
			'From' => array(
				'email' => $this->get_from_email(),
				'name'  => $this->get_from_name(),
			),
			'To'      => array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $recipients ),
			'Subject' => $subject,
		);

		if ( 'text/html' === $content_type ) {
			$body['ContentType'] = 'HTML';
			$body['HTMLContent'] = $message;
			// SendLayer accepts a PlainContent alongside HTMLContent for
			// multipart/alternative behaviour.
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$body['PlainContent'] = $plain_alt;
			}
		} else {
			$body['ContentType']  = 'Text';
			$body['PlainContent'] = $message;
		}

		// SendLayer Attachments[]: {Content (base64), Name, Type}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['Attachments'] = array_map(
				function ( $a ) {
					return array(
						'Content' => $a['content_b64'],
						'Name'    => $a['filename'],
						'Type'    => $a['mime'],
					);
				},
				$prepared
			);
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['CC'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['BCC'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['ReplyTo'] = $this->addr_obj( $parsed['reply_to'] );
		}

		$response = $this->make_request(
			'https://console.sendlayer.com/api/v1/email',
			array(
				'Authorization' => 'Bearer ' . $this->strip_auth_prefix( $this->get_api_key(), 'Bearer ' ),
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
