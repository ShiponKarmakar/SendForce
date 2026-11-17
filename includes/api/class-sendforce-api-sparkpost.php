<?php
/**
 * SparkPost API mailer.
 *
 * @see https://developers.sparkpost.com/api/transmissions/
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Sparkpost
 */
class SendForce_API_Sparkpost extends SendForce_API_Mailer {

	/** @inheritDoc */
	public function get_name() {
		return 'SparkPost';
	}

	/** @inheritDoc */
	public function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$parsed     = $this->parse_headers( $headers );
		$recipients = $this->normalize_to( $to );

		$address_list = array_map( function ( $email ) {
			return array( 'address' => array( 'email' => $email ) );
		}, $recipients );

		// CC recipients: must set header_to to primary recipient.
		$cc_addresses = array();
		if ( ! empty( $parsed['cc'] ) ) {
			foreach ( $parsed['cc'] as $cc ) {
				$address_list[] = array(
					'address'   => array( 'email' => $cc, 'header_to' => $recipients[0] ),
				);
				$cc_addresses[] = $cc;
			}
		}

		// BCC recipients: same pattern but not listed in headers.
		if ( ! empty( $parsed['bcc'] ) ) {
			foreach ( $parsed['bcc'] as $bcc ) {
				$address_list[] = array(
					'address'   => array( 'email' => $bcc, 'header_to' => $recipients[0] ),
				);
			}
		}

		$content = array(
			'from'    => array(
				'name'  => $this->get_from_name(),
				'email' => $this->get_from_email(),
			),
			'subject' => $subject,
		);

		if ( 'text/html' === $parsed['content_type'] ) {
			$content['html'] = $message;
			$plain_alt = $this->get_plain_text_alt( $message, $parsed );
			if ( null !== $plain_alt ) {
				$content['text'] = $plain_alt;
			}
		} else {
			$content['text'] = $message;
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$content['reply_to'] = $parsed['reply_to'];
		}

		// Add CC header so recipients see CC addresses in their email client.
		if ( ! empty( $cc_addresses ) ) {
			$content['headers'] = array(
				'CC' => implode( ', ', $cc_addresses ),
			);
		}

		// SparkPost attachments[]: {name, type, data (base64)}.
		$prepared = $this->prepare_attachments( $attachments );
		if ( ! empty( $prepared ) ) {
			$content['attachments'] = array_map(
				function ( $a ) {
					return array(
						'name' => $a['filename'],
						'type' => $a['mime'],
						'data' => $a['content_b64'],
					);
				},
				$prepared
			);
		}

		$body = array(
			'recipients' => $address_list,
			'content'    => $content,
		);

		$response = $this->make_request(
			'https://api.sparkpost.com/api/v1/transmissions',
			array(
				'Authorization' => $this->get_api_key(),
				'Content-Type'  => 'application/json',
			),
			$body
		);

		return $this->is_success( $response );
	}
}
