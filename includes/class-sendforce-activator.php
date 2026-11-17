<?php
/**
 * Plugin activator.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Activator
 *
 * Handles plugin activation tasks.
 */
class SendForce_Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		self::migrate_from_old_version();
		self::setup_site();
		self::reset_conflict_dismissed();
	}

	/**
	 * Everything one site needs to run the plugin: default options, cron
	 * events and database tables. Safe to run repeatedly (every step checks
	 * before acting).
	 *
	 * Runs on activation for the site being activated, and lazily from
	 * sendforce_maybe_setup_site() on the first request to any site whose
	 * stored sendforce_db_version doesn't match the plugin. That lazy path is
	 * what sets up the other sites of a Multisite network: Network Activate
	 * only fires the activation hook on the main site, and sites created later
	 * never fire it at all.
	 *
	 * Tables are created last because create_tables() records
	 * sendforce_db_version, the "this site is set up" marker — if anything
	 * before it fails, the next request simply tries again.
	 */
	public static function setup_site() {
		self::set_default_options();
		self::schedule_events();
		self::create_tables();
	}

	/**
	 * Migrate data from old smtpflow_* naming to sendforce_* naming.
	 * Safe to run multiple times (checks before acting).
	 */
	public static function migrate_from_old_version() {
		global $wpdb;

		// --- Option migration ---
		$option_map = array(
			'smtpflow_settings'    => 'sendforce_settings',
			'smtpflow_connections' => 'sendforce_connections',
			'smtpflow_alerts'      => 'sendforce_alerts',
		);
		foreach ( $option_map as $old_key => $new_key ) {
			$old_value = get_option( $old_key );
			if ( false !== $old_value && false === get_option( $new_key ) ) {
				add_option( $new_key, $old_value );
			}
			if ( false !== $old_value ) {
				delete_option( $old_key );
			}
		}
		// Also clean up old db version option.
		delete_option( 'smtpflow_db_version' );

		// --- Table migration ---
		$table_map = array(
			$wpdb->prefix . 'smtpflow_logs'  => $wpdb->prefix . 'sendforce_logs',
			$wpdb->prefix . 'smtpflow_queue' => $wpdb->prefix . 'sendforce_queue',
		);
		foreach ( $table_map as $old_table => $new_table ) {
			// Table names are hardcoded in $table_map above combined with $wpdb->prefix, so interpolation is safe.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
			$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );
			if ( $old_exists && ! $new_exists ) {
				$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
			} elseif ( $old_exists && $new_exists ) {
				$wpdb->query( "DROP TABLE `{$old_table}`" );
			}
			// phpcs:enable
		}
	}

	/**
	 * Public wrapper so the plugin bootstrap can re-run dbDelta on upgrade.
	 */
	public static function create_tables_public() {
		self::create_tables();
		update_option( 'sendforce_db_version', SENDFORCE_VERSION );
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}sendforce_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			to_email text NOT NULL,
			subject text NOT NULL,
			message longtext NOT NULL,
			headers text NOT NULL,
			attachments text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			error text NOT NULL,
			debug_data longtext NULL,
			provider varchar(50) NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY provider (provider)
		) $charset_collate;

		CREATE TABLE {$wpdb->prefix}sendforce_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			to_email text NOT NULL,
			subject text NOT NULL,
			message longtext NOT NULL,
			headers text NOT NULL,
			attachments text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts int(11) NOT NULL DEFAULT 0,
			max_attempts int(11) NOT NULL DEFAULT 3,
			error text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			next_retry_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY next_retry_at (next_retry_at)
		) $charset_collate;

		CREATE TABLE {$wpdb->prefix}sendforce_routes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(190) NOT NULL DEFAULT '',
			priority int(11) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			conditions longtext NOT NULL,
			action_type varchar(20) NOT NULL DEFAULT 'connection',
			action_value text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY priority (priority),
			KEY enabled (enabled)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'sendforce_db_version', SENDFORCE_VERSION );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'default_connection'  => '',
			'fallback_connection' => '',
			'from_email'          => get_option( 'admin_email' ),
			'from_name'           => get_option( 'blogname' ),
			'log_enabled'         => true,
			'queue_enabled'       => false,
			'batch_size'          => 50,
			'log_retention_days'  => 30,
			'log_email_body'      => true,
		);

		if ( false === get_option( 'sendforce_settings' ) ) {
			add_option( 'sendforce_settings', $defaults );
		}
	}

	/**
	 * Schedule WP-Cron events.
	 */
	private static function schedule_events() {
		// Register the custom cron schedule before scheduling events,
		// since plugins_loaded hasn't fired yet during activation.
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );

		if ( ! wp_next_scheduled( 'sendforce_process_queue' ) ) {
			// Every minute only while the queue is on; see SendForce_Queue::desired_schedule().
			wp_schedule_event( time(), SendForce_Queue::desired_schedule(), 'sendforce_process_queue' );
		}

		if ( ! wp_next_scheduled( 'sendforce_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'sendforce_cleanup_logs' );
		}

		if ( ! wp_next_scheduled( 'sendforce_send_daily_summary' ) ) {
			wp_schedule_event( time(), 'daily', 'sendforce_send_daily_summary' );
		}
	}

	/**
	 * Register custom cron schedule for activation context.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_cron_schedule( $schedules ) {
		// Literal string — see add_cron_schedule() in class-sendforce-mailer.php
		// for the rationale (cron_schedules filter fires before init, calling
		// __() here triggers WP 6.7+'s early-translation notice).
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
	 * Clear conflict-dismissed user meta for all users so the notice
	 * re-appears fresh after plugin activation.
	 */
	private static function reset_conflict_dismissed() {
		delete_metadata( 'user', 0, 'sendforce_conflict_dismissed', '', true );
	}
}
