<?php
/**
 * Mailjet API mailer.
 *
 * Uses HTTP Basic Auth with API Key (public) and Secret Key (private).
 *
 * @see https://dev.mailjet.com/email/guides/send-api-v31/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Mailjet
 */
class SendForce_API_Mailjet extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Mailjet';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		// Mailjet uses Basic Auth: API Key (public) as username, Secret Key as password.
		$api_key_public  = $this->settings['username'] ?? '';
		$api_key_private = SendForce_Mailer::decrypt_password( $this->settings['api_key'] ?? '' );

		if ( empty( $api_key_public ) || empty( $api_key_private ) ) {
			return false;
		}

		$content_type = 'text/html' === $parsed['content_type'] ? 'text/html' : 'text/plain';

		$msg = array(
			'From' => array(
				'Email' => $this->get_from_email(),
				'Name'  => $this->get_from_name(),
			),
			'To' => array_map( function ( $email ) {
				return $this->addr_obj( $email, 'Email', 'Name' );
			}, $recipients ),
			'Subject' => $subject,
		);

		if ( 'text/html' === $content_type ) {
			$msg['HTMLPart'] = $message;
			// Mailjet accepts both HTMLPart + TextPart in the same message.
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$msg['TextPart'] = $plain_alt;
			}
		} else {
			$msg['TextPart'] = $message;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$msg['Cc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email, 'Email', 'Name' );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$msg['Bcc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email, 'Email', 'Name' );
			}, $parsed['bcc'] );
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$msg['ReplyTo'] = $this->addr_obj( $parsed['reply_to'], 'Email', 'Name' );
		}

		// Mailjet v3.1 Attachments[]: {ContentType, Filename, Base64Content}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$msg['Attachments'] = array_map(
				function ( $a ) {
					return array(
						'ContentType'   => $a['mime'],
						'Filename'      => $a['filename'],
						'Base64Content' => $a['content_b64'],
					);
				},
				$prepared
			);
		}

		$body = array( 'Messages' => array( $msg ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for HTTP Basic Auth.
		$auth = base64_encode( $api_key_public . ':' . $api_key_private );

		$response = $this->make_request(
			'https://api.mailjet.com/v3.1/send',
			array(
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
