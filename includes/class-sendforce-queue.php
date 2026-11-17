<?php
/**
 * Email queue and retry handler.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Queue
 *
 * Handles email queuing and retry logic.
 */
class SendForce_Queue {

	/**
	 * Whether we are currently processing the queue (prevents recursion).
	 *
	 * @var bool
	 */
	private static $processing = false;

	/**
	 * One-shot bypass flag. Set to true to make the next intercepted
	 * wp_mail() call skip the queue and send synchronously. Used by the
	 * "Send Test" handler (which needs immediate delivery for UX) and any
	 * other admin tool that wants to bypass queuing. The flag is consumed
	 * (auto-reset to false) on first use, so callers don't have to remember
	 * to clear it after their wp_mail() call.
	 *
	 * @var bool
	 */
	public static $bypass_once = false;

	/**
	 * Initialize queue hooks.
	 */
	public function init() {
		$settings = get_option( 'sendforce_settings', array() );

		if ( ! empty( $settings['queue_enabled'] ) ) {
			// Priority 3: after Simulation Mode (1), but BEFORE Smart Routing (5)
			// and the API sender (10). At 10 the API sender — registered first —
			// ran before the queue: API connections delivered immediately and were
			// never queued, and while processing the queue the API result was then
			// discarded, so core sent the same email again through PHPMailer.
			// Running before routing also means routing never swaps connection
			// settings for an email that is only being queued (the swap is undone
			// on wp_mail_succeeded/failed, which never fire for a queued email).
			add_filter( 'pre_wp_mail', array( $this, 'intercept_mail' ), 3, 2 );
		}

		// Process queue on cron.
		add_action( 'sendforce_process_queue', array( $this, 'process_queue' ) );

		// Move existing installs onto the 1-minute schedule. Hooked to `init`
		// (not run here) so the cron_schedules filter that defines
		// `sendforce_every_minute` is guaranteed to be registered first.
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	/**
	 * Run intervals an admin can pick for the Email Queue, in minutes.
	 * The single source for the setting's sanitizer, the Settings dropdown
	 * and the WP-Cron schedules below.
	 *
	 * @return int[]
	 */
	public static function allowed_intervals() {
		return array( 1, 5, 10, 15, 30 );
	}

	/**
	 * The admin's chosen queue interval in minutes (default 1). Anything not
	 * in allowed_intervals() falls back to 1.
	 *
	 * @param array|null $settings sendforce_settings, or null to read the option.
	 * @return int
	 */
	public static function interval_minutes( $settings = null ) {
		if ( null === $settings ) {
			$settings = get_option( 'sendforce_settings', array() );
		}
		$minutes = (int) ( $settings['queue_interval'] ?? 1 ); // (int), not absint(): "-5" must not become 5.
		return in_array( $minutes, self::allowed_intervals(), true ) ? $minutes : 1;
	}

	/**
	 * WP-Cron schedule name for an interval, e.g. 10 → sendforce_every_10_minutes.
	 * 1 keeps its original name (sendforce_every_minute) so existing events
	 * aren't needlessly rescheduled on update.
	 *
	 * @param int $minutes Interval in minutes.
	 * @return string
	 */
	public static function schedule_name( $minutes ) {
		return 1 === (int) $minutes ? 'sendforce_every_minute' : 'sendforce_every_' . (int) $minutes . '_minutes';
	}

	/**
	 * Add every queue schedule to WP-Cron's list. Used by the cron_schedules
	 * filter in both the mailer and the activator.
	 *
	 * The display labels are deliberately literal English: cron_schedules can
	 * fire before the textdomain loads, and calling __() then triggers WP 6.7+'s
	 * early-translation notice. They only appear in debug tools (WP Crontrol,
	 * wp cron event list), never in SendForce's own screens.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_schedules( $schedules ) {
		foreach ( self::allowed_intervals() as $minutes ) {
			$schedules[ self::schedule_name( $minutes ) ] = array(
				'interval' => $minutes * MINUTE_IN_SECONDS,
				'display'  => 1 === $minutes
					? 'Every Minute (SendForce Mail Relay)'
					: 'Every ' . $minutes . ' Minutes (SendForce Mail Relay)',
			);
		}
		return $schedules;
	}

	/**
	 * Recurrence the queue processor should use right now.
	 *
	 * While the Email Queue is enabled: the interval the admin chose in
	 * Settings (every minute by default). While it is disabled: every 5
	 * minutes — still enough to drain rows left over from when it was on,
	 * without making every site spawn WP-Cron's background request once a
	 * minute just for a feature most sites don't use.
	 *
	 * @return string Schedule name.
	 */
	public static function desired_schedule() {
		$settings = get_option( 'sendforce_settings', array() );
		return ! empty( $settings['queue_enabled'] )
			? self::schedule_name( self::interval_minutes( $settings ) )
			: 'sendforce_every_5_minutes';
	}

	/**
	 * Keep the queue event on the recurrence desired_schedule() asks for.
	 *
	 * Activation only fires on activate, not on update, so existing installs
	 * carry the old 5-minute event; toggling the queue also changes the target.
	 * This reschedules when they differ (and re-creates a missing event).
	 * Otherwise it's a single cheap wp_get_schedule() lookup per request.
	 */
	public static function ensure_schedule() {
		$desired = self::desired_schedule();
		if ( $desired === wp_get_schedule( 'sendforce_process_queue' ) ) {
			return;
		}
		// Never clear the existing event unless the replacement schedule is
		// actually registered — otherwise the queue would be left with no cron
		// event at all and silently stop sending.
		$schedules = wp_get_schedules();
		if ( empty( $schedules[ $desired ] ) ) {
			return;
		}
		wp_clear_scheduled_hook( 'sendforce_process_queue' );
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $desired, 'sendforce_process_queue' );
	}

	/**
	 * Intercept wp_mail calls and queue them instead of sending immediately.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts   The wp_mail arguments.
	 * @return bool|null Null to proceed normally, or true to short-circuit.
	 */
	public function intercept_mail( $return, $atts ) {
		// Sending FROM the queue: never re-queue, but don't discard a result an
		// earlier filter produced (e.g. Simulation Mode) — returning null here
		// would make core send the email for real.
		if ( self::$processing ) {
			return $return;
		}

		// Consume the one-shot bypass flag up front so it can never leak to a
		// later send in the same request. (Used by the "Send Test" handler and
		// similar admin tools that need synchronous delivery.)
		$bypass            = self::$bypass_once;
		self::$bypass_once = false;

		// If another pre_wp_mail filter already handled this send — the API
		// mailer delivered it (returns true), or Simulation Mode short-circuited
		// it — honor that result. This MUST be checked before the bypass branch:
		// discarding a non-null $return would make WordPress core fall through to
		// PHPMailer and send a duplicate, and during a test send it would also
		// slip past Simulation Mode.
		if ( null !== $return ) {
			return $return;
		}

		// Bypass: don't queue — let core deliver this send synchronously.
		if ( $bypass ) {
			return null;
		}

		// If the row can't be queued (DB error, missing table, oversized row),
		// do NOT claim success — returning true here would make wp_mail() report
		// delivery to the caller (WooCommerce, forms, password resets) while the
		// email vanished. Returning null lets WordPress core fall through and
		// deliver it synchronously this request, so the mail is never silently
		// lost; the only cost is that one message skips the queue.
		if ( false === $this->add_to_queue( $atts ) ) {
			return null;
		}

		return true; // Short-circuit wp_mail (queued).
	}

	/**
	 * Add an email to the queue.
	 *
	 * @param array $atts The wp_mail arguments.
	 * @return int|false Insert ID or false on failure.
	 */
	public function add_to_queue( $atts ) {
		global $wpdb;

		$settings     = get_option( 'sendforce_settings', array() );
		$max_attempts = isset( $settings['max_attempts'] ) ? absint( $settings['max_attempts'] ) : 3;

		$to = isset( $atts['to'] ) ? $atts['to'] : '';
		if ( is_array( $to ) ) {
			$to = implode( ', ', $to );
		}

		$headers = isset( $atts['headers'] ) ? $atts['headers'] : '';

		// Freeze the content type now. Plugins like Directorist mark HTML only
		// through the wp_mail_content_type filter, attached just for their
		// wp_mail() call. The queue sends later, when that filter is gone, so
		// without this the email went out as plain text and showed raw HTML.
		// Appending is enough: wp_mail() and SendForce both use the last
		// Content-Type header.
		$content_type = SendForce_Mailer::resolve_content_type( $headers );
		if ( $content_type['media'] !== $content_type['raw_media'] ) {
			$frozen = $content_type['type'];
			if ( false === stripos( $frozen, 'charset=' ) ) {
				$frozen .= '; charset=' . ( '' !== $content_type['charset'] ? $content_type['charset'] : get_bloginfo( 'charset' ) );
			}
			if ( ! is_array( $headers ) ) {
				$headers = '' === trim( (string) $headers ) ? array() : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
			}
			// The value comes from filter code; never let it add a header line.
			$headers[] = 'Content-Type: ' . str_replace( array( "\r", "\n" ), '', $frozen );
		}

		if ( is_array( $headers ) ) {
			$headers = implode( "\n", $headers );
		}

		$attachments = isset( $atts['attachments'] ) ? $atts['attachments'] : array();
		if ( is_array( $attachments ) ) {
			$attachments = wp_json_encode( $attachments );
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sendforce_queue',
			array(
				'to_email'      => $to,
				'subject'       => isset( $atts['subject'] ) ? $atts['subject'] : '',
				'message'       => isset( $atts['message'] ) ? $atts['message'] : '',
				'headers'       => $headers,
				'attachments'   => $attachments,
				'status'        => 'queued',
				'attempts'      => 0,
				'max_attempts'  => $max_attempts,
				'error'         => '',
				'created_at'    => current_time( 'mysql' ),
				'next_retry_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Process the email queue.
	 */
	public function process_queue() {
		global $wpdb;

		if ( self::$processing ) {
			return;
		}

		self::$processing = true;

		$settings   = get_option( 'sendforce_settings', array() );
		$batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 50;
		$now        = current_time( 'mysql' );

		// Recover orphaned rows. When we mark a row 'processing' below, we also
		// set next_retry_at to NOW+10min as a self-expiring lease. If the
		// previous batch crashed before flipping the row to 'sent'/'failed',
		// the lease eventually expires and we flip the row back to 'queued'
		// so it gets retried on a future cron run.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is from $wpdb->prefix + literal; $now is parameterized.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}sendforce_queue SET status = 'queued' WHERE status = 'processing' AND next_retry_at < %s",
				$now
			)
		);

		// Fetch a wider window of candidates than we will send. Rows whose
		// connection has used up its per-minute allowance are skipped (left
		// queued for the next run), so without the wider window a backlog for
		// one rate-limited connection would starve every other connection's
		// mail queued behind it. batch_size still caps how many are SENT.
		$candidate_limit = min( 1000, max( 1, $batch_size ) * 5 );

		$emails = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sendforce_queue
				WHERE status IN ('queued', 'failed')
				AND attempts < max_attempts
				AND next_retry_at <= %s
				ORDER BY created_at ASC
				LIMIT %d",
				$now,
				$candidate_limit
			)
		);

		if ( empty( $emails ) ) {
			self::$processing = false;
			return;
		}

		// Compute the lease expiry once per batch — every row claimed below gets
		// the same lease time. If the batch crashes, orphan recovery (above) will
		// reclaim these rows on the next cron run after the lease expires.
		//
		// This MUST be in the same timezone frame as $now (current_time('mysql'),
		// i.e. site-local), because orphan recovery compares next_retry_at < $now.
		// Using a UTC timestamp here made the lease land ~offset hours away from
		// $now: on a positive-offset site (e.g. UTC+6) the lease was already in
		// the past, so a concurrent run reclaimed an in-flight row and re-sent the
		// email; on a negative offset it sat hours in the future, stranding
		// genuinely crashed rows. current_time('timestamp') is the local-adjusted
		// epoch, so gmdate() formats it as the local wall clock — matching $now.
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- deliberately pairing local-epoch with gmdate() to match current_time('mysql').
		$lease_expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( 10 * MINUTE_IN_SECONDS ) );

		/**
		 * Filter how long one queue run may keep sending, in seconds.
		 *
		 * Hosts often cap PHP worker time (commonly 30s). Stopping well inside
		 * that keeps a run from being killed mid-send; anything left over goes
		 * out on the next run a minute later.
		 *
		 * @param int $seconds Default 20.
		 */
		$deadline = microtime( true ) + max( 1, (int) apply_filters( 'sendforce_queue_time_budget', 20 ) );
		$sent_in_run = 0;
		$rate_limits = self::get_rate_limits();
		$default_id  = (string) ( $settings['default_connection'] ?? '' );

		foreach ( $emails as $email ) {
			if ( $sent_in_run >= $batch_size || microtime( true ) >= $deadline ) {
				break; // Remaining rows stay queued for the next run.
			}

			// Decode attachments.
			$attachments = json_decode( $email->attachments, true );
			if ( ! is_array( $attachments ) ) {
				$attachments = array();
			}

			// Parse headers back into array.
			$headers = ! empty( $email->headers ) ? explode( "\n", $email->headers ) : array();

			// Per-connection rate limit. Work out which connection this email will
			// go through and skip it — without claiming it — if that connection
			// has already used its allowance for the current minute. It stays
			// queued and is picked up again on a later run.
			$conn_id = self::predict_connection_id(
				array(
					'to'          => $email->to_email,
					'subject'     => $email->subject,
					'message'     => $email->message,
					'headers'     => $headers,
					'attachments' => $attachments,
				),
				$default_id
			);
			if ( self::is_rate_limited( $conn_id, $rate_limits ) ) {
				continue;
			}

			// Atomically claim the row. The UPDATE only matches if the row is
			// still in the exact state we selected (same status family and
			// attempt count), so when two runs overlap — a cron tick and the
			// "Process Queue Now" button, or two cron workers — only one of them
			// gets affected-rows = 1 and sends; the other skips. The old
			// SELECT-then-UPDATE let both runs send the same email.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is from $wpdb->prefix + literal; values are parameterized.
			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}sendforce_queue
					SET status = 'processing', next_retry_at = %s
					WHERE id = %d AND status IN ('queued', 'failed') AND attempts = %d",
					$lease_expires,
					$email->id,
					absint( $email->attempts )
				)
			);
			if ( 1 !== (int) $claimed ) {
				continue; // Another run already took this row.
			}
			++$sent_in_run;

			// Capture the real failure reason from wp_mail_failed so we can
			// store the provider's actual error (e.g. "AADSTS… token expired",
			// "MailFromDomainNotVerified", "Domain not found") in the queue
			// row instead of a generic "Failed to send email" message. Without
			// this, users on the Queue tab see only the generic string and
			// can't tell whether retrying will help.
			$captured_error = '';
			$capture_cb     = function ( $wp_error ) use ( &$captured_error ) {
				if ( is_wp_error( $wp_error ) ) {
					$captured_error = $wp_error->get_error_message();
				}
			};
			add_action( 'wp_mail_failed', $capture_cb );

			// Reset the cross-request fallback signal before the send so a stale
			// value from an earlier iteration can't mislead us. attempt_fallback()
			// sets it true when a fallback connection recovers a failed primary.
			if ( class_exists( 'SendForce_Mailer' ) ) {
				SendForce_Mailer::$last_send_used_fallback = false;
			}

			// Send the email.
			$sent = wp_mail(
				$email->to_email,
				$email->subject,
				$email->message,
				$headers,
				$attachments
			);

			remove_action( 'wp_mail_failed', $capture_cb );

			// For an SMTP-primary connection, core's wp_mail() returns false even
			// when the fallback connection actually delivered the email (it reports
			// the primary's failure). Treat that recovery as success — otherwise
			// this row would be re-queued and the fallback would send the same
			// email again on the next cron run.
			$used_fallback = class_exists( 'SendForce_Mailer' ) && SendForce_Mailer::$last_send_used_fallback;
			if ( ! $sent && $used_fallback ) {
				$sent = true;
			}

			// Count this attempt against the connection's per-minute allowance.
			// Failed attempts count too — the provider still received a request
			// (and a rate-limit rejection is itself a reason to slow down). A
			// fallback recovery also spent one of the fallback's sends.
			self::count_send( $conn_id, $rate_limits );
			if ( $used_fallback && ! empty( $settings['fallback_connection'] ) ) {
				self::count_send( (string) $settings['fallback_connection'], $rate_limits );
			}

			$attempts = absint( $email->attempts ) + 1;

			if ( $sent ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sendforce_queue',
					array(
						'status'   => 'sent',
						'attempts' => $attempts,
						'error'    => '', // Clear any previous-attempt error.
					),
					array( 'id' => $email->id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
			} else {
				// Calculate next retry with exponential backoff.
				$delay         = pow( 3, $attempts ) * 5 * MINUTE_IN_SECONDS; // 5min, 15min, 45min.
				$next_retry_at = wp_date( 'Y-m-d H:i:s', time() + $delay );

				$new_status = $attempts >= absint( $email->max_attempts ) ? 'failed' : 'queued';

				// Use the captured error when available, otherwise the previous
				// generic message — so callers always see SOMETHING actionable.
				$error_msg = '' !== $captured_error
					? wp_strip_all_tags( $captured_error )
					: __( 'Failed to send email (no provider error captured).', 'sendforce-mail-relay' );

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sendforce_queue',
					array(
						'status'        => $new_status,
						'attempts'      => $attempts,
						'next_retry_at' => $next_retry_at,
						'error'         => mb_strimwidth( $error_msg, 0, 2000, '…' ),
					),
					array( 'id' => $email->id ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		self::$processing = false;
	}

	/**
	 * Map of connection id => max emails per minute (only connections with a
	 * limit set; 0 / missing means unlimited).
	 *
	 * @return array<string,int>
	 */
	private static function get_rate_limits() {
		$limits = array();
		foreach ( (array) get_option( 'sendforce_connections', array() ) as $conn ) {
			if ( ! is_array( $conn ) || empty( $conn['id'] ) ) {
				continue;
			}
			$limit = absint( $conn['rate_limit'] ?? 0 );
			if ( $limit > 0 ) {
				$limits[ (string) $conn['id'] ] = $limit;
			}
		}
		return $limits;
	}

	/**
	 * Work out which connection a queued email will be sent through: the
	 * Smart Routing target if a rule matches, otherwise the primary.
	 *
	 * The router caches its decision per set of wp_mail attributes for the
	 * rest of the request, so when wp_mail() then resolves the same email it
	 * gets the same answer — including for random round-robin rules. That only
	 * holds if these attributes match the ones WordPress builds, so mirror
	 * core's shape: WP 6.9+ adds an `embeds` argument to wp_mail().
	 *
	 * @param array  $atts       to, subject, message, headers, attachments.
	 * @param string $default_id Primary connection id.
	 * @return string Connection id ('' when nothing is configured).
	 */
	private static function predict_connection_id( $atts, $default_id ) {
		static $has_embeds = null;
		if ( null === $has_embeds ) {
			$has_embeds = function_exists( 'wp_mail' ) && ( new ReflectionFunction( 'wp_mail' ) )->getNumberOfParameters() >= 6;
		}
		if ( $has_embeds ) {
			$atts['embeds'] = array();
		}

		if ( class_exists( 'SendForce_Router' ) ) {
			$routed = SendForce_Router::resolve_connection_for( $atts );
			if ( is_array( $routed ) && ! empty( $routed['_connection_id'] ) ) {
				return (string) $routed['_connection_id'];
			}
		}
		return $default_id;
	}

	/**
	 * Transient key for a connection's send counter in the current minute.
	 * The window is a UTC calendar minute, so every concurrent run agrees on it.
	 *
	 * @param string $conn_id Connection id.
	 * @return string
	 */
	private static function rate_key( $conn_id ) {
		return 'sendforce_rl_' . md5( $conn_id ) . '_' . gmdate( 'YmdHi' );
	}

	/**
	 * Whether a connection has used up its allowance for the current minute.
	 *
	 * @param string $conn_id     Connection id.
	 * @param array  $rate_limits From get_rate_limits().
	 * @return bool
	 */
	private static function is_rate_limited( $conn_id, $rate_limits ) {
		if ( '' === $conn_id || empty( $rate_limits[ $conn_id ] ) ) {
			return false;
		}
		return (int) get_transient( self::rate_key( $conn_id ) ) >= $rate_limits[ $conn_id ];
	}

	/**
	 * Record one send against a rate-limited connection's current minute.
	 * Unlimited connections aren't tracked. The read-increment-write isn't
	 * atomic across parallel runs, so a limit can be overshot by a send or two
	 * when runs overlap; the atomic row claim still guarantees no email is
	 * ever sent twice.
	 *
	 * @param string $conn_id     Connection id.
	 * @param array  $rate_limits From get_rate_limits().
	 */
	private static function count_send( $conn_id, $rate_limits ) {
		if ( '' === $conn_id || empty( $rate_limits[ $conn_id ] ) ) {
			return;
		}
		$key = self::rate_key( $conn_id );
		set_transient( $key, (int) get_transient( $key ) + 1, 2 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get queue entries with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total'.
	 */
	public static function get_queue( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = 'WHERE 1=1';
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$values[] = $args['status'];
		}

		$allowed_orderby = array( 'created_at', 'status', 'to_email', 'attempts', 'next_retry_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built from safe %s placeholders; values passed to prepare().
			$total = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is built from safe %s placeholders above.
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_queue {$where}", $values )
			);
		} else {
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_queue" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where uses only %s placeholders; $orderby and $order are whitelisted above.
		$query        = "SELECT * FROM {$wpdb->prefix}sendforce_queue {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values = array_merge( $values, array( absint( $args['per_page'] ), $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items        = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => absint( $total ),
		);
	}

	/**
	 * Delete a queue entry.
	 *
	 * @param int $queue_id Queue ID.
	 * @return bool
	 */
	public static function delete_queue_item( $queue_id ) {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sendforce_queue',
			array( 'id' => absint( $queue_id ) ),
			array( '%d' )
		);
	}

	/**
	 * Retry a failed queue entry.
	 *
	 * @param int $queue_id Queue ID.
	 * @return bool
	 */
	public static function retry_queue_item( $queue_id ) {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sendforce_queue',
			array(
				'status'        => 'queued',
				'attempts'      => 0,
				'next_retry_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $queue_id ) ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT status, COUNT(*) as count FROM {$wpdb->prefix}sendforce_queue GROUP BY status"
		);

		$stats = array(
			'queued'     => 0,
			'processing' => 0,
			'sent'       => 0,
			'failed'     => 0,
			'total'      => 0,
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				$stats[ $row->status ] = absint( $row->count );
				$stats['total']       += absint( $row->count );
			}
		}

		return $stats;
	}
}
