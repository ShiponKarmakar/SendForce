<?php
/**
 * WP-CLI commands.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage SendForce Mail Relay from the command line.
 */
class SendForce_CLI {

	/**
	 * Send a test email.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Recipient email address.
	 *
	 * [--subject=<subject>]
	 * : Subject line. Defaults to "SendForce CLI Test".
	 *
	 * [--message=<message>]
	 * : Body. Defaults to a short timestamped message.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sendforce test you@example.com
	 *     wp sendforce test you@example.com --subject="Hello" --message="Body text"
	 */
	public function test( $args, $assoc_args ) {
		list( $email ) = $args;

		if ( ! is_email( $email ) ) {
			/* translators: %s: email address */
			\WP_CLI::error( sprintf( __( 'Invalid email address: %s', 'sendforce-mail-relay' ), $email ) );
		}

		$subject = $assoc_args['subject'] ?? __( 'SendForce CLI Test', 'sendforce-mail-relay' );
		/* translators: %s: date and time */
		$message = $assoc_args['message'] ?? sprintf( __( 'Sent at %s via WP-CLI.', 'sendforce-mail-relay' ) . "\n", current_time( 'mysql' ) );

		$mail_error = '';
		$capture    = function ( $err ) use ( &$mail_error ) {
			$mail_error = $err->get_error_message();
		};
		add_action( 'wp_mail_failed', $capture );

		$sent = wp_mail( $email, $subject, $message );

		remove_action( 'wp_mail_failed', $capture );

		if ( $sent ) {
			/* translators: %s: email address */
			\WP_CLI::success( sprintf( __( 'Test email dispatched to %s.', 'sendforce-mail-relay' ), $email ) );
		} else {
			\WP_CLI::error( $mail_error ?: __( 'wp_mail() returned false.', 'sendforce-mail-relay' ) );
		}
	}

	/**
	 * Manage the email queue.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : One of: process, stats, clear.
	 *
	 * [--status=<status>]
	 * : When clearing, restrict to status: queued, processing, sent, failed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sendforce queue process
	 *     wp sendforce queue stats
	 *     wp sendforce queue clear --status=failed
	 */
	public function queue( $args, $assoc_args ) {
		list( $action ) = $args;

		if ( 'process' === $action ) {
			$q = new SendForce_Queue();
			$q->process_queue();
			\WP_CLI::success( __( 'Queue processed.', 'sendforce-mail-relay' ) );
			return;
		}

		if ( 'stats' === $action ) {
			$stats = SendForce_Queue::get_stats();
			$rows  = array();
			foreach ( $stats as $key => $value ) {
				$rows[] = array(
					'status' => $key,
					'count'  => $value,
				);
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'status', 'count' ) );
			return;
		}

		if ( 'clear' === $action ) {
			global $wpdb;
			$status = isset( $assoc_args['status'] ) ? sanitize_key( $assoc_args['status'] ) : '';
			$table  = $wpdb->prefix . 'sendforce_queue';

			\WP_CLI::confirm(
				$status
					/* translators: %s: queue status */
					? sprintf( __( 'Delete all queue rows with status=%s?', 'sendforce-mail-relay' ), $status )
					: __( 'Delete ALL queue rows?', 'sendforce-mail-relay' ),
				$assoc_args
			);

			if ( $status ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table built from $wpdb->prefix + hardcoded literal; not user-controlled.
				$deleted = $wpdb->delete( $table, array( 'status' => $status ), array( '%s' ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table built from $wpdb->prefix + hardcoded literal; safe to interpolate.
				$deleted = $wpdb->query( "DELETE FROM {$table}" );
			}
			/* translators: %d: number of rows */
			\WP_CLI::success( sprintf( __( 'Deleted %d queue rows.', 'sendforce-mail-relay' ), absint( $deleted ) ) );
			return;
		}

		/* translators: %s: action name */
		\WP_CLI::error( sprintf( __( 'Unknown queue action: %s. Use process, stats, or clear.', 'sendforce-mail-relay' ), $action ) );
	}

	/**
	 * Manage the email log.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : One of: list, tail, delete, count.
	 *
	 * [--status=<status>]
	 * : Filter by status (sent|failed). For list/tail/count.
	 *
	 * [--limit=<limit>]
	 * : Number of rows for list/tail. Default 20.
	 *
	 * [--before=<date>]
	 * : When deleting, remove logs created before YYYY-MM-DD.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sendforce log list --limit=50
	 *     wp sendforce log tail --status=failed
	 *     wp sendforce log delete --before=2026-01-01
	 *     wp sendforce log count
	 */
	public function log( $args, $assoc_args ) {
		list( $action ) = $args;
		global $wpdb;
		$table = $wpdb->prefix . 'sendforce_logs';

		if ( in_array( $action, array( 'list', 'tail' ), true ) ) {
			$limit  = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
			$status = isset( $assoc_args['status'] ) ? sanitize_key( $assoc_args['status'] ) : '';
			$rows   = SendForce_Logger::get_logs(
				array(
					'per_page' => $limit,
					'page'     => 1,
					'status'   => $status,
					'orderby'  => 'created_at',
					'order'    => 'DESC',
				)
			);
			$out = array();
			foreach ( $rows['items'] as $r ) {
				$out[] = array(
					'id'         => $r->id,
					'to'         => $r->to_email,
					'subject'    => mb_strimwidth( $r->subject, 0, 40, '…' ),
					'status'     => $r->status,
					'created_at' => $r->created_at,
				);
			}
			\WP_CLI\Utils\format_items( 'table', $out, array( 'id', 'to', 'subject', 'status', 'created_at' ) );
			return;
		}

		if ( 'count' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table built from $wpdb->prefix + hardcoded literal; no user input.
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			/* translators: %d: number of log rows */
			\WP_CLI::log( sprintf( __( 'Total logs: %d', 'sendforce-mail-relay' ), absint( $total ) ) );
			return;
		}

		if ( 'delete' === $action ) {
			$before = isset( $assoc_args['before'] ) ? sanitize_text_field( $assoc_args['before'] ) : '';
			if ( ! $before || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before ) ) {
				\WP_CLI::error( __( 'Provide --before=YYYY-MM-DD.', 'sendforce-mail-relay' ) );
			}
			/* translators: %s: date (YYYY-MM-DD) */
			\WP_CLI::confirm( sprintf( __( 'Delete all log rows created before %s?', 'sendforce-mail-relay' ), $before ), $assoc_args );
			$cutoff = $before . ' 00:00:00';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table built from $wpdb->prefix + hardcoded literal; $cutoff is parameterized via prepare().
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
			/* translators: 1: number of rows, 2: date (YYYY-MM-DD) */
			\WP_CLI::success( sprintf( __( 'Deleted %1$d log rows before %2$s.', 'sendforce-mail-relay' ), absint( $deleted ), $before ) );
			return;
		}

		/* translators: %s: action name */
		\WP_CLI::error( sprintf( __( 'Unknown log action: %s. Use list, tail, delete, or count.', 'sendforce-mail-relay' ), $action ) );
	}

	/**
	 * Print system / debug info as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sendforce sysinfo
	 *     wp sendforce sysinfo > sendforce-debug.json
	 */
	public function sysinfo( $args, $assoc_args ) {
		\WP_CLI::log( wp_json_encode( SendForce_Sysinfo::collect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Import settings from another SMTP plugin.
	 *
	 * The source plugin must be active, since its own code is used to read and
	 * decrypt its settings. Nothing already in SendForce is replaced, and
	 * importing twice does not create duplicates.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : One of: list, preview, run.
	 *
	 * [<source>]
	 * : Source plugin for preview/run: wp-mail-smtp or fluent-smtp.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt when running an import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sendforce import list
	 *     wp sendforce import preview wp-mail-smtp
	 *     wp sendforce import run fluent-smtp --yes
	 *     wp sendforce import run wp-mail-smtp --url=sub.example.com
	 */
	public function import( $args, $assoc_args ) {
		$action = $args[0];

		if ( 'list' === $action ) {
			$available = SendForce_Importer::available_sources();
			$rows      = array();
			foreach ( SendForce_Importer::SOURCES as $slug => $name ) {
				$rows[] = array(
					'source'     => $slug,
					'plugin'     => $name,
					'importable' => isset( $available[ $slug ] )
						? __( 'yes', 'sendforce-mail-relay' )
						: __( 'no (not active)', 'sendforce-mail-relay' ),
				);
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'source', 'plugin', 'importable' ) );
			return;
		}

		if ( ! in_array( $action, array( 'preview', 'run' ), true ) ) {
			/* translators: %s: action name */
			\WP_CLI::error( sprintf( __( 'Unknown import action: %s. Use list, preview, or run.', 'sendforce-mail-relay' ), $action ) );
		}

		$source = isset( $args[1] ) ? sanitize_key( $args[1] ) : '';
		if ( '' === $source ) {
			\WP_CLI::error( __( 'Specify a source: wp-mail-smtp or fluent-smtp. Run "wp sendforce import list" to see what is available.', 'sendforce-mail-relay' ) );
		}

		// preview() strips every secret, so nothing sensitive is printed.
		$plan = SendForce_Importer::preview( $source );
		if ( is_wp_error( $plan ) ) {
			\WP_CLI::error( $plan->get_error_message() );
		}

		$rows       = array();
		$importable = 0;
		foreach ( $plan['items'] as $item ) {
			if ( ! in_array( $item['status'], array( 'unsupported', 'already' ), true ) ) {
				++$importable;
			}
			$rows[] = array(
				'connection' => $item['label'],
				'type'       => strtoupper( $item['mode'] ),
				'role'       => $item['role'],
				'status'     => $item['status'],
				'notes'      => implode( ' ', $item['notes'] ),
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'connection', 'type', 'role', 'status', 'notes' ) );

		if ( ! empty( $plan['sender']['force'] ) && ! empty( $plan['sender']['email'] ) ) {
			/* translators: %s: sender email address */
			\WP_CLI::log( sprintf( __( 'Sender identity: %s', 'sendforce-mail-relay' ), $plan['sender']['email'] ) );
		}

		if ( 'preview' === $action ) {
			/* translators: %d: number of connections */
			\WP_CLI::log( sprintf( __( 'Would import %d connection(s). Nothing has been changed.', 'sendforce-mail-relay' ), $importable ) );
			return;
		}

		if ( ! $importable ) {
			\WP_CLI::success( __( 'Nothing left to import: everything here is already in SendForce or cannot be imported.', 'sendforce-mail-relay' ) );
			return;
		}

		\WP_CLI::confirm(
			/* translators: 1: number of connections, 2: plugin name */
			sprintf( __( 'Import %1$d connection(s) from %2$s into SendForce?', 'sendforce-mail-relay' ), $importable, $plan['source_label'] ),
			$assoc_args
		);

		$result = SendForce_Importer::import( $source );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		/* translators: %d: number of connections */
		\WP_CLI::success( sprintf( __( 'Imported %d connection(s).', 'sendforce-mail-relay' ), count( $result['imported'] ) ) );
		if ( $result['rules'] ) {
			/* translators: %d: number of routing rules */
			\WP_CLI::log( sprintf( __( 'Created %d Smart Routing rule(s).', 'sendforce-mail-relay' ), $result['rules'] ) );
		}
		if ( ! empty( $result['routing_off'] ) ) {
			\WP_CLI::warning( __( 'Smart Routing is switched off, so those rules will not apply until you turn it on.', 'sendforce-mail-relay' ) );
		}
		if ( ! empty( $result['needs_action'] ) ) {
			/* translators: %s: comma-separated connection names */
			\WP_CLI::warning( sprintf( __( 'Needs your attention: %s', 'sendforce-mail-relay' ), implode( ', ', $result['needs_action'] ) ) );
		}
		\WP_CLI::log( __( 'Send a test email, then deactivate the other plugin so only one of them handles email.', 'sendforce-mail-relay' ) );
	}
}

\WP_CLI::add_command( 'sendforce', 'SendForce_CLI' );
