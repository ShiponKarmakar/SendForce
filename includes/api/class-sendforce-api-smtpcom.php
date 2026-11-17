<?php
/**
 * SMTP.com API mailer.
 *
 * @see https://www.smtp.com/api-documentation/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_SMTPcom
 */
class SendForce_API_SMTPcom extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SMTP.com';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );
		$channel    = $this->settings['smtpcom_channel'] ?? 'default';

		$body = array(
			'channel'  => $channel,
			'recipients' => array(
				'to' => array_map( function ( $email ) {
					return array( 'address' => $email );
				}, $recipients ),
			),
			'originator' => array(
				'from' => array(
					'name'    => $this->get_from_name(),
					'address' => $this->get_from_email(),
				),
			),
			'subject'  => $subject,
			'body'     => array(),
		);

		$parts     = array();
		$plain_alt = $this->get_plain_text_alt( $message, $parsed );
		if ( null !== $plain_alt ) {
			$parts[] = array( 'type' => 'text/plain', 'content' => $plain_alt );
		}
		if ( 'text/html' === $parsed['content_type'] ) {
			$parts[] = array( 'type' => 'text/html', 'content' => $message );
		} else {
			$parts[] = array( 'type' => 'text/plain', 'content' => $message );
		}
		$body['body']['parts'] = $parts;

		if ( ! empty( $parsed['cc'] ) ) {
			$body['recipients']['cc'] = array_map( function ( $email ) {
				return array( 'address' => $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$body['recipients']['bcc'] = array_map( function ( $email ) {
				return array( 'address' => $email );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['originator']['reply_to'] = array( 'address' => $parsed['reply_to'] );
		}

		// SMTP.com attachments — added as additional body parts with
		// content_disposition: attachment.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			foreach ( $prepared as $a ) {
				$body['body']['parts'][] = array(
					'type'                 => $a['mime'],
					'content'              => $a['content_b64'],
					'encoding'             => 'base64',
					'content_disposition'  => 'attachment',
					'filename'             => $a['filename'],
				);
			}
		}

		$response = $this->make_request(
			'https://api.smtp.com/v4/messages',
			array(
				'Authorization' => 'Bearer ' . $this->strip_auth_prefix( $this->get_api_key(), 'Bearer ' ),
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
