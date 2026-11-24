<?php
/**
 * SocketLabs API mailer.
 *
 * @see https://help.socketlabs.com/docs/email/v2/injection-api
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Socketlabs
 */
class SendForce_API_Socketlabs extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SocketLabs';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );
		$server_id  = $this->settings['socketlabs_server_id'] ?? '';

		if ( empty( $server_id ) ) {
			return false;
		}

		$msg = array(
			'to'      => array_map( function ( $email ) {
				return $this->addr_obj( $email, 'emailAddress', 'friendlyName' );
			}, $recipients ),
			'from'    => array(
				'emailAddress' => $this->get_from_email(),
				'friendlyName' => $this->get_from_name(),
			),
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$msg['htmlBody'] = $message;
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$msg['textBody'] = $plain_alt;
			}
		} else {
			$msg['textBody'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$msg['cc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email, 'emailAddress', 'friendlyName' );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$msg['bcc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email, 'emailAddress', 'friendlyName' );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$msg['replyTo'] = $this->addr_obj( $parsed['reply_to'], 'emailAddress', 'friendlyName' );
		}

		// SocketLabs attachments[]: {name, contentType, content (base64)}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$msg['attachments'] = array_map(
				function ( $a ) {
					return array(
						'name'        => $a['filename'],
						'contentType' => $a['mime'],
						'content'     => $a['content_b64'],
					);
				},
				$prepared
			);
		}

		$body = array(
			'serverId' => intval( $server_id ),
			'apiKey'   => $this->get_api_key(),
			'messages' => array( $msg ),
		);

		$response = $this->make_request(
			'https://inject.socketlabs.com/api/v1/email',
			array( 'Content-Type' => 'application/json' ),
			$body
		);

		return $this->is_success( $response );
	}
}
