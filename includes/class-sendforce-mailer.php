<?php
/**
 * SMTP mailer configuration.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Mailer
 *
 * Configures WordPress to send emails via SMTP using phpmailer_init.
 */
class SendForce_Mailer {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Resolved fallback connection settings (merged with global from_*), or
	 * null when no fallback is configured / valid.
	 *
	 * @var array|null
	 */
	private $fallback_settings = null;

	/**
	 * Snapshot of the primary $settings, taken when Smart Routing temporarily
	 * swaps in a routed connection. Restored after the send completes.
	 *
	 * @var array|null
	 */
	private $primary_settings_backup = null;

	/**
	 * Set to true by attempt_fallback() the moment a fallback send succeeds,
	 * so send_via_api() can return TRUE to wp_mail() in the same request.
	 * Reset at the start of every send.
	 *
	 * @var bool
	 */
	private $fallback_just_succeeded = false;

	/**
	 * Globally-readable signal that the most recent send attempt in THIS
	 * request was recovered via the fallback connection. Static so the
	 * test-email AJAX handler (and any external listener) can read it
	 * without holding a reference to the mailer instance.
	 *
	 * @var bool
	 */
	public static $last_send_used_fallback = false;

	/**
	 * Set when decrypt_password() was handed a value that is clearly encrypted
	 * but could not be decrypted with either the current or the legacy key —
	 * i.e. the credential is unrecoverable (almost always because the site's
	 * salts were rotated before 1.0.37 made the key stable). The admin layer
	 * reads this to warn the user to re-enter the password, instead of
	 * silently attempting to send with an empty credential.
	 *
	 * @var bool
	 */
	public static $last_decrypt_failed = false;

	/**
	 * Human-readable label of the fallback connection used in the most recent
	 * send (for display in the test-email success message).
	 *
	 * @var string
	 */
	public static $last_fallback_label = '';

	/**
	 * Label of the routing rule that matched the most recent wp_mail call,
	 * or '' when no rule matched (and the primary connection was used).
	 * Read by the logger to surface the route on the email-log row.
	 *
	 * @var string
	 */
	public static $last_matched_rule_label = '';

	/**
	 * Connection-config keys merged from a sendforce_connections row into the
	 * working settings array.
	 *
	 * @var array
	 */
	private static $connection_keys = array( 'provider', 'connection_type', 'host', 'port', 'encryption', 'auth', 'username', 'password', 'api_key', 'ses_region', 'ses_access_key', 'mailgun_domain', 'mailgun_region', 'smtpcom_channel', 'socketlabs_server_id', 'outlook_tenant_id', 'outlook_client_id', 'gmail_client_id' );

	/**
	 * Initialize the mailer hooks.
	 */
	public function init() {
		$this->settings = get_option( 'sendforce_settings', array() );

		// Load the active connection from sendforce_connections and merge into settings.
		$default_id = $this->settings['default_connection'] ?? '';
		$this->settings = $this->merge_connection_into( $this->settings, $default_id );

		// Resolve the fallback connection (if configured AND different from primary).
		$fallback_id = $this->settings['fallback_connection'] ?? '';
		if ( $fallback_id && $fallback_id !== $default_id ) {
			$base = get_option( 'sendforce_settings', array() );
			$this->fallback_settings = $this->merge_connection_into( $base, $fallback_id );
		}

		// Email Simulation Mode — intercept before sending.
		if ( ! empty( $this->settings['simulation_mode'] ) ) {
			add_filter( 'pre_wp_mail', array( $this, 'intercept_simulation' ), 1, 2 );

			// Override from email and name (both modes).
			if ( ! empty( $this->settings['from_email'] ) ) {
				add_filter( 'wp_mail_from', array( $this, 'set_from_email' ) );
			}
			if ( ! empty( $this->settings['from_name'] ) ) {
				add_filter( 'wp_mail_from_name', array( $this, 'set_from_name' ) );
			}

			// Register custom cron schedule.
			add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
			return; // Don't configure SMTP or API hooks.
		}

		// Routing — runs at priority 5 (BEFORE send_via_api at 10) so that a
		// matched rule can swap $this->settings before any send hook fires.
		add_filter( 'pre_wp_mail', array( $this, 'apply_routing' ), 5, 2 );

		// Both send paths are registered ALWAYS so that routing can switch
		// between API and SMTP connections freely. Each one short-circuits
		// when the (possibly routed) settings don't match its mode.
		add_filter( 'pre_wp_mail', array( $this, 'send_via_api' ), 10, 2 );
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 10, 1 );

		// Multi-Part Plain Text — add AltBody to HTML emails.
		if ( ! empty( $this->settings['multipart_text'] ) ) {
			add_action( 'phpmailer_init', array( $this, 'add_plain_text_alt' ), 20, 1 );
		}

		// Override from email and name (both modes).
		if ( ! empty( $this->settings['from_email'] ) ) {
			add_filter( 'wp_mail_from', array( $this, 'set_from_email' ) );
		}
		if ( ! empty( $this->settings['from_name'] ) ) {
			add_filter( 'wp_mail_from_name', array( $this, 'set_from_name' ) );
		}

		// Register custom cron schedule.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

		// Mirror SMTP conversation to debug.log when developer_debug is enabled.
		if ( ! empty( $this->settings['developer_debug'] ) ) {
			add_action( 'phpmailer_init', array( $this, 'capture_smtp_debug' ), 9999 );
		}

		// Fallback connection: when the primary send fails, retry once via the
		// fallback. Hooked at priority 5 so we run BEFORE the logger (priority
		// 10) — that way the original failure is still logged for an audit
		// trail, plus a second "sent (via fallback)" log entry on success.
		if ( $this->fallback_settings ) {
			add_action( 'wp_mail_failed', array( $this, 'attempt_fallback' ), 5 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		}
	}

	/**
	 * Take a base settings array and merge in the chosen connection's config
	 * from sendforce_connections. Returns the merged array unchanged if no
	 * matching connection is found, or pre-tagged for "phpmail" mode.
	 *
	 * @param array  $base       Base settings (sendforce_settings).
	 * @param string $connection_id Connection id to merge in, or 'phpmail'.
	 * @return array Merged settings array.
	 */
	private function merge_connection_into( $base, $connection_id ) {
		if ( 'phpmail' === $connection_id ) {
			$base['provider'] = 'phpmail';
			return $base;
		}
		if ( empty( $connection_id ) ) {
			return $base;
		}
		$connections = get_option( 'sendforce_connections', array() );
		foreach ( $connections as $conn ) {
			if ( isset( $conn['id'] ) && $conn['id'] === $connection_id ) {
				foreach ( self::$connection_keys as $key ) {
					if ( isset( $conn[ $key ] ) ) {
						$base[ $key ] = $conn[ $key ];
					}
				}
				return $base;
			}
		}
		return $base;
	}

	/**
	 * Hook PHPMailer to mirror SMTP debug output to error_log (when WP_DEBUG_LOG is on).
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance.
	 */
	public function capture_smtp_debug( $phpmailer ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPDebug = max( 2, (int) $phpmailer->SMTPDebug );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Debugoutput = function ( $str ) {
			self::log_debug( 'SMTP', trim( $str ) );
		};
	}

	/**
	 * Write a SendForce debug entry to the WordPress debug log.
	 *
	 * Only writes when both WP_DEBUG and WP_DEBUG_LOG are enabled, AND the
	 * "Developer debug" setting is on. Secrets are redacted upstream — this
	 * helper does not sanitize.
	 *
	 * @param string $context Short context label (e.g. "SMTP", "API sendgrid").
	 * @param mixed  $payload String or array.
	 */
	public static function log_debug( $context, $payload ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}
		$settings = get_option( 'sendforce_settings', array() );
		if ( empty( $settings['developer_debug'] ) ) {
			return;
		}

		$line = '[SendForce ' . $context . '] ' . ( is_string( $payload ) ? $payload : wp_json_encode( $payload ) );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}

	/**
	 * Send email via provider API instead of SMTP.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts   wp_mail attributes (to, subject, message, headers, attachments).
	 * @return bool|null True if sent via API, null to fall back to SMTP.
	 */
	/**
	 * Apply Smart Routing rules. Runs at pre_wp_mail priority 5 — before the
	 * API send and PHPMailer hooks at priority 10 — so a matched rule can
	 * temporarily swap $this->settings to a different connection's config
	 * for this single send.
	 *
	 * Returns null in all cases (we never short-circuit wp_mail here).
	 *
	 * @param null|bool $return Existing short-circuit value.
	 * @param array     $atts   wp_mail attributes.
	 * @return null
	 */
	public function apply_routing( $return, $atts ) {
		if ( null !== $return ) {
			return $return; // Another filter handled it; routing has no say.
		}

		// Reset rule signal at the start of every send (mirrors fallback signals).
		self::$last_matched_rule_label = '';

		$routed = SendForce_Router::resolve_connection_for( $atts );
		if ( null === $routed ) {
			return null; // No rule matched — keep primary connection.
		}

		// Surface which rule matched so the logger / log list can show it.
		if ( ! empty( SendForce_Router::$last_matched_rule['label'] ) ) {
			self::$last_matched_rule_label = (string) SendForce_Router::$last_matched_rule['label'];
		}

		// Stash the primary settings so we can restore after the send.
		$this->primary_settings_backup = $this->settings;

		// Preserve any from_email / from_name override from primary so the
		// "WordPress Website" identity stays consistent across routed sends.
		if ( ! empty( $this->settings['from_email'] ) ) {
			$routed['from_email'] = $this->settings['from_email'];
		}
		if ( ! empty( $this->settings['from_name'] ) ) {
			$routed['from_name'] = $this->settings['from_name'];
		}

		$this->settings = $routed;

		// Restore after the send completes (success or fail), so the next
		// wp_mail call starts fresh with the primary connection.
		$restore = function () use ( &$restore ) {
			if ( ! empty( $this->primary_settings_backup ) ) {
				$this->settings                 = $this->primary_settings_backup;
				$this->primary_settings_backup = null;
			}
			remove_action( 'wp_mail_succeeded', $restore );
			remove_action( 'wp_mail_failed',    $restore );
		};
		add_action( 'wp_mail_succeeded', $restore, 999 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		add_action( 'wp_mail_failed',    $restore, 999 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.

		return null;
	}

	public function send_via_api( $return, $atts ) {
		// Another filter (simulation, queue, custom) already handled this send.
		if ( null !== $return ) {
			return $return;
		}

		// Only act when the (possibly routed) connection is in API mode.
		$connection_type = $this->settings['connection_type'] ?? 'smtp';
		if ( 'api' !== $connection_type ) {
			return null;
		}

		// Reset the fallback signals at the start of every send.
		$this->fallback_just_succeeded   = false;
		self::$last_send_used_fallback   = false;
		self::$last_fallback_label       = '';

		$provider = $this->settings['provider'] ?? '';
		$mailer   = SendForce_API_Mailer::get_instance( $provider, $this->settings );

		if ( ! $mailer ) {
			return null; // Fall back to SMTP.
		}

		/**
		 * Fires before SendForce hands an email off to a provider API.
		 * Filter $atts to mutate recipients/subject/headers, or short-circuit by
		 * returning a boolean from a sibling pre_wp_mail filter.
		 *
		 * @param array  $atts     wp_mail attributes (to, subject, message, headers, attachments).
		 * @param string $provider Provider key (e.g. "sendgrid", "mailgun").
		 * @param string $mode     Always "api" here.
		 */
		do_action( 'sendforce_before_send', $atts, $provider, 'api' );

		$to          = $atts['to'] ?? '';
		$subject     = $atts['subject'] ?? '';
		$message     = $atts['message'] ?? '';
		$headers     = $atts['headers'] ?? '';
		$attachments = $atts['attachments'] ?? array();

		// Use the From the sending plugin chose, as core does for SMTP sends.
		$mailer->apply_message_from( $headers );
		$sent = $mailer->send( $to, $subject, $message, $headers, $attachments );

		// Attach captured request/response so the logger can persist it.
		$debug = $mailer->get_last_debug();
		if ( ! empty( $debug ) ) {
			$atts['_sendforce_debug'] = $debug;
		}

		/**
		 * Fires after SendForce attempts a provider API send.
		 *
		 * @param array  $atts     wp_mail attributes (with _sendforce_debug key when available).
		 * @param bool   $sent     Whether the provider returned success.
		 * @param string $provider Provider key.
		 * @param string $mode     Always "api" here.
		 */
		do_action( 'sendforce_after_send', $atts, $sent, $provider, 'api' );

		if ( $sent ) {
			// Trigger WordPress core hook for logging.
			do_action( 'wp_mail_succeeded', $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
			return true;
		}

		// Primary failed. If a fallback is configured, try it INLINE so wp_mail()
		// returns true on fallback success (the test-email UI and any plugin
		// listening for the wp_mail return value see the fallback as a success).
		// We also still fire wp_mail_failed below so the logger records the
		// primary failure for the audit trail.
		$primary_msg = sprintf(
			/* translators: %s: provider name */
			__( 'Failed to send email via %s API.', 'sendforce-mail-relay' ),
			$mailer->get_name()
		);
		if ( ! empty( $debug['resp_code'] ) || ! empty( $debug['resp_body'] ) ) {
			$primary_msg .= ' [HTTP ' . ( $debug['resp_code'] ?? 0 ) . '] '
				. mb_strimwidth( (string) ( $debug['resp_body'] ?? '' ), 0, 600, '…' );
		}

		$primary_error = new WP_Error( 'sendforce_api_error', $primary_msg, $atts );
		do_action( 'wp_mail_failed', $primary_error ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook. Logger records the failed primary attempt.

		// At this point attempt_fallback() (priority 5 listener) has already
		// fired and tried the fallback. If it succeeded the listener flipped
		// our instance flag — return TRUE to wp_mail so plugins waiting on
		// the return value see the send as successful.
		if ( $this->fallback_just_succeeded ) {
			return true;
		}

		// No fallback configured, or fallback also failed.
		return false;
	}

	/**
	 * When the primary connection's send fails, retry once via the configured
	 * fallback connection. Hooked to wp_mail_failed at priority 5.
	 *
	 * The original failure log entry is kept (the logger runs at priority 10),
	 * so users see a complete audit trail: one "failed" row for the primary
	 * attempt and, if recovery succeeds, one "sent" row tagged
	 * `_sendforce_fallback_used = true` for the fallback attempt.
	 *
	 * @param WP_Error $error Error fired by the primary mailer.
	 */
	public function attempt_fallback( $error ) {
		// Re-entrancy guard: never allow a fallback attempt to itself trigger
		// another fallback (would loop infinitely if both connections fail).
		static $in_progress = false;
		if ( $in_progress ) {
			return;
		}

		if ( ! is_wp_error( $error ) || ! $this->fallback_settings ) {
			return;
		}

		$atts = $error->get_error_data();
		if ( ! is_array( $atts ) || empty( $atts['to'] ) ) {
			return;
		}

		// Already a fallback attempt? Don't retry again.
		if ( ! empty( $atts['_sendforce_fallback_used'] ) ) {
			return;
		}

		$in_progress = true;

		$fallback        = $this->fallback_settings;
		$connection_type = $fallback['connection_type'] ?? 'smtp';
		$provider        = $fallback['provider'] ?? 'other';

		$sent  = false;
		$debug = array();

		if ( 'phpmail' === $provider ) {
			// PHP mail() fallback: just call the WP default by re-running
			// wp_mail with all SendForce hooks temporarily detached.
			$sent = $this->fallback_via_phpmail( $atts );
		} elseif ( 'api' === $connection_type && 'other' !== $provider ) {
			// API fallback: call the provider directly.
			$mailer = SendForce_API_Mailer::get_instance( $provider, $fallback );
			if ( $mailer ) {
				$mailer->apply_message_from( $atts['headers'] ?? '' );
				$sent = $mailer->send(
					$atts['to'] ?? '',
					$atts['subject'] ?? '',
					$atts['message'] ?? '',
					$atts['headers'] ?? '',
					$atts['attachments'] ?? array()
				);
				$debug = $mailer->get_last_debug();
			}
		} else {
			// SMTP fallback: instantiate PHPMailer manually with fallback creds.
			list( $sent, $debug ) = $this->fallback_via_smtp( $atts, $fallback );
		}

		$in_progress = false;

		if ( ! $sent ) {
			// Fallback also failed. Original failure is already being logged
			// by the priority-10 logger; nothing to do.
			return;
		}

		// Tag the success entry so the log UI can render a "Recovered via
		// fallback" badge, and forward debug data for the log row.
		$atts['_sendforce_fallback_used'] = true;
		if ( ! empty( $debug ) ) {
			$atts['_sendforce_debug'] = $debug;
		}
		do_action( 'wp_mail_succeeded', $atts ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.

		// Signal back to send_via_api() so wp_mail() returns TRUE — otherwise
		// the test-email UI and any plugin reading the wp_mail return value
		// would see the original primary failure as the final result.
		$this->fallback_just_succeeded = true;

		// Expose globally for the test-email handler / external listeners.
		self::$last_send_used_fallback = true;
		self::$last_fallback_label     = $this->resolve_fallback_label();
	}

	/**
	 * Look up the human-readable label of the configured fallback connection.
	 *
	 * @return string
	 */
	private function resolve_fallback_label() {
		$id = $this->settings['fallback_connection'] ?? '';
		if ( 'phpmail' === $id ) {
			return 'PHP Mail';
		}
		if ( empty( $id ) ) {
			return '';
		}
		$conns = get_option( 'sendforce_connections', array() );
		foreach ( $conns as $c ) {
			if ( isset( $c['id'] ) && $c['id'] === $id ) {
				$label = ! empty( $c['label'] ) ? $c['label'] : ( $c['provider'] ?? '' );
				// Sanitize on read — labels are user-supplied and end up in the
				// admin UI / log; we never trust DB content blindly.
				return sanitize_text_field( (string) $label );
			}
		}
		return '';
	}

	/**
	 * Send via PHP's built-in mail() as the fallback path.
	 *
	 * @param array $atts wp_mail attributes.
	 * @return bool
	 */
	private function fallback_via_phpmail( $atts ) {
		// Strip CR/LF from every input before handing it to mail() to prevent
		// header injection (a malicious wp_mail filter could pass headers with
		// embedded newlines, allowing extra recipients / spoofed envelope).
		$strip_crlf = static function ( $value ) {
			return is_string( $value ) ? str_replace( array( "\r", "\n", "\0" ), '', $value ) : $value;
		};

		$to_raw  = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
		$to      = $strip_crlf( $to_raw );
		$subject = $strip_crlf( (string) ( $atts['subject'] ?? '' ) );
		$body    = (string) ( $atts['message'] ?? '' ); // body intentionally NOT stripped — newlines are valid here.

		$raw_headers = $atts['headers'] ?? '';
		if ( ! is_array( $raw_headers ) ) {
			$raw_headers = explode( "\n", str_replace( "\r\n", "\n", (string) $raw_headers ) );
		}
		$safe_headers = array();
		foreach ( $raw_headers as $h ) {
			$h = $strip_crlf( (string) $h );
			if ( '' !== trim( $h ) ) {
				$safe_headers[] = $h;
			}
		}
		$headers = implode( "\r\n", $safe_headers );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_mail -- explicit PHP-mail fallback path requested by the user via the fallback connection setting.
		return @mail( $to, $subject, $body, $headers );
	}

	/**
	 * Send via PHPMailer using fallback SMTP credentials.
	 *
	 * @param array $atts wp_mail attributes.
	 * @param array $config Fallback connection config.
	 * @return array [ bool $sent, array $debug ]
	 */
	private function fallback_via_smtp( $atts, $config ) {
		if ( empty( $config['host'] ) ) {
			return array( false, array() );
		}

		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$debug_log = '';
		$mailer    = new \PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$mailer->isSMTP();
			$mailer->Host       = $config['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Port       = absint( $config['port'] ?? 587 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->SMTPSecure = $config['encryption'] ?? 'tls'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Timeout    = 15; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( 'none' === ( $config['encryption'] ?? '' ) ) {
				$mailer->SMTPSecure  = ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$mailer->SMTPAutoTLS = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}

			if ( ! empty( $config['auth'] ) ) {
				$mailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$mailer->Username = $config['username']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$mailer->Password = self::decrypt_password( $config['password'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}

			if ( ! empty( $this->settings['developer_debug'] ) ) {
				$mailer->SMTPDebug   = 2; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$mailer->Debugoutput = function ( $str ) use ( &$debug_log ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$debug_log .= trim( $str ) . "\n";
				};
			}

			$from_email = ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_option( 'admin_email' );
			$from_name  = ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_option( 'blogname' );
			$mailer->setFrom( $from_email, $from_name );

			$recipients = is_array( $atts['to'] ) ? $atts['to'] : array_map( 'trim', explode( ',', (string) $atts['to'] ) );
			foreach ( $recipients as $addr ) {
				if ( $addr ) {
					$mailer->addAddress( $addr );
				}
			}

			$headers = $atts['headers'] ?? '';
			if ( ! is_array( $headers ) ) {
				$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
			}
			// HTML or not is decided the way wp_mail() decides it — header, then
			// the wp_mail_content_type filter — so emails marked HTML only by
			// that filter (e.g. Directorist) aren't sent as plain text.
			if ( 'text/html' === self::resolve_content_type( $headers )['media'] ) {
				$mailer->isHTML( true );
			}
			foreach ( $headers as $h ) {
				if ( 0 === stripos( trim( $h ), 'cc:' ) ) {
					foreach ( array_map( 'trim', explode( ',', trim( substr( trim( $h ), 3 ) ) ) ) as $cc ) {
						if ( $cc ) {
							$mailer->addCC( $cc ); }
					}
				} elseif ( 0 === stripos( trim( $h ), 'bcc:' ) ) {
					foreach ( array_map( 'trim', explode( ',', trim( substr( trim( $h ), 4 ) ) ) ) as $bcc ) {
						if ( $bcc ) {
							$mailer->addBCC( $bcc ); }
					}
				} elseif ( 0 === stripos( trim( $h ), 'reply-to:' ) ) {
					$mailer->addReplyTo( trim( substr( trim( $h ), 9 ) ) );
				}
			}

			$mailer->Subject = (string) ( $atts['subject'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$mailer->Body    = (string) ( $atts['message'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$attachments = $atts['attachments'] ?? array();
			if ( ! is_array( $attachments ) ) {
				$attachments = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r\n", "\n", (string) $attachments ) ) ) );
			}
			foreach ( $attachments as $att ) {
				if ( $att && self::is_safe_attachment_path( $att ) ) {
					$mailer->addAttachment( $att );
				}
			}

			$ok = $mailer->send();
		} catch ( \Exception $e ) {
			return array( false, array(
				'provider'  => 'SMTP fallback',
				'url'       => ( $config['host'] ?? '' ) . ':' . ( $config['port'] ?? '' ),
				'resp_code' => 0,
				'resp_body' => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			) );
		}

		return array( $ok, array(
			'provider'  => 'SMTP fallback',
			'url'       => ( $config['host'] ?? '' ) . ':' . ( $config['port'] ?? '' ),
			'resp_code' => $ok ? 250 : 0,
			'resp_body' => mb_strimwidth( $debug_log, 0, 4000, '…' ),
			'timestamp' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Configure PHPMailer to use SMTP.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		// Skip when the (possibly routed) connection is in API mode or PHP-mail mode.
		$type     = $this->settings['connection_type'] ?? 'smtp';
		$provider = $this->settings['provider'] ?? 'other';
		if ( 'api' === $type && 'other' !== $provider ) {
			return;
		}
		if ( 'phpmail' === $provider ) {
			return;
		}
		// No host configured — nothing to do.
		if ( empty( $this->settings['host'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host       = $this->settings['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port       = absint( $this->settings['port'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = $this->settings['encryption']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout    = 15; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( 'none' === $this->settings['encryption'] ) {
			$phpmailer->SMTPSecure  = ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->SMTPAutoTLS = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		if ( ! empty( $this->settings['auth'] ) ) {
			$phpmailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Username = $this->settings['username']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Password = self::decrypt_password( $this->settings['password'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	/**
	 * Set the from email address.
	 *
	 * @param string $from_email The current from email.
	 * @return string
	 */
	public function set_from_email( $from_email ) {
		return sanitize_email( $this->settings['from_email'] );
	}

	/**
	 * Set the from name.
	 *
	 * @param string $from_name The current from name.
	 * @return string
	 */
	public function set_from_name( $from_name ) {
		return sanitize_text_field( $this->settings['from_name'] );
	}

	/**
	 * Intercept email in simulation mode — log but do not send.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts   wp_mail attributes.
	 * @return true
	 */
	public function intercept_simulation( $return, $atts ) {
		do_action( 'wp_mail_failed', new WP_Error( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
			'sendforce_simulation',
			__( '[Simulation Mode] Email not sent — simulation mode is active.', 'sendforce-mail-relay' ),
			$atts
		) );
		return true; // Return non-null to short-circuit wp_mail.
	}

	/**
	 * Work out an email's content type exactly the way wp_mail() does: the
	 * last Content-Type header (default text/plain), then the
	 * `wp_mail_content_type` filter.
	 *
	 * The filter step matters. Plugins such as Directorist mark their emails
	 * as HTML only through that filter, adding it just before wp_mail() and
	 * removing it straight after, with no Content-Type header at all. Any send
	 * path that reads only the headers (the API mailers, the inline fallback
	 * mailer) or that sends later without the filter in place (the Email
	 * Queue) would deliver such an email as plain text, so the recipient saw
	 * raw HTML source. Call this while the original wp_mail() call is still
	 * running, so the sender's filter is still attached.
	 *
	 * @param string|array $headers wp_mail() headers.
	 * @return array {
	 *   @type string $type      Filtered content type, as wp_mail() would set it (may carry parameters).
	 *   @type string $media     Lower-cased media type of $type, e.g. "text/html".
	 *   @type string $raw_media Lower-cased media type from the headers alone, before the filter.
	 *   @type string $charset   Charset from the Content-Type header, or '' when none was given.
	 * }
	 */
	public static function resolve_content_type( $headers ) {
		$type    = '';
		$charset = '';

		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}
		foreach ( $headers as $header ) {
			if ( false === strpos( (string) $header, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', (string) $header, 2 );
			if ( 'content-type' !== strtolower( trim( $name ) ) ) {
				continue;
			}
			// Last header wins, as in wp_mail().
			$parts   = explode( ';', $value );
			$type    = trim( $parts[0] );
			$charset = '';
			foreach ( array_slice( $parts, 1 ) as $param ) {
				if ( false !== stripos( $param, 'charset=' ) ) {
					$charset = trim( str_ireplace( array( 'charset=', '"' ), '', $param ) );
				}
			}
		}

		if ( '' === $type ) {
			$type = 'text/plain';
		}
		$raw_media = strtolower( $type );

		$filtered = (string) apply_filters( 'wp_mail_content_type', $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.

		return array(
			'type'      => $filtered,
			'media'     => strtolower( trim( explode( ';', $filtered )[0] ) ),
			'raw_media' => $raw_media,
			'charset'   => $charset,
		);
	}

	/**
	 * Add plain-text alternative body to HTML emails.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance.
	 */
	public function add_plain_text_alt( $phpmailer ) {
		// HTML emails only. Setting an AltBody on a plain-text email turns it
		// into multipart/alternative, and PHPMailer always labels the second
		// part text/html — so the plain body was delivered as an HTML part and
		// mail clients displayed it as HTML. That hit WordPress's own plain-text
		// mail (password resets, notifications, the Email Test) on every SMTP
		// and PHP Mail connection with this setting on. The API mailers already
		// guarded on HTML in build_raw_mime(); this brings PHPMailer into line.
		// Compare the media type only: wp_mail() copies whatever the
		// wp_mail_content_type filter returns into ContentType unparsed, so
		// "text/html; charset=UTF-8" is a real HTML email too.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$media_type = strtolower( trim( explode( ';', (string) $phpmailer->ContentType )[0] ) );
		if ( 'text/html' !== $media_type ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( empty( $phpmailer->AltBody ) && ! empty( $phpmailer->Body ) ) {
			$phpmailer->AltBody = wp_strip_all_tags( $phpmailer->Body ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	/**
	 * Add custom cron schedule for queue processing.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		// NOTE: We deliberately use a literal English string here instead of
		// __( …, 'sendforce-mail-relay' ). The cron_schedules filter can fire
		// VERY early in the request lifecycle (during wp_schedule_event() or
		// wp_next_scheduled() calls in plugins_loaded or even setup_theme),
		// before our textdomain is loaded at init. Calling __() here would
		// trigger WP 6.7+'s "Translation loading triggered too early" notice.
		// The `display` value is only ever shown in admin debug tools (WP
		// Crontrol, wp-cli cron list, etc.) — not user-facing — so leaving
		// it untranslated is harmless.
		// The queue runs every minute while the Email Queue is enabled (1.1.0+),
		// and every 5 minutes while it is disabled (see
		// SendForce_Queue::desired_schedule), so both schedules are registered.
		$schedules['sendforce_every_minute']    = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Every Minute (SendForce Mail Relay)',
		);
		$schedules['sendforce_every_5_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every 5 Minutes (SendForce Mail Relay)',
		);
		return $schedules;
	}

	/**
	 * Encrypt a password for storage.
	 *
	 * @param string $password Plain text password.
	 * @return string Encrypted password (base64 encoded).
	 */
	public static function encrypt_password( $password ) {
		if ( '' === (string) $password ) {
			return '';
		}

		$key    = self::get_encryption_key();
		$ivlen  = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv     = openssl_random_pseudo_bytes( $ivlen );
		$cipher = ( false !== $iv ) ? openssl_encrypt( $password, 'aes-256-cbc', $key, 0, $iv ) : false;

		if ( false === $iv || false === $cipher ) {
			return '';
		}

		// Store as "sf2:" + base64( raw 16-byte IV . ciphertext ). The IV has a
		// fixed length, so decryption slices it off by length rather than
		// splitting on a delimiter. The previous "iv :: cipher" scheme could
		// split inside a binary IV that happened to contain the bytes 0x3A3A
		// ("::"), corrupting roughly 1 in 4,400 stored secrets on read.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'sf2:' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored password. Understands the current "sf2:" format and the
	 * legacy delimiter format, so credentials saved by older versions keep
	 * working (they migrate to sf2 the next time they're re-saved).
	 *
	 * @param string $encrypted_password Encrypted password.
	 * @return string Decrypted password ('' when an encrypted value can't be
	 *                decrypted — fail closed rather than leak the ciphertext).
	 */
	public static function decrypt_password( $encrypted_password ) {
		// Reset here rather than relying on every caller to clear it first: a
		// stale `true` left by an earlier call would report a perfectly valid
		// credential as corrupted. The flag always describes THIS call only.
		self::$last_decrypt_failed = false;

		if ( '' === (string) $encrypted_password ) {
			return '';
		}

		$ivlen = openssl_cipher_iv_length( 'aes-256-cbc' );

		// Try the current key first, then the legacy salt-derived key. Values
		// written before 1.0.37 were encrypted with the salt-derived key, so
		// both must be attempted before we declare the value unreadable.
		$keys = array( self::get_encryption_key(), self::get_legacy_encryption_key() );

		// Current scheme: "sf2:" + base64( IV(16) . ciphertext ).
		if ( 0 === strpos( $encrypted_password, 'sf2:' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$data = base64_decode( substr( $encrypted_password, 4 ), true );
			if ( false === $data || strlen( $data ) <= $ivlen ) {
				self::$last_decrypt_failed = true;
				return ''; // Encrypted but unparseable — fail closed.
			}
			foreach ( $keys as $key ) {
				$decrypted = openssl_decrypt( substr( $data, $ivlen ), 'aes-256-cbc', $key, 0, substr( $data, 0, $ivlen ) );
				if ( false !== $decrypted ) {
					return $decrypted;
				}
			}
			self::$last_decrypt_failed = true;
			return '';
		}

		// Legacy scheme: base64( IV . "::" . ciphertext ).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = base64_decode( $encrypted_password );
		if ( false !== $data && false !== strpos( $data, '::' ) ) {
			list( $iv, $cipher ) = explode( '::', $data, 2 );
			foreach ( $keys as $key ) {
				$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', $key, 0, $iv );
				if ( false !== $decrypted ) {
					return $decrypted;
				}
			}
			self::$last_decrypt_failed = true;
			return '';
		}

		// No recognizable encryption markers — treat as legacy plaintext (values
		// stored before encryption was introduced). New encrypted values always
		// carry the "sf2:" prefix, so this branch can never leak a failed-decrypt
		// blob as a credential.
		return $encrypted_password;
	}

	/**
	 * Get the encryption key used for NEW credentials.
	 *
	 * Derived from a dedicated random secret stored in the `sendforce_enc_key`
	 * option rather than from wp_salt('auth'). Salts are rotatable — security
	 * plugins ("regenerate security keys"), host migrations, staging pushes and
	 * `wp config shuffle-salts` all change them. Because the salt-derived key
	 * was the ONLY key before 1.0.37, any such rotation permanently destroyed
	 * every stored password and API key, which surfaced to users as "the update
	 * wiped my SMTP settings". This secret is generated once and never rotates,
	 * so credentials now survive salt changes.
	 *
	 * @return string
	 */
	private static function get_encryption_key() {
		$secret = get_option( 'sendforce_enc_key' );

		if ( ! is_string( $secret ) || 64 !== strlen( $secret ) ) {
			try {
				$secret = bin2hex( random_bytes( 32 ) );
			} catch ( Exception $e ) {
				// No CSPRNG available — fall back to the legacy key rather than
				// encrypting with a predictable secret.
				return self::get_legacy_encryption_key();
			}
			// add_option() is a no-op if another request created the key first;
			// re-reading afterwards guarantees every request agrees on the value.
			add_option( 'sendforce_enc_key', $secret, '', 'yes' );
			$stored = get_option( 'sendforce_enc_key' );
			if ( is_string( $stored ) && 64 === strlen( $stored ) ) {
				$secret = $stored;
			}
		}

		return hash( 'sha256', $secret . 'sendforce' );
	}

	/**
	 * The pre-1.0.37 salt-derived key. Decrypt-only — never used for new
	 * values — so credentials written by older versions keep working and are
	 * transparently re-encrypted with the stable key the next time they are
	 * saved or the migration routine runs.
	 *
	 * @return string
	 */
	private static function get_legacy_encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . 'sendforce' );
	}

	/**
	 * Validate that an attachment path is safe to attach. Prevents path
	 * traversal and reading of arbitrary OS files (e.g. /etc/passwd) via
	 * a malicious wp_mail filter.
	 *
	 * Acceptable: paths inside ABSPATH or WP_CONTENT_DIR or WP_PLUGIN_DIR
	 * or the system temp directory (most plugins write attachments to one
	 * of these). Anything outside is rejected.
	 *
	 * @param string $path Attachment path.
	 * @return bool
	 */
	public static function is_safe_attachment_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		if ( ! file_exists( $path ) || ! is_file( $path ) ) {
			return false;
		}

		$real = realpath( $path );
		if ( false === $real ) {
			return false;
		}

		$allowed_roots = array(
			realpath( ABSPATH ),
			defined( 'WP_CONTENT_DIR' ) ? realpath( WP_CONTENT_DIR ) : null,
			defined( 'WP_PLUGIN_DIR' ) ? realpath( WP_PLUGIN_DIR ) : null,
			realpath( sys_get_temp_dir() ),
		);
		$upload = wp_upload_dir();
		if ( ! empty( $upload['basedir'] ) ) {
			$allowed_roots[] = realpath( $upload['basedir'] );
		}

		foreach ( $allowed_roots as $root ) {
			if ( $root && 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}
		return false;
	}
}
