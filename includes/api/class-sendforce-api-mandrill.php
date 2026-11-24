<?php
/**
 * Mandrill (Mailchimp Transactional) API mailer.
 *
 * Mandrill passes the API key in the JSON body, not as a header.
 *
 * @see https://mailchimp.com/developer/transactional/api/messages/send-new-message/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Mandrill
 */
class SendForce_API_Mandrill extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Mandrill';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		// Build recipient list with type.
		$to_list = array_map( function ( $email ) {
			return $this->addr_obj( $email ) + array( 'type' => 'to' );
		}, $recipients );

		if ( ! empty( $parsed['cc'] ) ) {
			foreach ( $parsed['cc'] as $cc ) {
				$to_list[] = array(
					'email' => $this->addr_email( $cc ),
					'type'  => 'cc',
				);
			}
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			foreach ( $parsed['bcc'] as $bcc ) {
				$to_list[] = array(
					'email' => $this->addr_email( $bcc ),
					'type'  => 'bcc',
				);
			}
		}

		$msg = array(
			'from_email' => $this->get_from_email(),
			'from_name'  => $this->get_from_name(),
			'to'         => $to_list,
			'subject'    => $subject,
		);

		if ( 'text/html' === $content_type ) {
			$msg['html'] = $message;
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$msg['text'] = $plain_alt;
			}
		} else {
			$msg['text'] = $message;
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$msg['headers'] = array(
				'Reply-To' => $parsed['reply_to'],
			);
		}

		// Mandrill attachments[]: {type, name, content (base64)}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$msg['attachments'] = array_map(
				function ( $a ) {
					return array(
						'type'    => $a['mime'],
						'name'    => $a['filename'],
						'content' => $a['content_b64'],
					);
				},
				$prepared
			);
		}

		// Mandrill uses API key in the request body, not as a header.
		$body = array(
			'key'     => $this->get_api_key(),
			'message' => $msg,
		);

		$response = $this->make_request(
			'https://mandrillapp.com/api/1.0/messages/send.json',
			array(
				'Content-Type' => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
