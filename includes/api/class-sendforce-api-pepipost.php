<?php
/**
 * Pepipost/Netcore API mailer.
 *
 * @see https://cpaasdocs.netcorecloud.com/docs/pepipost-api/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Pepipost
 */
class SendForce_API_Pepipost extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'Pepipost/Netcore';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$personalizations = array(
			'to' => array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $recipients ),
		);

		if ( ! empty( $parsed['cc'] ) ) {
			$personalizations['cc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$personalizations['bcc'] = array_map( function ( $email ) {
				return $this->addr_obj( $email );
			}, $parsed['bcc'] );
		}

		// Build content[] — supports both text + html when multipart_text is on.
		$content = array();
		$plain_alt = $this->get_plain_text_alt( $message, $parsed );
		if ( null !== $plain_alt ) {
			$content[] = array( 'type' => 'text', 'value' => $plain_alt );
		}
		$content[] = array(
			'type'  => 'text/html' === $parsed['content_type'] ? 'html' : 'text',
			'value' => $message,
		);

		$body = array(
			'from'    => array(
				'email' => $this->get_from_email(),
				'name'  => $this->get_from_name(),
			),
			'subject' => $subject,
			'personalizations' => array( $personalizations ),
			'content' => $content,
		);

		if ( ! empty( $parsed['reply_to'] ) ) {
			$body['reply_to'] = $this->addr_obj( $parsed['reply_to'] );
		}

		// Pepipost attachments[]: {content (base64), name}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$body['attachments'] = array_map(
				function ( $a ) {
					return array(
						'content' => $a['content_b64'],
						'name'    => $a['filename'],
					);
				},
				$prepared
			);
		}

		$response = $this->make_request(
			'https://emailapi.netcorecloud.net/v5/mail/send',
			array(
				'api_key'      => $this->get_api_key(),
				'Content-Type' => 'application/json',
			),
			$body
		);

		return $this->is_success( $response, array( 200, 202 ) );
	}
}
