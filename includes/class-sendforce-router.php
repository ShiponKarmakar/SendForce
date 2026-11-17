<?php
/**
 * Smart routing rules engine.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Router
 *
 * Decides which connection sends each email based on user-defined rules.
 *
 * Rules are stored in the `sendforce_routes` table and evaluated top-down
 * (lowest priority value first). The first rule whose ALL conditions match
 * wins; the email is then sent through the rule's target connection.
 *
 * If no rule matches, the email falls back to the global default ("primary")
 * connection — preserving existing behaviour for users who never configure
 * routing.
 */
class SendForce_Router {

	/**
	 * Cache of matched rule per request to avoid recomputing on log/etc.
	 * Keyed by md5(serialize($atts)).
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Static signal: when a rule matched the most recent send, this holds the
	 * rule's id + label so the logger / debug panel can surface it.
	 *
	 * @var array
	 */
	public static $last_matched_rule = array();

	/**
	 * Resolve which connection settings should be used for a given wp_mail
	 * call. Returns null when no rule matches and the caller should use the
	 * global primary connection.
	 *
	 * @param array $atts wp_mail attributes (to, subject, message, headers, attachments).
	 * @return array|null Connection settings array, or null for "use default".
	 */
	public static function resolve_connection_for( $atts ) {
		self::$last_matched_rule = array();

		// Master switch: when Smart Routing is disabled the router behaves as if
		// no rules existed — every email goes through the primary connection.
		if ( ! self::is_routing_enabled() ) {
			return null;
		}

		$key = md5( wp_json_encode( $atts ) );
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$rules = self::get_active_rules();
		if ( empty( $rules ) ) {
			return self::$cache[ $key ] = null;
		}

		$context = self::build_context( $atts );

		foreach ( $rules as $rule ) {
			if ( ! self::rule_matches( $rule, $context ) ) {
				continue;
			}

			$settings = self::pick_connection_settings( $rule );
			if ( null === $settings ) {
				// Target connection no longer exists — skip this rule, try the next one.
				continue;
			}

			self::$last_matched_rule = array(
				'id'    => (int) $rule['id'],
				'label' => (string) $rule['label'],
			);
			return self::$cache[ $key ] = $settings;
		}

		return self::$cache[ $key ] = null;
	}

	/**
	 * Detect which plugin (or "wordpress-core") originated the wp_mail call,
	 * by walking the PHP backtrace and finding the first file whose path
	 * contains /wp-content/plugins/<slug>/.
	 *
	 * @return string Plugin slug, or "wordpress-core".
	 */
	public static function detect_source_plugin() {
		// 25 frames is plenty — wp_mail is called shallow from most plugins.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- intentional, used to identify caller.
		$trace      = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 );
		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$mu_dir     = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
		$self_dir   = wp_normalize_path( SENDFORCE_PLUGIN_DIR );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );

			// Skip our own files — we're looking for the CALLER, not ourselves.
			if ( 0 === strpos( $file, $self_dir ) ) {
				continue;
			}

			if ( 0 === strpos( $file, $plugin_dir . '/' ) ) {
				$rel  = substr( $file, strlen( $plugin_dir ) + 1 );
				$slug = strtok( $rel, '/' );
				if ( $slug ) {
					return $slug;
				}
			}

			if ( $mu_dir && 0 === strpos( $file, $mu_dir . '/' ) ) {
				return 'mu-plugins';
			}
		}

		return 'wordpress-core';
	}

	/**
	 * Build the context object that conditions are matched against.
	 *
	 * @param array $atts wp_mail attributes.
	 * @return array
	 */
	private static function build_context( $atts ) {
		$to = $atts['to'] ?? '';
		if ( is_array( $to ) ) {
			$to_first = (string) reset( $to );
		} else {
			$parts    = explode( ',', (string) $to );
			$to_first = trim( (string) reset( $parts ) );
		}

		$to_domain = '';
		if ( false !== strpos( $to_first, '@' ) ) {
			$to_domain = strtolower( substr( strrchr( $to_first, '@' ), 1 ) );
		}

		// Best-effort From-address extraction. Headers may be a string or array;
		// when no header sets From explicitly we fall back to the global
		// sendforce_settings['from_email'] (the "Force From" identity).
		$from_email  = '';
		$headers_raw = $atts['headers'] ?? '';
		$header_list = is_array( $headers_raw ) ? $headers_raw : preg_split( "/\r\n|\r|\n/", (string) $headers_raw );
		foreach ( (array) $header_list as $line ) {
			if ( preg_match( '/^\s*From:\s*(.*)$/i', (string) $line, $m ) ) {
				if ( preg_match( '/<([^>]+)>/', $m[1], $m2 ) ) {
					$from_email = strtolower( trim( $m2[1] ) );
				} else {
					$from_email = strtolower( trim( $m[1] ) );
				}
				break;
			}
		}
		if ( '' === $from_email ) {
			$global_settings = get_option( 'sendforce_settings', array() );
			if ( ! empty( $global_settings['from_email'] ) ) {
				$from_email = strtolower( (string) $global_settings['from_email'] );
			}
		}
		$from_domain = '';
		if ( false !== strpos( $from_email, '@' ) ) {
			$from_domain = strtolower( substr( strrchr( $from_email, '@' ), 1 ) );
		}

		return array(
			'to'             => $to_first,
			'to_domain'      => $to_domain,
			'from_email'     => $from_email,
			'from_domain'    => $from_domain,
			'subject'        => (string) ( $atts['subject'] ?? '' ),
			'source_plugin'  => self::detect_source_plugin(),
			'hour'           => (int) current_time( 'G' ),
			'weekday'        => (int) current_time( 'N' ), // 1 = Mon, 7 = Sun
		);
	}

	/**
	 * Build a context array from explicit test inputs (used by the Test Routing
	 * tool). Mirrors build_context() but skips backtrace detection and lets the
	 * caller pin source_plugin / hour / weekday explicitly.
	 *
	 * @param array $params Keys: to, subject, from, source_plugin, hour, weekday.
	 * @return array
	 */
	public static function build_test_context( $params ) {
		$to            = (string) ( $params['to']            ?? '' );
		$subject       = (string) ( $params['subject']       ?? '' );
		$from          = strtolower( (string) ( $params['from'] ?? '' ) );
		$source_plugin = (string) ( $params['source_plugin'] ?? 'wordpress-core' );

		$to_domain = ( false !== strpos( $to, '@' ) )
			? strtolower( substr( strrchr( $to, '@' ), 1 ) )
			: '';

		$from_domain = ( false !== strpos( $from, '@' ) )
			? strtolower( substr( strrchr( $from, '@' ), 1 ) )
			: '';

		return array(
			'to'             => $to,
			'to_domain'      => $to_domain,
			'from_email'     => $from,
			'from_domain'    => $from_domain,
			'subject'        => $subject,
			'source_plugin'  => $source_plugin ?: 'wordpress-core',
			'hour'           => isset( $params['hour'] )    ? (int) $params['hour']    : (int) current_time( 'G' ),
			'weekday'        => isset( $params['weekday'] ) ? (int) $params['weekday'] : (int) current_time( 'N' ),
		);
	}

	/**
	 * Returns true if the given connection id resolves to a live connection
	 * (or the special "phpmail" pseudo-id). Used by the rule list UI to flag
	 * rules whose target has been deleted.
	 *
	 * @param string $connection_id
	 * @return bool
	 */
	public static function connection_exists( $connection_id ) {
		if ( 'phpmail' === $connection_id ) {
			return true;
		}
		if ( '' === (string) $connection_id ) {
			return false;
		}
		$conns = (array) get_option( 'sendforce_connections', array() );
		foreach ( $conns as $c ) {
			if ( isset( $c['id'] ) && $c['id'] === $connection_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Master "Enable Smart Routing" switch. Defaults to on so existing installs
	 * with rules don't silently stop matching after upgrade.
	 *
	 * @return bool
	 */
	public static function is_routing_enabled() {
		return (bool) get_option( 'sendforce_routing_enabled', 1 );
	}

	/**
	 * Test whether a rule's conditions ALL match the given context.
	 *
	 * Public so the admin "Test Routing" tool evaluates rules through the
	 * exact same logic the live mailer uses — preventing UI/runtime drift.
	 *
	 * @param array $rule
	 * @param array $context
	 * @return bool
	 */
	public static function rule_matches( $rule, $context ) {
		$conditions = json_decode( $rule['conditions'], true );
		if ( ! is_array( $conditions ) || empty( $conditions ) ) {
			return false;
		}

		foreach ( $conditions as $cond ) {
			$type  = $cond['type']  ?? '';
			$op    = $cond['op']    ?? 'equals';
			$value = $cond['value'] ?? '';

			switch ( $type ) {
				case 'to_domain':
					if ( ! self::compare_string( $context['to_domain'], $op, $value ) ) {
						return false;
					}
					break;

				case 'from_email':
					if ( ! self::compare_string( $context['from_email'], $op, $value ) ) {
						return false;
					}
					break;

				case 'from_domain':
					if ( ! self::compare_string( $context['from_domain'], $op, $value ) ) {
						return false;
					}
					break;

				case 'to_pattern':
					// Glob-style match on the recipient address.
					if ( ! self::glob_match( $context['to'], $value ) ) {
						return false;
					}
					break;

				case 'source_plugin':
					if ( ! self::compare_string( $context['source_plugin'], $op, $value ) ) {
						return false;
					}
					break;

				case 'subject_contains':
					if ( '' === $value || false === stripos( $context['subject'], $value ) ) {
						return false;
					}
					break;

				case 'time_between':
					// $value is "HH-HH" e.g. "09-18".
					$range = explode( '-', $value );
					if ( count( $range ) !== 2 ) {
						return false;
					}
					$start = (int) $range[0];
					$end   = (int) $range[1];
					$h     = $context['hour'];
					if ( $start <= $end ) {
						if ( $h < $start || $h >= $end ) {
							return false;
						}
					} else {
						// Overnight wrap, e.g. 22-06.
						if ( $h < $start && $h >= $end ) {
							return false;
						}
					}
					break;

				case 'weekday':
					// $value is comma-list of ISO weekday numbers, e.g. "1,2,3,4,5" for Mon-Fri.
					$days = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
					if ( ! in_array( $context['weekday'], $days, true ) ) {
						return false;
					}
					break;

				default:
					// Unknown condition type — never matches.
					return false;
			}
		}

		return true;
	}

	private static function compare_string( $haystack, $op, $needle ) {
		$haystack = strtolower( (string) $haystack );
		$needle   = strtolower( (string) $needle );

		switch ( $op ) {
			case 'equals':
				return $haystack === $needle;
			case 'not_equals':
				return $haystack !== $needle;
			case 'contains':
				return '' !== $needle && false !== strpos( $haystack, $needle );
			case 'starts_with':
				return '' !== $needle && 0 === strpos( $haystack, $needle );
			case 'ends_with':
				return '' !== $needle && substr( $haystack, -strlen( $needle ) ) === $needle;
		}
		return false;
	}

	private static function glob_match( $subject, $pattern ) {
		$pattern = strtolower( (string) $pattern );
		$subject = strtolower( (string) $subject );
		$regex   = '#^' . str_replace( array( '\\*', '\\?' ), array( '.*', '.' ), preg_quote( $pattern, '#' ) ) . '$#';
		return (bool) preg_match( $regex, $subject );
	}

	/**
	 * Resolve the action of a matched rule to a settings array (the same shape
	 * SendForce_Mailer expects). Handles both "specific connection" and
	 * "round robin" actions.
	 *
	 * @return array|null
	 */
	private static function pick_connection_settings( $rule ) {
		$type  = $rule['action_type']  ?? 'connection';
		$value = $rule['action_value'] ?? '';

		$ids = array();
		if ( 'roundrobin' === $type ) {
			// array_values() re-indexes after array_filter(): filtering out an
			// empty entry (e.g. a since-deleted connection id, leaving "1,,3")
			// leaves gaps in the keys, so a positional wp_rand() lookup could
			// hit an undefined index and pick nothing.
			$ids = array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
			if ( empty( $ids ) ) {
				return null;
			}
			$pick = $ids[ wp_rand( 0, count( $ids ) - 1 ) ];
		} else {
			$pick = (string) $value;
		}

		return self::settings_for_connection( $pick );
	}

	/**
	 * Look up a connection by id and return a "ready to send" settings array
	 * (merged with global from_email / from_name from sendforce_settings).
	 *
	 * @return array|null
	 */
	private static function settings_for_connection( $connection_id ) {
		$base = get_option( 'sendforce_settings', array() );

		// `_connection_id` records which connection was chosen, so callers that
		// only receive the merged settings array (e.g. the queue's per-connection
		// rate limiter) can tell connections apart.
		if ( 'phpmail' === $connection_id ) {
			$base['provider']       = 'phpmail';
			$base['_connection_id'] = 'phpmail';
			return $base;
		}
		if ( empty( $connection_id ) ) {
			return null;
		}

		$conns = (array) get_option( 'sendforce_connections', array() );
		foreach ( $conns as $c ) {
			if ( isset( $c['id'] ) && $c['id'] === $connection_id ) {
				$keys = array( 'provider', 'connection_type', 'host', 'port', 'encryption', 'auth', 'username', 'password', 'api_key', 'ses_region', 'ses_access_key', 'mailgun_domain', 'mailgun_region', 'smtpcom_channel', 'socketlabs_server_id', 'outlook_tenant_id', 'outlook_client_id', 'gmail_client_id' );
				foreach ( $keys as $k ) {
					if ( isset( $c[ $k ] ) ) {
						$base[ $k ] = $c[ $k ];
					}
				}
				$base['_connection_id'] = $connection_id;
				return $base;
			}
		}
		return null;
	}

	/**
	 * Fetch all enabled rules ordered by priority ASC.
	 */
	public static function get_active_rules() {
		global $wpdb;
		$table = $wpdb->prefix . 'sendforce_routes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table from $wpdb->prefix + literal; no user input.
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE enabled = 1 ORDER BY priority ASC, id ASC",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * List of supported source plugins, for the rule editor dropdown.
	 *
	 * @return array slug => label
	 */
	public static function known_source_plugins() {
		return array(
			'wordpress-core'         => __( 'WordPress core (password reset, comment notifications, etc.)', 'sendforce-mail-relay' ),
			'woocommerce'            => 'WooCommerce',
			'contact-form-7'         => 'Contact Form 7',
			'wpforms-lite'           => 'WPForms Lite',
			'wpforms'                => 'WPForms (Pro)',
			'gravityforms'           => 'Gravity Forms',
			'ninja-forms'            => 'Ninja Forms',
			'fluentform'             => 'Fluent Forms',
			'forminator'             => 'Forminator',
			'easy-digital-downloads' => 'Easy Digital Downloads',
			'memberpress'            => 'MemberPress',
			'sfwd-lms'               => 'LearnDash',
			'mailpoet'               => 'MailPoet (transactional)',
			'buddypress'             => 'BuddyPress',
			'bbpress'                => 'bbPress',
			'mu-plugins'             => __( 'Any must-use plugin', 'sendforce-mail-relay' ),
		);
	}
}
