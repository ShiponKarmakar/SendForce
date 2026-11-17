<?php
/**
 * System / debug info collector.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Sysinfo
 *
 * Builds a sanitized snapshot of plugin + environment data for debugging.
 * Secrets (passwords, API keys) are NEVER included — only presence flags.
 */
class SendForce_Sysinfo {

	/**
	 * Collect sysinfo as an associative array.
	 *
	 * @return array
	 */
	public static function collect() {
		global $wp_version, $wpdb;

		$settings    = get_option( 'sendforce_settings', array() );
		$connections = get_option( 'sendforce_connections', array() );
		$alerts      = get_option( 'sendforce_alerts', array() );

		$conn_summary = array();
		foreach ( (array) $connections as $c ) {
			$conn_summary[] = array(
				'id'              => $c['id'] ?? '',
				'label'           => $c['label'] ?? '',
				'provider'        => $c['provider'] ?? 'other',
				'connection_type' => $c['connection_type'] ?? 'smtp',
				'host'            => $c['host'] ?? '',
				'port'            => $c['port'] ?? '',
				'encryption'      => $c['encryption'] ?? '',
				'auth'            => ! empty( $c['auth'] ),
				'has_password'    => ! empty( $c['password'] ),
				'has_api_key'     => ! empty( $c['api_key'] ),
			);
		}

		$alert_channels = array();
		foreach ( (array) ( $alerts['channels'] ?? array() ) as $key => $ch ) {
			$alert_channels[ $key ] = array(
				'enabled'        => ! empty( $ch['enabled'] ),
				'has_credential' => ! empty( $ch['webhook'] ) || ! empty( $ch['token'] ),
			);
		}

		return array(
			'plugin'  => array(
				'name'       => 'SendForce Mail Relay',
				'version'    => defined( 'SENDFORCE_VERSION' ) ? SENDFORCE_VERSION : '',
				'db_version' => get_option( 'sendforce_db_version', '' ),
			),
			'env'     => array(
				'wordpress'    => $wp_version,
				'php'          => PHP_VERSION,
				'mysql'        => $wpdb->db_version(),
				'wp_debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
				'is_multisite' => is_multisite(),
				'site_url'     => get_site_url(),
				'home_url'     => get_home_url(),
				'language'     => get_locale(),
				'timezone'     => wp_timezone_string(),
				'memory_limit' => WP_MEMORY_LIMIT,
				'curl'         => function_exists( 'curl_version' ) && curl_version() ? curl_version()['version'] : 'not available',
				'openssl'      => defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : 'not available',
				'mbstring'     => extension_loaded( 'mbstring' ),
			),
			'theme'   => array(
				'name'    => wp_get_theme()->get( 'Name' ),
				'version' => wp_get_theme()->get( 'Version' ),
			),
			'plugins' => self::active_plugins(),
			'mailer'  => array(
				'default_connection'  => $settings['default_connection'] ?? '',
				'fallback_connection' => $settings['fallback_connection'] ?? '',
				'from_email'          => $settings['from_email'] ?? '',
				'from_name'           => $settings['from_name'] ?? '',
				'simulation_mode'     => ! empty( $settings['simulation_mode'] ),
				'queue_enabled'       => ! empty( $settings['queue_enabled'] ),
				'log_enabled'         => ! isset( $settings['log_enabled'] ) || $settings['log_enabled'],
				'log_email_body'      => ! empty( $settings['log_email_body'] ),
				'log_retention_days'  => $settings['log_retention_days'] ?? 30,
				'developer_debug'     => ! empty( $settings['developer_debug'] ),
				'connections'         => $conn_summary,
			),
			'alerts'  => $alert_channels,
			'cron'    => array(
				'process_queue'      => self::format_cron( 'sendforce_process_queue' ),
				'cleanup_logs'       => self::format_cron( 'sendforce_cleanup_logs' ),
				'send_daily_summary' => self::format_cron( 'sendforce_send_daily_summary' ),
			),
			'queue'   => SendForce_Queue::get_stats(),
			'logs'    => self::log_counts(),
		);
	}

	/**
	 * Render sysinfo as a plain-text block (suitable for pasting into support tickets).
	 *
	 * @return string
	 */
	public static function as_text() {
		$data  = self::collect();
		$lines = array();
		$lines[] = '### SendForce Mail Relay — Debug Info ###';
		self::flatten( $data, '', $lines );
		return implode( "\n", $lines );
	}

	/**
	 * Recursively flatten an array into "key.subkey: value" lines.
	 *
	 * @param array  $data   Data.
	 * @param string $prefix Key prefix.
	 * @param array  $lines  Output buffer (by ref).
	 */
	private static function flatten( $data, $prefix, &$lines ) {
		foreach ( $data as $key => $value ) {
			$path = $prefix ? "{$prefix}.{$key}" : $key;
			if ( is_array( $value ) ) {
				if ( empty( $value ) ) {
					$lines[] = "{$path}: (empty)";
				} elseif ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
					// List of items — print as JSON one-liner per item.
					foreach ( $value as $i => $item ) {
						if ( is_array( $item ) ) {
							$lines[] = "{$path}[{$i}]: " . wp_json_encode( $item );
						} else {
							$lines[] = "{$path}[{$i}]: " . self::scalar( $item );
						}
					}
				} else {
					self::flatten( $value, $path, $lines );
				}
			} else {
				$lines[] = "{$path}: " . self::scalar( $value );
			}
		}
	}

	/**
	 * Stringify a scalar value safely.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function scalar( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'true' : 'false';
		}
		if ( null === $v ) {
			return 'null';
		}
		return (string) $v;
	}

	/**
	 * Active plugins as "name (version)" array.
	 *
	 * @return array
	 */
	private static function active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$result  = array();
		foreach ( $active as $file ) {
			if ( isset( $all[ $file ] ) ) {
				$result[] = $all[ $file ]['Name'] . ' ' . $all[ $file ]['Version'];
			}
		}
		return $result;
	}

	/**
	 * Format the next-scheduled timestamp for a cron hook.
	 *
	 * @param string $hook Cron hook.
	 * @return string
	 */
	private static function format_cron( $hook ) {
		$ts = wp_next_scheduled( $hook );
		return $ts ? wp_date( 'Y-m-d H:i:s', $ts ) : 'not scheduled';
	}

	/**
	 * Log row counts by status.
	 *
	 * @return array
	 */
	private static function log_counts() {
		global $wpdb;
		$table = $wpdb->prefix . 'sendforce_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table built from $wpdb->prefix + hardcoded literal; no user input.
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" );
		$out  = array(
			'sent'   => 0,
			'failed' => 0,
			'total'  => 0,
		);
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$out[ $r->status ] = absint( $r->n );
				$out['total']    += absint( $r->n );
			}
		}
		return $out;
	}
}
