<?php
/**
 * Alerts: summary emails and error notifications.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Alerts
 *
 * Handles daily summary emails and real-time error notifications
 * via Telegram, Slack, Discord, and Pushover.
 */
class SendForce_Alerts {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );
		add_action( 'sendforce_send_daily_summary', array( $this, 'send_daily_summary' ) );
	}

	/**
	 * Fired when wp_mail fails.
	 *
	 * Rate-limited per channel so a burst of failures (e.g. WooCommerce
	 * batch order email run with an expired API key) doesn't spam every
	 * enabled channel with hundreds of identical pings. Within a sliding
	 * 5-minute window we send AT MOST one notification per channel; the
	 * second-through-Nth failure increments an "additional failures"
	 * counter that's appended to the next ping that goes out (so the user
	 * still knows the volume, just in one message instead of N).
	 *
	 * Window length is filterable via `sendforce_alerts_throttle_seconds`
	 * for sites that want different behaviour. Pass 0 to disable throttling.
	 *
	 * @param WP_Error $wp_error The error object.
	 */
	public function on_mail_failed( $wp_error ) {
		$settings = self::get_alerts_settings();
		$channels = $settings['channels'] ?? array();

		$error_message = $wp_error->get_error_message();
		$site_name     = get_bloginfo( 'name' );
		$site_url      = get_bloginfo( 'url' );

		/**
		 * Filter the throttle window length in seconds. Default 300 (5 min).
		 * Set to 0 to disable throttling entirely (one alert per failure).
		 *
		 * @param int $seconds Throttle window in seconds.
		 */
		$throttle = (int) apply_filters( 'sendforce_alerts_throttle_seconds', 5 * MINUTE_IN_SECONDS );

		foreach ( $channels as $channel => $channel_settings ) {
			if ( empty( $channel_settings['enabled'] ) ) {
				continue;
			}

			$message = sprintf(
				/* translators: 1: site name, 2: site URL, 3: line break, 4: error message */
				__( '[SendForce Mail Relay] Email sending failed on %1$s (%2$s)%3$sError: %4$s', 'sendforce-mail-relay' ),
				$site_name,
				$site_url,
				"\n",
				$error_message
			);

			if ( $throttle > 0 ) {
				$state_key = 'sendforce_alert_throttle_' . sanitize_key( $channel );
				$state     = get_transient( $state_key );

				if ( is_array( $state ) && ! empty( $state['locked_until'] ) && $state['locked_until'] > time() ) {
					// Inside the throttle window — increment the suppressed
					// counter and skip sending. The next alert AFTER the
					// window expires will surface this count to the user.
					$state['suppressed'] = isset( $state['suppressed'] ) ? (int) $state['suppressed'] + 1 : 1;
					$state['last_error'] = $error_message;
					set_transient( $state_key, $state, $throttle * 2 );
					continue;
				}

				// Outside the window (or first time): allowed to send. If
				// there were suppressed alerts during the PREVIOUS window
				// that we never told the user about, append a one-liner so
				// the volume is visible.
				$prev_suppressed = ( is_array( $state ) && ! empty( $state['suppressed'] ) ) ? (int) $state['suppressed'] : 0;
				if ( $prev_suppressed > 0 ) {
					$message .= "\n" . sprintf(
						/* translators: %d: number of suppressed alerts */
						_n( '(+%d additional failure was suppressed during the throttle window)', '(+%d additional failures were suppressed during the throttle window)', $prev_suppressed, 'sendforce-mail-relay' ),
						$prev_suppressed
					);
				}

				set_transient(
					$state_key,
					array(
						'locked_until' => time() + $throttle,
						'suppressed'   => 0,
						'last_error'   => $error_message,
					),
					$throttle * 2
				);
			}

			$this->notify_channel( $channel, $channel_settings, $message );
		}
	}

	/**
	 * Send daily summary email.
	 */
	public function send_daily_summary() {
		$settings = self::get_alerts_settings();

		if ( empty( $settings['summary_enabled'] ) ) {
			return;
		}

		// Check if today is in the configured days.
		$today         = strtolower( wp_date( 'D' ) ); // mon, tue, wed, etc.
		$summary_days  = $settings['summary_days'] ?? array();

		if ( ! in_array( $today, $summary_days, true ) ) {
			return;
		}

		// Build stats for last 24 hours.
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

		$sent = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'sent' AND created_at >= %s",
				$since
			)
		);

		$failed = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs WHERE status = 'failed' AND created_at >= %s",
				$since
			)
		);

		$total       = $sent + $failed;
		$success_pct = $total > 0 ? round( ( $sent / $total ) * 100, 1 ) : 0;

		$site_name = get_bloginfo( 'name' );
		$site_url  = get_bloginfo( 'url' );
		$date      = wp_date( 'F j, Y' );

		// Build HTML email.
		$subject = sprintf(
			/* translators: 1: site name, 2: date */
			__( '[SendForce Mail Relay] Daily Email Summary for %1$s — %2$s', 'sendforce-mail-relay' ),
			$site_name,
			$date
		);

		$body  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#1E293B;background:#F0F2F5;padding:20px;">';
		$body .= '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">';
		$body .= '<div style="background:#0C1E2C;padding:20px 24px;">';
		$body .= '<h1 style="margin:0;color:#1dd1a1;font-size:20px;">SendForce Mail Relay Daily Summary</h1>';
		$body .= '<p style="margin:4px 0 0;color:#94A3B8;font-size:13px;">' . esc_html( $site_name ) . ' &mdash; ' . esc_html( $date ) . '</p>';
		$body .= '</div>';
		$body .= '<div style="padding:24px;">';
		$body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
		$body .= '<tr>';
		$body .= '<td style="padding:16px;text-align:center;background:#DCFCE7;border-radius:8px;margin:4px;">';
		$body .= '<div style="font-size:32px;font-weight:700;color:#16A34A;">' . esc_html( $sent ) . '</div>';
		$body .= '<div style="font-size:12px;color:#64748B;margin-top:4px;">' . esc_html__( 'Sent', 'sendforce-mail-relay' ) . '</div>';
		$body .= '</td>';
		$body .= '<td style="width:16px;"></td>';
		$body .= '<td style="padding:16px;text-align:center;background:#FEE2E2;border-radius:8px;">';
		$body .= '<div style="font-size:32px;font-weight:700;color:#DC2626;">' . esc_html( $failed ) . '</div>';
		$body .= '<div style="font-size:12px;color:#64748B;margin-top:4px;">' . esc_html__( 'Failed', 'sendforce-mail-relay' ) . '</div>';
		$body .= '</td>';
		$body .= '<td style="width:16px;"></td>';
		$body .= '<td style="padding:16px;text-align:center;background:#DBEAFE;border-radius:8px;">';
		$body .= '<div style="font-size:32px;font-weight:700;color:#2563EB;">' . esc_html( $success_pct ) . '%</div>';
		$body .= '<div style="font-size:12px;color:#64748B;margin-top:4px;">' . esc_html__( 'Success Rate', 'sendforce-mail-relay' ) . '</div>';
		$body .= '</td>';
		$body .= '</tr></table>';
		$body .= '<p style="margin:0 0 16px;font-size:13px;color:#64748B;">' . esc_html__( 'This is your automated daily summary from SendForce Mail Relay. To change notification settings, visit your WordPress admin panel.', 'sendforce-mail-relay' ) . '</p>';
		$body .= '<a href="' . esc_url( admin_url( 'admin.php?page=sendforce-mail-relay&tab=email-log' ) ) . '" style="display:inline-block;background:#1dd1a1;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;">' . esc_html__( 'View Email Log', 'sendforce-mail-relay' ) . '</a>';
		$body .= '</div>';
		$body .= '<div style="background:#F8FAFC;padding:16px 24px;border-top:1px solid #E2E8F0;">';
		$body .= '<p style="margin:0;font-size:11px;color:#94A3B8;">' . esc_html( $site_url ) . '</p>';
		$body .= '</div>';
		$body .= '</div></body></html>';

		// Resolve recipients.
		$admin_email  = get_option( 'admin_email' );
		$raw_emails   = $settings['summary_emails'] ?? '{site_admin}';
		$raw_emails   = str_replace( '{site_admin}', $admin_email, $raw_emails );
		$email_lines  = preg_split( '/[\r\n,]+/', $raw_emails );
		$recipients   = array();

		foreach ( $email_lines as $line ) {
			$line = trim( $line );
			if ( is_email( $line ) ) {
				$recipients[] = $line;
			}
		}

		if ( empty( $recipients ) ) {
			$recipients = array( $admin_email );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		foreach ( $recipients as $recipient ) {
			wp_mail( $recipient, $subject, $body, $headers );
		}
	}

	/**
	 * Validate a user-supplied webhook URL before we POST to it, to defend
	 * against SSRF. A compromised or careless admin could otherwise point an
	 * alert webhook at internal infrastructure (cloud metadata endpoints,
	 * private services) and use SendForce as a blind request proxy.
	 *
	 * Rules:
	 *  - must be a syntactically valid http(s) URL (wp_http_validate_url also
	 *    rejects credentialed URLs and non-standard ports by default);
	 *  - host must not resolve to a private, loopback, link-local or reserved
	 *    IP range (blocks 127.0.0.0/8, 10/8, 172.16/12, 192.168/16,
	 *    169.254/16 incl. the 169.254.169.254 metadata IP, ::1, fc00::/7,
	 *    fe80::/10, etc.).
	 *
	 * Filterable via `sendforce_allow_private_webhook_hosts` for the rare
	 * self-hosted setup that genuinely needs to hit an internal collector.
	 *
	 * @param string $url Raw webhook URL from channel settings.
	 * @return bool True when the URL is safe to request.
	 */
	private function is_safe_webhook_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}

		// Allow operators to opt back into private-network webhooks.
		if ( apply_filters( 'sendforce_allow_private_webhook_hosts', false, $url ) ) {
			return (bool) wp_http_validate_url( $url );
		}

		// wp_http_validate_url() enforces scheme (http/https), rejects
		// userinfo (user:pass@), and already blocks obvious private hosts —
		// but its IP checks don't cover every reserved range, so we re-check
		// the resolved address(es) ourselves below.
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		// Strip IPv6 brackets if present.
		$host = trim( $host, '[]' );

		// Resolve to IPs. If the host is already an IP literal, gethostbynamel
		// returns false, so fall back to treating $host as the address.
		$ips = array();
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host;
		} else {
			$resolved = gethostbynamel( $host );
			if ( is_array( $resolved ) ) {
				$ips = $resolved;
			}
			// gethostbynamel is IPv4-only; also try AAAA via dns_get_record
			// when available so an AAAA-only internal host can't slip past.
			if ( function_exists( 'dns_get_record' ) ) {
				$aaaa = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort lookup; failure is non-fatal.
				if ( is_array( $aaaa ) ) {
					foreach ( $aaaa as $rec ) {
						if ( ! empty( $rec['ipv6'] ) ) {
							$ips[] = $rec['ipv6'];
						}
					}
				}
			}
		}

		// If we couldn't resolve anything, fail closed — better to not send an
		// alert than to send one to an unverifiable host.
		if ( empty( $ips ) ) {
			return false;
		}

		foreach ( $ips as $ip ) {
			// FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE reject private and
			// reserved ranges (covers loopback, link-local, and the metadata
			// IP for both IPv4 and IPv6).
			if ( ! filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Route notification to the correct channel.
	 *
	 * @param string $channel  Channel key (telegram|slack|discord|pushover).
	 * @param array  $settings Channel settings.
	 * @param string $message  The message to send.
	 */
	private function notify_channel( $channel, $settings, $message ) {
		switch ( $channel ) {
			case 'telegram':
				$this->notify_telegram( $settings, $message );
				break;
			case 'slack':
				$this->notify_slack( $settings, $message );
				break;
			case 'discord':
				$this->notify_discord( $settings, $message );
				break;
			case 'pushover':
				$this->notify_pushover( $settings, $message );
				break;
			case 'teams':
				$this->notify_teams( $settings, $message );
				break;
			case 'google_chat':
				$this->notify_google_chat( $settings, $message );
				break;
			case 'webhook':
				$this->notify_webhook( $settings, $message );
				break;
		}
	}

	/**
	 * Send Telegram notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 */
	public function notify_telegram( $settings, $message ) {
		$token   = $settings['bot_token'] ?? '';
		$chat_id = $settings['chat_id'] ?? '';

		if ( empty( $token ) || empty( $chat_id ) ) {
			return false;
		}

		$url = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat_id,
						'text'    => $message,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === (int) $code );
	}

	/**
	 * Send Slack notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 */
	public function notify_slack( $settings, $message ) {
		$webhook_url = $settings['webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// SSRF guard — never POST a user-supplied webhook URL that resolves to
		// a private/internal/reserved IP. See is_safe_webhook_url().
		if ( ! $this->is_safe_webhook_url( $webhook_url ) ) {
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $message ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === (int) $code );
	}

	/**
	 * Send Discord notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 */
	public function notify_discord( $settings, $message ) {
		$webhook_url = $settings['webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// SSRF guard — never POST a user-supplied webhook URL that resolves to
		// a private/internal/reserved IP. See is_safe_webhook_url().
		if ( ! $this->is_safe_webhook_url( $webhook_url ) ) {
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'content' => $message ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		// Discord returns 204 No Content on success.
		return in_array( (int) $code, array( 200, 204 ), true );
	}

	/**
	 * Send Pushover notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 */
	public function notify_pushover( $settings, $message ) {
		$user_key  = $settings['user_key'] ?? '';
		$api_token = $settings['api_token'] ?? '';

		if ( empty( $user_key ) || empty( $api_token ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.pushover.net/1/messages.json',
			array(
				'timeout' => 10,
				'body'    => array(
					'token'   => $api_token,
					'user'    => $user_key,
					'message' => $message,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === (int) $code );
	}

	/**
	 * Send Microsoft Teams notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 * @return bool
	 */
	public function notify_teams( $settings, $message ) {
		$webhook_url = $settings['webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// SSRF guard — never POST a user-supplied webhook URL that resolves to
		// a private/internal/reserved IP. See is_safe_webhook_url().
		if ( ! $this->is_safe_webhook_url( $webhook_url ) ) {
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $message ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === (int) $code );
	}

	/**
	 * Send Google Chat notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 * @return bool
	 */
	public function notify_google_chat( $settings, $message ) {
		$webhook_url = $settings['webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// SSRF guard — never POST a user-supplied webhook URL that resolves to
		// a private/internal/reserved IP. See is_safe_webhook_url().
		if ( ! $this->is_safe_webhook_url( $webhook_url ) ) {
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $message ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === (int) $code );
	}

	/**
	 * Send Custom Webhook notification.
	 *
	 * @param array  $settings Channel settings.
	 * @param string $message  Message text.
	 * @return bool
	 */
	public function notify_webhook( $settings, $message ) {
		$webhook_url = $settings['webhook_url'] ?? '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// SSRF guard — never POST a user-supplied webhook URL that resolves to
		// a private/internal/reserved IP. See is_safe_webhook_url().
		if ( ! $this->is_safe_webhook_url( $webhook_url ) ) {
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout'     => 10,
				// Do not follow redirects: the SSRF pre-check (is_safe_webhook_url)
				// only validates the URL we were given, so a malicious endpoint
				// could otherwise 3xx-redirect us to an internal host after
				// passing it. Legitimate webhooks (Slack, Discord, Teams, Google
				// Chat) always respond directly, so this never breaks real use.
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'event'     => 'email_failed',
						'message'   => $message,
						'site'      => get_bloginfo( 'url' ),
						'timestamp' => current_time( 'c' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return ( $code >= 200 && $code < 300 );
	}

	/**
	 * Get alert settings with defaults.
	 *
	 * @return array
	 */
	public static function get_alerts_settings() {
		$defaults = array(
			'summary_enabled' => false,
			'summary_emails'  => '{site_admin}',
			'summary_days'    => array( 'mon', 'wed', 'fri' ),
			'channels'        => array(
				'telegram' => array(
					'enabled'   => false,
					'bot_token' => '',
					'chat_id'   => '',
				),
				'slack'    => array(
					'enabled'     => false,
					'webhook_url' => '',
				),
				'discord'  => array(
					'enabled'     => false,
					'webhook_url' => '',
				),
				'pushover'    => array(
					'enabled'   => false,
					'user_key'  => '',
					'api_token' => '',
				),
				'teams'       => array(
					'enabled'     => false,
					'webhook_url' => '',
				),
				'google_chat' => array(
					'enabled'     => false,
					'webhook_url' => '',
				),
				'webhook'     => array(
					'enabled'     => false,
					'webhook_url' => '',
				),
			),
		);

		$saved = get_option( 'sendforce_alerts', array() );

		// Deep merge channels so individual channel defaults are preserved.
		if ( isset( $saved['channels'] ) && is_array( $saved['channels'] ) ) {
			foreach ( $saved['channels'] as $channel => $data ) {
				if ( isset( $defaults['channels'][ $channel ] ) ) {
					$defaults['channels'][ $channel ] = array_merge( $defaults['channels'][ $channel ], $data );
				}
			}
			unset( $saved['channels'] );
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Save channel-specific settings.
	 *
	 * @param string $channel Channel key.
	 * @param array  $data    Channel data to save.
	 */
	public static function save_channel_settings( $channel, $data ) {
		$settings = get_option( 'sendforce_alerts', array() );

		if ( ! isset( $settings['channels'] ) ) {
			$settings['channels'] = array();
		}

		$existing = $settings['channels'][ $channel ] ?? array();
		$settings['channels'][ $channel ] = array_merge( $existing, $data );

		update_option( 'sendforce_alerts', $settings );
	}
}
