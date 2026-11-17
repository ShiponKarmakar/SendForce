<?php
/**
 * Email logger.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Logger
 *
 * Logs all emails sent through wp_mail.
 */
class SendForce_Logger {

	/**
	 * Temporarily store mail data for logging.
	 *
	 * @var array
	 */
	private $current_mail = array();

	/**
	 * Initialize logger hooks.
	 */
	public function init() {
		// Capture mail data before sending.
		add_filter( 'wp_mail', array( $this, 'capture_mail_data' ), 999 );

		// Log on success.
		add_action( 'wp_mail_succeeded', array( $this, 'log_success' ) );

		// Log on failure.
		add_action( 'wp_mail_failed', array( $this, 'log_failure' ) );

		// Schedule log cleanup.
		add_action( 'sendforce_cleanup_logs', array( $this, 'cleanup_old_logs' ) );
	}

	/**
	 * Capture mail data for logging.
	 *
	 * @param array $args The wp_mail arguments.
	 * @return array Unmodified arguments.
	 */
	public function capture_mail_data( $args ) {
		$this->current_mail = $args;
		return $args;
	}

	/**
	 * Log a successful email.
	 *
	 * @param array $mail_data Mail data from wp_mail_succeeded.
	 */
	public function log_success( $mail_data ) {
		$this->insert_log( $mail_data, 'sent' );
	}

	/**
	 * Log a failed email.
	 *
	 * @param WP_Error $error The error object.
	 */
	public function log_failure( $error ) {
		$mail_data = $error->get_error_data();
		if ( ! is_array( $mail_data ) ) {
			$mail_data = array();
		}

		$this->insert_log(
			$mail_data,
			'failed',
			$error->get_error_message()
		);
	}

	/**
	 * Insert a log entry into the database.
	 *
	 * @param array  $mail_data Mail data.
	 * @param string $status    Email status (sent/failed).
	 * @param string $error     Error message if any.
	 */
	private function insert_log( $mail_data, $status, $error = '' ) {
		global $wpdb;

		$settings = get_option( 'sendforce_settings', array() );

		// Respect the log_enabled setting (default true).
		if ( isset( $settings['log_enabled'] ) && ! $settings['log_enabled'] ) {
			return;
		}

		$to = '';
		if ( isset( $mail_data['to'] ) ) {
			$to = is_array( $mail_data['to'] ) ? implode( ', ', $mail_data['to'] ) : $mail_data['to'];
		}

		$message = '';
		if ( ! empty( $settings['log_email_body'] ) && isset( $mail_data['message'] ) ) {
			$message = $mail_data['message'];
		}

		$headers = '';
		if ( isset( $mail_data['headers'] ) ) {
			$headers = is_array( $mail_data['headers'] ) ? implode( "\n", $mail_data['headers'] ) : $mail_data['headers'];
		}

		// Store ONLY the filename basenames of attachments, never the full
		// filesystem paths. Full paths in the log would leak the server's
		// directory layout (`/var/www/site/wp-content/uploads/...`), shared-
		// hosting usernames, and customer order IDs that appear in WooCommerce
		// invoice filenames. Basenames give the same diagnostic value
		// ("invoice.pdf was attached") with none of those leaks. Existing
		// rows in the database from earlier versions retain their old format
		// — only new entries from 1.0.27 onwards get basename-only.
		$attachments = '';
		if ( isset( $mail_data['attachments'] ) ) {
			$paths = is_array( $mail_data['attachments'] ) ? $mail_data['attachments'] : array( (string) $mail_data['attachments'] );
			$names = array();
			foreach ( $paths as $p ) {
				$p = (string) $p;
				if ( '' === $p ) {
					continue;
				}
				$names[] = basename( $p );
			}
			$attachments = implode( ', ', $names );
		}

		// Optional debug payload forwarded by SendForce_Mailer::send_via_api()
		// or attempt_fallback(). The fallback flag is embedded inside the same
		// JSON blob so we don't need a schema change.
		$debug_data = '';
		$debug      = array();
		if ( isset( $mail_data['_sendforce_debug'] ) && is_array( $mail_data['_sendforce_debug'] ) ) {
			$debug = $mail_data['_sendforce_debug'];
		}
		if ( ! empty( $mail_data['_sendforce_fallback_used'] ) ) {
			$debug['fallback_used'] = true;
		}
		// Surface the matched routing rule (when Smart Routing redirected the send).
		if ( class_exists( 'SendForce_Mailer' ) && '' !== SendForce_Mailer::$last_matched_rule_label ) {
			$debug['matched_rule'] = SendForce_Mailer::$last_matched_rule_label;
		}
		if ( ! empty( $debug ) ) {
			$debug_data = wp_json_encode( $debug );
		}

		// Provider key (e.g. "sendgrid", "ses", "outlook", "phpmail") — used
		// by the Email Log "Filter by connection" dropdown introduced in 1.0.30.
		// Pulled from the captured debug payload first (where the API mailer
		// records the provider name verbatim), then falls back to the global
		// active connection's provider, then to the routed connection if a
		// Smart Routing rule fired.
		$provider = '';
		if ( ! empty( $debug['provider'] ) ) {
			$provider = strtolower( (string) $debug['provider'] );
		} else {
			$active = get_option( 'sendforce_connections', array() );
			$active_id = $settings['default_connection'] ?? '';
			foreach ( (array) $active as $c ) {
				if ( isset( $c['id'] ) && $c['id'] === $active_id ) {
					$provider = (string) ( $c['provider'] ?? '' );
					break;
				}
			}
		}

		$row = array(
			'to_email'    => $to,
			'subject'     => isset( $mail_data['subject'] ) ? $mail_data['subject'] : '',
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
			'status'      => $status,
			'error'       => $error,
			'debug_data'  => $debug_data,
			'provider'    => $provider,
			'created_at'  => current_time( 'mysql' ),
		);

		/**
		 * Filter the row about to be inserted into the email log.
		 * Return null to skip logging this entry entirely.
		 *
		 * @param array  $row       Columns => values about to be inserted.
		 * @param array  $mail_data Original wp_mail attributes.
		 * @param string $status    "sent" or "failed".
		 */
		$row = apply_filters( 'sendforce_log_entry', $row, $mail_data, $status );

		if ( null === $row || ! is_array( $row ) ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sendforce_logs',
			$row,
			// Format spec must list each $row key in order: to_email, subject,
			// message, headers, attachments, status, error, debug_data,
			// provider, created_at — all strings.
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// insert_log runs on the wp_mail hot path (every send, often on the
		// front-end). Only flush the cheap headline-counts cache here — it's
		// two indexed COUNT(*)s, fast to rebuild. We deliberately do NOT flush
		// the daily-chart caches on every send: those cost one COUNT per day to
		// rebuild (up to 180 queries) and an hour of chart staleness is
		// invisible, so flushing them per-send would add real write overhead to
		// every email for no user-visible benefit. The chart caches age out on
		// their own 1-hour TTL and are flushed in full only on the infrequent
		// admin delete / cron cleanup paths (see flush_stats_cache()).
		delete_transient( 'sendforce_dashboard_counts' );
	}

	/**
	 * Invalidate ALL cached dashboard/chart statistics (headline counts and
	 * every daily-chart range).
	 *
	 * Called on the infrequent, admin- or cron-initiated paths that can change
	 * row counts in bulk — log cleanup, single delete, and bulk delete — so the
	 * dashboard reflects the change on the next view. The hot insert path does
	 * NOT call this; it flushes only the cheap headline-counts transient inline
	 * (see insert_log) to keep per-send overhead minimal.
	 *
	 * We delete the transients directly here (rather than calling
	 * SendForce_Admin::flush_stats_cache()) because the logger runs on the
	 * front-end during ordinary wp_mail traffic, and we don't want to autoload
	 * the large admin class just to clear four keys. The key list MUST stay in
	 * sync with SendForce_Admin::flush_stats_cache().
	 *
	 * @return void
	 */
	private static function flush_stats_cache() {
		delete_transient( 'sendforce_dashboard_counts' );
		delete_transient( 'sendforce_daily_stats_7' );
		delete_transient( 'sendforce_daily_stats_30' );
		delete_transient( 'sendforce_daily_stats_90' );
	}

	/**
	 * Clean up old log entries.
	 *
	 * @return int Number of rows deleted (0 if retention is "forever").
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$settings       = get_option( 'sendforce_settings', array() );
		$retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 30;

		if ( 0 === $retention_days ) {
			return 0; // 0 means keep forever.
		}

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}sendforce_logs WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
			)
		);

		if ( $deleted ) {
			self::flush_stats_cache();
		}

		return (int) $deleted;
	}

	/**
	 * Get log entries with pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total'.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'  => 20,
			'page'      => 1,
			'status'    => '',
			'search'    => '',
			'date_from' => '',
			'date_to'   => '',
			'provider'  => '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = 'WHERE 1=1';
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$values[] = $args['status'];
		}

		// Provider filter (1.0.30+) — matches the `provider` column populated
		// by insert_log(). Rows logged before 1.0.30 have NULL provider and
		// only match when this filter is empty ("All providers").
		if ( ! empty( $args['provider'] ) ) {
			$where   .= ' AND provider = %s';
			$values[] = sanitize_key( $args['provider'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND (to_email LIKE %s OR subject LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where   .= ' AND created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where   .= ' AND created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$allowed_orderby = array( 'created_at', 'status', 'to_email', 'subject' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

		// Get total count.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built from safe %s placeholders; values passed to prepare().
			$total = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is built from safe %s placeholders above.
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs {$where}", $values )
			);
		} else {
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sendforce_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Get items.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where uses only %s placeholders; $orderby and $order are whitelisted above.
		$query          = "SELECT * FROM {$wpdb->prefix}sendforce_logs {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values   = array_merge( $values, array( absint( $args['per_page'] ), $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => absint( $total ),
		);
	}

	/**
	 * Get a single log entry.
	 *
	 * @param int $log_id Log ID.
	 * @return object|null
	 */
	public static function get_log( $log_id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sendforce_logs WHERE id = %d",
				$log_id
			)
		);
	}

	/**
	 * Delete a log entry.
	 *
	 * @param int $log_id Log ID.
	 * @return bool
	 */
	public static function delete_log( $log_id ) {
		global $wpdb;
		$deleted = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sendforce_logs',
			array( 'id' => absint( $log_id ) ),
			array( '%d' )
		);
		if ( $deleted ) {
			self::flush_stats_cache();
		}
		return $deleted;
	}

	/**
	 * Return the distinct non-empty provider keys present in the log table.
	 * Used by the Email Log "Filter by connection" dropdown introduced in
	 * 1.0.30 to populate its options from real log data. Cheap query — the
	 * `provider` column is indexed.
	 *
	 * @return string[] Provider keys, lowercase, sorted alphabetically.
	 */
	public static function get_distinct_providers() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix + literal; no user input.
		$rows = $wpdb->get_col( "SELECT DISTINCT provider FROM {$wpdb->prefix}sendforce_logs WHERE provider IS NOT NULL AND provider <> '' ORDER BY provider ASC" );
		return is_array( $rows ) ? array_map( 'strval', $rows ) : array();
	}

	/**
	 * Bulk delete log entries.
	 *
	 * @param array $log_ids Array of log IDs.
	 * @return int Number of deleted rows.
	 */
	public static function bulk_delete( $log_ids ) {
		global $wpdb;

		$ids = array_map( 'absint', $log_ids );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table bulk delete.
		$deleted = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a safe string of %d tokens generated by array_fill.
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}sendforce_logs WHERE id IN ({$placeholders})", $ids )
		);

		if ( $deleted ) {
			self::flush_stats_cache();
		}

		return (int) $deleted;
	}
}
