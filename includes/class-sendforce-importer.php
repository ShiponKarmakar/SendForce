<?php
/**
 * Import connections and sender settings from other SMTP plugins.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Importer
 *
 * Reads each source plugin's settings through that plugin's own API — never
 * its raw options. Most encrypt or encode credentials (WP Mail SMTP and Easy
 * WP SMTP: the SMTP password, with libsodium; FluentSMTP: most secrets, with
 * AES and rules that depend on the version a value was saved under; Post SMTP
 * and SMTP Mailer: base64), and several let wp-config.php constants override
 * saved values. Their own readers handle all of that and keep working when
 * they change it, which is why the source plugin must be active to import.
 *
 * Flow: plan() works out what would be imported, preview() is the same with
 * secrets removed (shown to the admin first), import() writes it. Importing
 * only ever ADDS connections; existing SendForce settings are not replaced.
 */
class SendForce_Importer {

	/**
	 * Supported sources, keyed by slug (the plugin's folder name).
	 *
	 * @var array<string,string>
	 */
	const SOURCES = array(
		'wp-mail-smtp' => 'WP Mail SMTP',
		'fluent-smtp'  => 'FluentSMTP',
		'easy-wp-smtp' => 'Easy WP SMTP',
		'post-smtp'    => 'Post SMTP',
		'smtp-mailer'  => 'SMTP Mailer',
		'wp-smtp'      => 'WP SMTP (Kadence Mail)',
	);

	/**
	 * Sources that store settings the way WP Mail SMTP does: an Options object
	 * with get( $group, $key ) / is_const_defined(), one active mailer, the SMTP
	 * password encrypted and decrypted on read, and an optional network-wide
	 * mode on Multisite. Maps the source slug to its class namespace.
	 *
	 * @var array<string,string>
	 */
	const OPTIONS_API_SOURCES = array(
		'wp-mail-smtp' => '\WPMailSMTP\\',
		'easy-wp-smtp' => '\EasyWPSMTP\\',
	);

	/**
	 * Providers that send through a mailbox's OAuth sign-in. Their app
	 * credentials import, but the sign-in itself is tied to the other plugin's
	 * OAuth setup, so the admin must click Connect again in SendForce.
	 *
	 * @var string[]
	 */
	const OAUTH_PROVIDERS = array( 'gmail', 'outlook' );

	/**
	 * Whether a source can be imported right now (plugin active, API present).
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function is_available( $source ) {
		if ( isset( self::OPTIONS_API_SOURCES[ $source ] ) ) {
			return class_exists( self::OPTIONS_API_SOURCES[ $source ] . 'Options' );
		}
		switch ( $source ) {
			case 'fluent-smtp':
				return function_exists( 'fluentMailGetSettings' );
			case 'post-smtp':
				return class_exists( 'PostmanOptions' ) && is_callable( array( 'PostmanOptions', 'getInstance' ) );
			case 'smtp-mailer':
				return function_exists( 'smtp_mailer_get_option' );
			case 'wp-smtp':
				return class_exists( '\SolidWP\Mail\Repository\ProvidersRepository' );
		}
		return false;
	}

	/**
	 * Sources that can be imported right now.
	 *
	 * @return array slug => plugin name.
	 */
	public static function available_sources() {
		return array_filter(
			self::SOURCES,
			array( __CLASS__, 'is_available' ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * What import() would do, with secrets removed — safe to send to the browser.
	 *
	 * @param string $source Source slug.
	 * @return array|WP_Error
	 */
	public static function preview( $source ) {
		$plan = self::plan( $source );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$existing = self::existing_import_keys();
		foreach ( $plan['items'] as $i => $item ) {
			unset( $plan['items'][ $i ]['conn'] );
			if ( isset( $existing[ $item['key'] ] ) ) {
				$plan['items'][ $i ]['status'] = 'already';
			}
		}
		return $plan;
	}

	/**
	 * Import a source's connections and sender settings into SendForce.
	 *
	 * @param string $source Source slug.
	 * @return array|WP_Error Summary of what was done.
	 */
	public static function import( $source ) {
		global $wpdb;

		$plan = self::plan( $source );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$connections = get_option( 'sendforce_connections', array() );
		$connections = is_array( $connections ) ? $connections : array();
		$settings    = get_option( 'sendforce_settings', array() );
		$settings    = is_array( $settings ) ? $settings : array();
		$existing    = self::existing_import_keys();
		$known_ids   = array();
		foreach ( $connections as $c ) { // Not wp_list_pluck(): it warns on a row with no id.
			if ( is_array( $c ) && ! empty( $c['id'] ) ) {
				$known_ids[] = (string) $c['id'];
			}
		}

		$result  = array(
			'imported'     => array(),
			'already'      => array(),
			'skipped'      => array(),
			'needs_action' => array(),
			'primary_set'  => false,
			'fallback_set' => false,
			'sender_set'   => false,
			'rules'        => 0,
		);
		$new_ids = array();

		foreach ( $plan['items'] as $item ) {
			if ( 'unsupported' === $item['status'] ) {
				$result['skipped'][] = $item['label'];
				continue;
			}
			if ( isset( $existing[ $item['key'] ] ) ) {
				$result['already'][] = $item['label'];
				$new_ids[ $item['key'] ] = $existing[ $item['key'] ];
				continue;
			}

			$conn = $item['conn'];
			foreach ( array( 'password', 'api_key' ) as $secret ) {
				$conn[ $secret ] = '' !== (string) $conn[ $secret ] ? SendForce_Mailer::encrypt_password( (string) $conn[ $secret ] ) : '';
			}
			$conn['id']            = 'conn_' . substr( md5( uniqid( '', true ) ), 0, 12 );
			$conn['label']         = $item['label'];
			$conn['imported_from'] = $source;
			$conn['import_key']    = $item['key'];
			$connections[]         = $conn;
			$known_ids[]           = $conn['id'];
			$new_ids[ $item['key'] ] = $conn['id'];

			$result['imported'][] = $item['label'];
			if ( 'needs_action' === $item['status'] ) {
				$result['needs_action'][] = $item['label'];
			}
		}

		// Primary / fallback: only fill them in when SendForce has none (or the
		// stored one no longer exists) — an import never replaces your choice.
		foreach ( array( 'primary' => 'default_connection', 'fallback' => 'fallback_connection' ) as $role => $setting_key ) {
			$current = (string) ( $settings[ $setting_key ] ?? '' );
			if ( '' !== $current && in_array( $current, $known_ids, true ) ) {
				continue;
			}
			foreach ( $plan['items'] as $item ) {
				if ( $role === $item['role'] && isset( $new_ids[ $item['key'] ] ) ) {
					$settings[ $setting_key ]    = $new_ids[ $item['key'] ];
					$result[ $role . '_set' ] = true;
					break;
				}
			}
		}
		if ( ! empty( $settings['fallback_connection'] ) && $settings['fallback_connection'] === ( $settings['default_connection'] ?? '' ) ) {
			$settings['fallback_connection'] = '';
			$result['fallback_set']          = false;
		}

		// Sender identity: only when SendForce has none and the source forced one
		// (SendForce's Sender Identity always overrides every email's From).
		if ( empty( $settings['from_email'] ) && ! empty( $plan['sender']['force'] ) && is_email( $plan['sender']['email'] ) ) {
			$settings['from_email'] = sanitize_email( $plan['sender']['email'] );
			if ( '' !== $plan['sender']['name'] && empty( $settings['from_name'] ) ) {
				$settings['from_name'] = sanitize_text_field( $plan['sender']['name'] );
			}
			$result['sender_set'] = true;
		}

		update_option( 'sendforce_connections', $connections );
		update_option( 'sendforce_settings', $settings );
		delete_transient( 'sendforce_broken_credentials' );

		// FluentSMTP picks a connection by the email's From address. Recreate
		// that as Smart Routing rules, or extra connections would never be used.
		$routes_table = $wpdb->prefix . 'sendforce_routes';
		foreach ( $plan['items'] as $item ) {
			if ( empty( $item['route_from'] ) || empty( $new_ids[ $item['key'] ] ) ) {
				continue;
			}
			foreach ( $item['route_from'] as $from ) {
				$conditions = wp_json_encode( array( array( 'type' => 'from_email', 'op' => 'equals', 'value' => $from ) ) );
				// Match on what the rule DOES, not its label: the label is
				// translated, so a site-language change would defeat a label check.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix + literal.
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$routes_table} WHERE action_value = %s AND conditions = %s", $new_ids[ $item['key'] ], $conditions ) );
				if ( $exists ) {
					continue;
				}
				$label = sprintf(
					/* translators: %s: sender email address */
					__( 'From %s (imported)', 'sendforce-mail-relay' ),
					$from
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix + literal.
				$priority = (int) $wpdb->get_var( "SELECT COALESCE(MAX(priority), 0) + 1 FROM {$routes_table}" );
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$routes_table,
					array(
						'label'        => $label,
						'enabled'      => 1,
						'priority'     => $priority,
						'conditions'   => $conditions,
						'action_type'  => 'connection',
						'action_value' => $new_ids[ $item['key'] ],
						'created_at'   => current_time( 'mysql' ),
						'updated_at'   => current_time( 'mysql' ),
					)
				);
				++$result['rules'];
			}
		}

		// Never flip the user's master switch, but say the rules are inert.
		$result['routing_off'] = $result['rules'] > 0 && ! SendForce_Router::is_routing_enabled();

		return $result;
	}

	/**
	 * Work out what would be imported from a source.
	 *
	 * Each item: key (stable id used to skip repeat imports), label, provider,
	 * provider_label, mode (smtp|api), role (primary|fallback|extra), status
	 * (ok|needs_action|unsupported), notes[], summary, conn (SendForce
	 * connection fields with PLAINTEXT secrets — never sent to the browser),
	 * route_from[] (sender addresses to route to it).
	 *
	 * @param string $source Source slug.
	 * @return array|WP_Error { source, source_label, items[], sender{email,name,force} }
	 */
	public static function plan( $source ) {
		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return new WP_Error( 'sendforce_import_source', __( 'Unknown source plugin.', 'sendforce-mail-relay' ) );
		}
		if ( ! self::is_available( $source ) ) {
			return new WP_Error(
				'sendforce_import_inactive',
				sprintf(
					/* translators: %s: plugin name */
					__( '%s must be active to import its settings. Activate it, import, then deactivate it.', 'sendforce-mail-relay' ),
					self::SOURCES[ $source ]
				)
			);
		}

		if ( self::network_wide( $source ) ) {
			// These plugins can keep one configuration for the whole network. It
			// lives on the main site, with secrets encrypted by the main site's
			// key, and is hidden from site admins. Read it there, and only for a
			// network administrator.
			if ( ! self::can_read_network_settings() ) {
				return new WP_Error(
					'sendforce_import_network',
					sprintf(
						/* translators: %s: plugin name */
						__( '%s is configured network-wide on this network. Only a network administrator can import its settings.', 'sendforce-mail-relay' ),
						self::SOURCES[ $source ]
					)
				);
			}
			switch_to_blog( get_main_site_id() );
			$plan = self::plan_options_api( $source );
			restore_current_blog();
		} elseif ( isset( self::OPTIONS_API_SOURCES[ $source ] ) ) {
			$plan = self::plan_options_api( $source );
		} elseif ( 'post-smtp' === $source ) {
			$plan = self::plan_post_smtp();
		} elseif ( 'smtp-mailer' === $source ) {
			$plan = self::plan_smtp_mailer();
		} elseif ( 'wp-smtp' === $source ) {
			$plan = self::plan_wp_smtp();
		} else {
			$plan = self::plan_fluent_smtp();
		}
		$plan['source']       = $source;
		$plan['source_label'] = self::SOURCES[ $source ];

		if ( empty( $plan['items'] ) ) {
			return new WP_Error(
				'sendforce_import_empty',
				sprintf(
					/* translators: %s: plugin name */
					__( 'No connection is configured in %s, so there is nothing to import.', 'sendforce-mail-relay' ),
					self::SOURCES[ $source ]
				)
			);
		}
		return $plan;
	}

	// ---------------------------------------------------------------------
	// WP Mail SMTP
	// ---------------------------------------------------------------------

	/**
	 * Whether the caller may read the network's shared WP Mail SMTP settings.
	 *
	 * Over WP-CLI there is no logged-in user, and whoever runs it already has
	 * shell and database access, so capability checks would only block a
	 * legitimate operator. A `--user=` on the command line is still honoured.
	 *
	 * @return bool
	 */
	private static function can_read_network_settings() {
		if ( defined( 'WP_CLI' ) && WP_CLI && ! get_current_user_id() ) {
			return true;
		}
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Whether a source uses one network-wide configuration (Multisite).
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	private static function network_wide( $source ) {
		if ( ! is_multisite() || ! isset( self::OPTIONS_API_SOURCES[ $source ] ) ) {
			return false;
		}
		$wp = self::OPTIONS_API_SOURCES[ $source ] . 'WP';
		return is_callable( array( $wp, 'use_global_plugin_settings' ) )
			&& call_user_func( array( $wp, 'use_global_plugin_settings' ) );
	}

	/**
	 * WP Mail SMTP and Easy WP SMTP share a settings design: one active mailer,
	 * read through an Options class that applies wp-config.php constants and
	 * decrypts the SMTP password. Easy WP SMTP is the same code lineage, so the
	 * group and key names match; where they differ (Easy WP SMTP's Gmail
	 * One-Click Setup, WP Mail SMTP's Pepipost) the extra keys are simply absent
	 * on the other plugin and read back empty.
	 *
	 * @param string $source Source slug (must be in OPTIONS_API_SOURCES).
	 * @return array
	 */
	private static function plan_options_api( $source ) {
		$class  = self::OPTIONS_API_SOURCES[ $source ] . 'Options';
		$o      = new $class();
		$mailer = (string) $o->get( 'mail', 'mailer' );
		$label  = self::SOURCES[ $source ];
		$prefix = 'wp-mail-smtp' === $source ? 'wpms' : 'ewp';
		$notes  = array();

		// A credential set as a wp-config.php constant is not copied into the
		// database: the admin chose to keep it out of there.
		$secret = function ( $group, $key ) use ( $o, &$notes ) {
			if ( $o->is_const_defined( $group, $key ) ) {
				$notes['wpconfig'] = __( 'A credential is set in wp-config.php and was not copied. Enter it in this connection.', 'sendforce-mail-relay' );
				return '';
			}
			return (string) $o->get( $group, $key );
		};

		$api = array(
			'sendgrid'     => array( 'sendgrid', 'api_key' ),
			'sparkpost'    => array( 'sparkpost', 'api_key' ),
			'sendinblue'   => array( 'brevo', 'api_key' ),
			'sendlayer'    => array( 'sendlayer', 'api_key' ),
			'elasticemail' => array( 'elasticemail', 'api_key' ),
			'smtp2go'      => array( 'smtp2go', 'api_key' ),
			'resend'       => array( 'resend', 'api_key' ),
			'mandrill'     => array( 'mandrill', 'api_key' ),
			'mailersend'   => array( 'mailersend', 'api_key' ),
			'pepipostapi'  => array( 'pepipost', 'api_key' ),
			'postmark'     => array( 'postmark', 'server_api_token' ),
		);

		$conn = null;
		if ( 'mail' === $mailer ) {
			$conn = self::conn( 'phpmail', 'smtp' );
		} elseif ( 'smtp' === $mailer || 'pepipost' === $mailer ) {
			$conn = self::smtp_conn(
				$o->get( $mailer, 'host' ),
				$o->get( $mailer, 'port' ),
				$o->get( $mailer, 'encryption' ),
				(bool) $o->get( $mailer, 'auth' ),
				$o->get( $mailer, 'user' ),
				$secret( $mailer, 'pass' ),
				'pepipost' === $mailer ? 'pepipost' : ''
			);
		} elseif ( isset( $api[ $mailer ] ) ) {
			$conn            = self::conn( $api[ $mailer ][0], 'api' );
			$conn['api_key'] = $secret( $mailer, $api[ $mailer ][1] );
			if ( 'sparkpost' === $mailer && 'eu' === strtolower( (string) $o->get( 'sparkpost', 'region' ) ) ) {
				// SendForce talks to api.sparkpost.com; an EU account lives on
				// api.eu.sparkpost.com and would reject the key.
				$notes['region'] = __( 'This SparkPost account is on the EU region, which SendForce does not support yet. The key was copied, but sending through it will fail.', 'sendforce-mail-relay' );
			}
			if ( 'postmark' === $mailer ) {
				$stream = (string) $o->get( 'postmark', 'message_stream' );
				if ( '' !== $stream && 'outbound' !== $stream ) {
					$notes['stream'] = sprintf(
						/* translators: %s: Postmark message stream id */
						__( 'Postmark message stream "%s" is not supported; SendForce sends through the default "outbound" stream.', 'sendforce-mail-relay' ),
						$stream
					);
				}
			}
		} elseif ( 'mailgun' === $mailer ) {
			$conn                   = self::conn( 'mailgun', 'api' );
			$conn['api_key']        = $secret( 'mailgun', 'api_key' );
			$conn['mailgun_domain'] = (string) $o->get( 'mailgun', 'domain' );
			$conn['mailgun_region'] = 'eu' === strtolower( (string) $o->get( 'mailgun', 'region' ) ) ? 'eu' : 'us';
		} elseif ( 'amazonses' === $mailer ) {
			$conn                   = self::conn( 'ses', 'api' );
			$conn['ses_access_key'] = $secret( 'amazonses', 'client_id' );
			$conn['api_key']        = $secret( 'amazonses', 'client_secret' );
			$conn['ses_region']     = (string) ( $o->get( 'amazonses', 'region' ) ?: 'us-east-1' );
		} elseif ( 'mailjet' === $mailer ) {
			$conn             = self::conn( 'mailjet', 'api' );
			$conn['username'] = $secret( 'mailjet', 'api_key' );   // Public key.
			$conn['api_key']  = $secret( 'mailjet', 'secret_key' );
		} elseif ( 'smtpcom' === $mailer ) {
			$conn                    = self::conn( 'smtpcom', 'api' );
			$conn['api_key']         = $secret( 'smtpcom', 'api_key' );
			$conn['smtpcom_channel'] = (string) $o->get( 'smtpcom', 'channel' );
		} elseif ( 'gmail' === $mailer || 'outlook' === $mailer ) {
			$conn                            = self::conn( $mailer, 'api' );
			$conn[ $mailer . '_client_id' ]  = (string) $o->get( $mailer, 'client_id' );
			$conn['api_key']                 = $secret( $mailer, 'client_secret' );
			if ( '' === $conn[ $mailer . '_client_id' ] && $o->get( $mailer, 'one_click_setup_enabled' ) ) {
				// Easy WP SMTP's One-Click Setup signs in through their own app,
				// so there are no app credentials on this site to copy.
				$notes['oneclick'] = __( 'This connection used One-Click Setup, so there are no app credentials stored here to copy. In SendForce, create your own Google or Microsoft app and paste its Client ID and Secret, then click Connect.', 'sendforce-mail-relay' );
			}
		}

		$items = array();
		if ( null === $conn ) {
			$items[] = self::unsupported( $prefix . ':' . $mailer, $mailer, $label );
		} elseif ( '' !== $mailer ) {
			$items[] = self::item( $prefix . ':' . $mailer, $conn, 'primary', $label, array_values( $notes ) );
		}

		return array(
			'items'  => $items,
			'sender' => array(
				'email' => (string) $o->get( 'mail', 'from_email' ),
				'name'  => (string) $o->get( 'mail', 'from_name' ),
				'force' => (bool) $o->get( 'mail', 'from_email_force' ),
			),
		);
	}

	// ---------------------------------------------------------------------
	// FluentSMTP
	// ---------------------------------------------------------------------

	/**
	 * FluentSMTP can have several connections, a fallback, and per-sender
	 * mappings. fluentMailGetSettings() returns them with secrets decrypted.
	 *
	 * @return array
	 */
	private static function plan_fluent_smtp() {
		$s           = fluentMailGetSettings( array(), false );
		$label       = self::SOURCES['fluent-smtp'];
		$default_key = (string) ( $s['misc']['default_connection'] ?? '' );
		$fallback    = (string) ( $s['misc']['fallback_connection'] ?? '' );
		$mappings    = is_array( $s['mappings'] ?? null ) ? $s['mappings'] : array();

		$api = array(
			'sendgrid'    => 'sendgrid',
			'sendinblue'  => 'brevo',
			'sparkpost'   => 'sparkpost',
			'pepipost'    => 'pepipost',
			'postmark'    => 'postmark',
			'elasticmail' => 'elasticemail',
			'smtp2go'     => 'smtp2go',
		);

		$items  = array();
		$sender = array( 'email' => '', 'name' => '', 'force' => false );

		foreach ( (array) ( $s['connections'] ?? array() ) as $key => $c ) {
			$ps       = is_array( $c['provider_settings'] ?? null ) ? $c['provider_settings'] : array();
			$provider = (string) ( $ps['provider'] ?? '' );
			if ( '' === $provider ) {
				continue; // A half-saved / corrupt entry: nothing to import, nothing to show.
			}
			$notes    = array();
			$wpconfig = 'wp_config' === ( $ps['key_store'] ?? 'db' );
			if ( $wpconfig ) {
				$notes[] = __( 'Credentials are stored in wp-config.php and were not copied. Enter them in this connection.', 'sendforce-mail-relay' );
			}
			$sec = function ( $field ) use ( $ps, $wpconfig ) {
				return $wpconfig ? '' : (string) ( $ps[ $field ] ?? '' );
			};

			$conn = null;
			if ( 'default' === $provider ) {
				$conn = self::conn( 'phpmail', 'smtp' );
			} elseif ( 'smtp' === $provider ) {
				$conn = self::smtp_conn( $ps['host'] ?? '', $ps['port'] ?? 587, $ps['encryption'] ?? 'tls', 'yes' === ( $ps['auth'] ?? 'yes' ), $sec( 'username' ), $sec( 'password' ), '' );
			} elseif ( isset( $api[ $provider ] ) ) {
				$conn            = self::conn( $api[ $provider ], 'api' );
				$conn['api_key'] = $sec( 'api_key' );
				if ( 'postmark' === $provider && ! empty( $ps['message_stream'] ) && 'outbound' !== $ps['message_stream'] ) {
					/* translators: %s: Postmark message stream id */
					$notes[] = sprintf( __( 'Postmark message stream "%s" is not supported; SendForce sends through the default "outbound" stream.', 'sendforce-mail-relay' ), $ps['message_stream'] );
				}
			} elseif ( 'mailgun' === $provider ) {
				$conn                   = self::conn( 'mailgun', 'api' );
				$conn['api_key']        = $sec( 'api_key' );
				$conn['mailgun_domain'] = (string) ( $ps['domain_name'] ?? '' );
				$conn['mailgun_region'] = 'eu' === strtolower( (string) ( $ps['region'] ?? 'us' ) ) ? 'eu' : 'us';
			} elseif ( 'ses' === $provider ) {
				$conn                   = self::conn( 'ses', 'api' );
				$conn['ses_access_key'] = $sec( 'access_key' );
				$conn['api_key']        = $sec( 'secret_key' );
				$conn['ses_region']     = (string) ( $ps['region'] ?? 'us-east-1' );
			} elseif ( 'gmail' === $provider || 'outlook' === $provider ) {
				$conn                              = self::conn( $provider, 'api' );
				$conn[ $provider . '_client_id' ]  = (string) ( $ps['client_id'] ?? '' );
				$conn['api_key']                   = $sec( 'client_secret' );
				if ( 'outlook' === $provider ) {
					$conn['outlook_tenant_id'] = (string) ( $ps['tenant_id'] ?? '' );
				}
			}

			$role = (string) $key === $default_key ? 'primary' : ( (string) $key === $fallback ? 'fallback' : 'extra' );
			$item_key = 'fluent:' . $key;
			if ( null === $conn ) {
				$items[] = self::unsupported( $item_key, $provider, $label );
				continue;
			}

			$item = self::item( $item_key, $conn, $role, $label, $notes );
			// Senders FluentSMTP routes to this connection (the primary already
			// handles everything that matches no rule).
			if ( 'primary' !== $role ) {
				foreach ( $mappings as $from => $mapped_key ) {
					if ( (string) $mapped_key === (string) $key && is_email( $from ) ) {
						$item['route_from'][] = $from;
					}
				}
			}
			$items[] = $item;

			if ( 'primary' === $role ) {
				$sender = array(
					'email' => (string) ( $ps['sender_email'] ?? '' ),
					'name'  => (string) ( $ps['sender_name'] ?? '' ),
					'force' => 'yes' === ( $ps['force_from_email'] ?? 'no' ),
				);
			}
		}

		// Primary first, then fallback, then the rest — the order they're shown.
		$rank = array( 'primary' => 0, 'fallback' => 1, 'extra' => 2 );
		usort( $items, function ( $a, $b ) use ( $rank ) {
			return $rank[ $a['role'] ] - $rank[ $b['role'] ];
		} );

		return array( 'items' => $items, 'sender' => $sender );
	}

	// ---------------------------------------------------------------------
	// Post SMTP
	// ---------------------------------------------------------------------

	/**
	 * Post SMTP has one active transport plus an optional fallback SMTP server.
	 * PostmanOptions decodes every stored secret (they are base64, some twice),
	 * applies its POST_SMTP_* wp-config.php constants, and on Multisite reads
	 * the network's shared settings when those are switched on.
	 *
	 * @return array
	 */
	private static function plan_post_smtp() {
		$o     = PostmanOptions::getInstance();
		$label = self::SOURCES['post-smtp'];
		$type  = (string) $o->getTransportType();
		$notes = array();

		// A secret set as a wp-config.php constant is not copied into the
		// database. Every API getter returns POST_SMTP_API_KEY when it is set.
		$wpconfig_note = __( 'A credential is set in wp-config.php and was not copied. Enter it in this connection.', 'sendforce-mail-relay' );
		$secret        = function ( $getter, $constant = 'POST_SMTP_API_KEY' ) use ( $o, &$notes, $wpconfig_note ) {
			if ( defined( $constant ) ) {
				$notes['wpconfig'] = $wpconfig_note;
				return '';
			}
			return (string) $o->$getter();
		};

		$api = array(
			'sendgrid_api'     => array( 'sendgrid', 'getSendGridApiKey' ),
			'sendinblue_api'   => array( 'brevo', 'getSendinblueApiKey' ),
			'mandrill_api'     => array( 'mandrill', 'getMandrillApiKey' ),
			'mailersend_api'   => array( 'mailersend', 'getMailerSendApiKey' ),
			'postmark_api'     => array( 'postmark', 'getPostmarkApiKey' ),
			'resend_api'       => array( 'resend', 'getResendApiKey' ),
			'sparkpost_api'    => array( 'sparkpost', 'getSparkPostApiKey' ),
			'elasticemail_api' => array( 'elasticemail', 'getElasticEmailApiKey' ),
			'smtp2go_api'      => array( 'smtp2go', 'getSmtp2GoApiKey' ),
		);

		$conn = null;
		if ( 'default' === $type ) {
			$conn = self::conn( 'phpmail', 'smtp' );
		} elseif ( 'smtp' === $type ) {
			$host = strtolower( trim( (string) $o->getHostname() ) );
			if ( $o->isAuthTypeOAuth2() && 'smtp.gmail.com' === $host ) {
				// Post SMTP's Gmail sign-in over SMTP. SendForce signs in to the
				// same Google app through the Gmail API instead.
				$conn                    = self::conn( 'gmail', 'api' );
				$conn['gmail_client_id'] = (string) $o->getClientId();
				$conn['api_key']         = (string) $o->getClientSecret();
			} else {
				$user = defined( 'POST_SMTP_AUTH_USERNAME' ) ? '' : (string) $o->getUsername();
				$pass = $secret( 'getPassword', 'POST_SMTP_AUTH_PASSWORD' );
				if ( defined( 'POST_SMTP_AUTH_USERNAME' ) ) {
					$notes['wpconfig'] = $wpconfig_note;
				}
				if ( $o->isAuthTypeOAuth2() ) {
					$pass            = '';
					$notes['oauth2'] = __( 'This server used an OAuth sign-in over SMTP, which SendForce does not support. Enter the account password or an app password in this connection.', 'sendforce-mail-relay' );
				}
				$conn = self::smtp_conn( $host, $o->getPort(), (string) $o->getEncryptionType(), ! $o->isAuthTypeNone(), $user, $pass, '' );
			}
		} elseif ( 'gmail_api' === $type ) {
			$conn                    = self::conn( 'gmail', 'api' );
			$conn['gmail_client_id'] = (string) $o->getClientId();
			$conn['api_key']         = (string) $o->getClientSecret();
		} elseif ( isset( $api[ $type ] ) ) {
			$conn            = self::conn( $api[ $type ][0], 'api' );
			$conn['api_key'] = $secret( $api[ $type ][1] );
			// getSendGridRegion() returns the API key constant when one is set,
			// so only trust it when the key came from the database.
			if ( 'sendgrid_api' === $type && ! defined( 'POST_SMTP_API_KEY' ) && 'EU' === strtoupper( (string) $o->getSendGridRegion() ) ) {
				$notes['region'] = __( 'This SendGrid account is on the EU region, which SendForce does not support yet. The key was copied, but sending through it will fail.', 'sendforce-mail-relay' );
			}
		} elseif ( 'mailgun_api' === $type ) {
			$conn                   = self::conn( 'mailgun', 'api' );
			$conn['api_key']        = $secret( 'getMailgunApiKey' );
			$conn['mailgun_domain'] = (string) $o->getMailgunDomainName();
			// Post SMTP stores the EU region as a ticked checkbox: any value means EU.
			$conn['mailgun_region'] = null !== $o->getMailgunRegion() ? 'eu' : 'us';
		} elseif ( 'mailjet_api' === $type ) {
			$conn             = self::conn( 'mailjet', 'api' );
			$conn['username'] = $secret( 'getMailjetApiKey' );    // Public key.
			$conn['api_key']  = $secret( 'getMailjetSecretKey' ); // Both read POST_SMTP_API_KEY when set.
		} elseif ( 'smtpcom_api' === $type ) {
			$conn                    = self::conn( 'smtpcom', 'api' );
			$conn['api_key']         = $secret( 'getSmtpcomApiKey' );
			$conn['smtpcom_channel'] = (string) $o->getSmtpcomChannel();
		}

		$items = array();
		if ( null === $conn ) {
			$items[] = self::unsupported( 'postsmtp:' . $type, $type, $label );
		} elseif ( '' !== $type ) {
			$items[] = self::item( 'postsmtp:' . $type, $conn, 'primary', $label, array_values( $notes ) );
		}

		// The fallback SMTP server, used when the main transport fails.
		if ( 'yes' === $o->getFallbackIsEnabled() && '' !== trim( (string) $o->getFallbackHostname() ) ) {
			$fb_notes = array();
			$fb_auth  = 'none' !== $o->getFallbackAuth();
			$fb_user  = (string) $o->getFallbackUsername();
			$fb_pass  = (string) $o->getFallbackPassword();
			if ( defined( 'POST_SMTP_FALLBACK_AUTH_PASSWORD' ) || defined( 'POST_SMTP_FALLBACK_AUTH_USERNAME' ) ) {
				$fb_user    = defined( 'POST_SMTP_FALLBACK_AUTH_USERNAME' ) ? '' : $fb_user;
				$fb_pass    = defined( 'POST_SMTP_FALLBACK_AUTH_PASSWORD' ) ? '' : $fb_pass;
				$fb_notes[] = $wpconfig_note;
			}
			$fb_conn = self::smtp_conn( $o->getFallbackHostname(), $o->getFallbackPort(), (string) $o->getFallbackSecurity(), $fb_auth, $fb_user, $fb_pass, '' );
			$items[] = self::item( 'postsmtp:fallback', $fb_conn, 'fallback', $label, $fb_notes );
		}

		return array(
			'items'  => $items,
			'sender' => array(
				'email' => (string) $o->getMessageSenderEmail(),
				'name'  => (string) $o->getMessageSenderName(),
				'force' => (bool) $o->isPluginSenderEmailEnforced(),
			),
		);
	}

	// ---------------------------------------------------------------------
	// SMTP Mailer
	// ---------------------------------------------------------------------

	/**
	 * SMTP Mailer keeps one SMTP server in one option, read through its own
	 * smtp_mailer_get_option(). The password is stored base64-encoded, and the
	 * plugin decodes it the same way when it sends.
	 *
	 * @return array
	 */
	private static function plan_smtp_mailer() {
		$o     = smtp_mailer_get_option();
		$o     = is_array( $o ) ? $o : array();
		$label = self::SOURCES['smtp-mailer'];
		$host  = trim( (string) ( $o['smtp_host'] ?? '' ) );
		$items = array();

		if ( '' !== $host ) {
			$auth  = 'true' === ( $o['smtp_auth'] ?? '' );
			$pass  = (string) base64_decode( (string) ( $o['smtp_password'] ?? '' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- SMTP Mailer stores the password base64-encoded.
			$conn  = self::smtp_conn( $host, $o['smtp_port'] ?? 587, (string) ( $o['type_of_encryption'] ?? 'tls' ), $auth, $auth ? (string) ( $o['smtp_username'] ?? '' ) : '', $auth ? $pass : '', '' );
			$notes = array();
			if ( ! empty( $o['disable_ssl_verification'] ) ) {
				$notes[] = __( 'SMTP Mailer was set to skip SSL certificate checks. SendForce always checks them, so sending fails if this server\'s certificate is invalid.', 'sendforce-mail-relay' );
			}
			$items[] = self::item( 'smtpmailer:smtp', $conn, 'primary', $label, $notes );
		}

		// "Force From Address" forces both; "Force From Email" only the address.
		$force_email = ! empty( $o['force_from_email'] ) || ! empty( $o['force_from_address'] );
		return array(
			'items'  => $items,
			'sender' => array(
				'email' => (string) ( $o['from_email'] ?? '' ),
				'name'  => (string) ( $o['from_name'] ?? '' ),
				'force' => $force_email,
			),
		);
	}

	// ---------------------------------------------------------------------
	// WP SMTP (Kadence Mail, formerly Solid Mail)
	// ---------------------------------------------------------------------

	/**
	 * WP SMTP can hold several connections. The default one sends anything
	 * that matches no other connection's From address; another active one
	 * sends the email whose From matches its own. Read through its own
	 * ProvidersRepository, which builds each connector with its fixed host,
	 * port and encryption.
	 *
	 * @return array
	 */
	private static function plan_wp_smtp() {
		$repo      = new \SolidWP\Mail\Repository\ProvidersRepository();
		$label     = self::SOURCES['wp-smtp'];
		$default   = $repo->get_default_provider();
		$def_from  = $default ? strtolower( trim( (string) $default->get_from_email() ) ) : '';
		// "Use other connections when the default fails" = a fallback.
		$use_other = false;
		if ( class_exists( '\SolidWP\Mail\Admin\SettingsScreen' ) ) {
			$wps_settings = ( new \SolidWP\Mail\Admin\SettingsScreen() )->get_settings();
			$use_other    = 'yes' === ( $wps_settings['use_unmatched_connections'] ?? 'no' );
		}
		$items     = array();
		$senders   = array();
		$hosts_api = array(
			'sendgrid' => 'sendgrid',
			'brevo'    => 'brevo',
			'mailgun'  => 'mailgun',
			'postmark' => 'postmark',
		);

		foreach ( $repo->get_all_providers() as $id => $c ) {
			$is_default = $default && $default->get_id() === $c->get_id();
			if ( ! $is_default && ! $c->is_active() ) {
				continue; // Switched off in WP SMTP: it never sends, so leave it behind.
			}

			$name = (string) $c->get_name();
			if ( 'postmark' === $name ) {
				// Sent through Postmark's API with the server token.
				$conn            = self::conn( 'postmark', 'api' );
				$conn['api_key'] = (string) $c->get_username();
			} else {
				$secure = (string) $c->get_secure();
				$conn   = self::smtp_conn(
					$c->get_host(),
					$c->get_port(),
					'' === $secure ? 'none' : $secure,
					$c->is_authentication(),
					$c->get_username(),
					$c->get_password(),
					$hosts_api[ $name ] ?? ''
				);
			}

			$from = strtolower( trim( (string) $c->get_from_email() ) );
			$role = $is_default ? 'primary' : 'extra';
			if ( ! $is_default && $use_other && ! in_array( 'fallback', wp_list_pluck( $items, 'role' ), true ) ) {
				$role = 'fallback'; // The first other active connection.
			}
			$item = self::item( 'wpsmtp:' . $id, $conn, $role, $label, array() );
			// Mail From this address went to this connection. Not when the
			// default shares the address: the default already sends that mail.
			if ( ! $is_default && is_email( $from ) && $from !== $def_from ) {
				$item['route_from'][] = $from;
			}
			$items[] = $item;

			if ( is_email( $from ) ) {
				$senders[ $from ] = (string) $c->get_from_name();
			}
		}

		// WP SMTP sends each email From its connection's own address. SendForce
		// has one Sender Identity for every email, so set it only when there is
		// a single address; with several, the original From is kept.
		$sender = array( 'email' => '', 'name' => '', 'force' => false );
		if ( 1 === count( $senders ) ) {
			$sender = array( 'email' => (string) key( $senders ), 'name' => (string) current( $senders ), 'force' => true );
		} elseif ( count( $senders ) > 1 && ! empty( $items ) && 'primary' === $items[0]['role'] ) {
			$items[0]['notes'][] = __( 'WP SMTP sent each connection\'s mail from that connection\'s own From address. SendForce uses one Sender Identity for all mail, so none was set: choose one in Settings → Sender Identity if your provider needs it.', 'sendforce-mail-relay' );
			$items[0]['status']  = 'needs_action';
		}

		// Primary first, then fallback, then the rest, as in the other importers.
		$rank = array( 'primary' => 0, 'fallback' => 1, 'extra' => 2 );
		usort( $items, function ( $a, $b ) use ( $rank ) {
			return $rank[ $a['role'] ] - $rank[ $b['role'] ];
		} );

		return array( 'items' => $items, 'sender' => $sender );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * An empty SendForce connection (same fields the connection form saves).
	 *
	 * @param string $provider SendForce provider slug.
	 * @param string $mode     smtp|api.
	 * @return array
	 */
	private static function conn( $provider, $mode ) {
		return array(
			'provider'             => $provider,
			'connection_type'      => $mode,
			'host'                 => '',
			'port'                 => 587,
			'encryption'           => 'tls',
			'auth'                 => true,
			'username'             => '',
			'password'             => '',
			'api_key'              => '',
			'ses_region'           => 'us-east-1',
			'ses_access_key'       => '',
			'mailgun_domain'       => '',
			'mailgun_region'       => 'us',
			'smtpcom_channel'      => '',
			'socketlabs_server_id' => '',
			'outlook_tenant_id'    => '',
			'outlook_client_id'    => '',
			'gmail_client_id'      => '',
			'oauth_email'          => '',
			'rate_limit'           => 0,
		);
	}

	/**
	 * An SMTP connection. When the host is a provider SendForce has a preset
	 * for (e.g. smtp.sendgrid.net), that provider is used so the connection
	 * shows the right name and logo; otherwise it's a generic "Other SMTP".
	 *
	 * @param string $host       Host.
	 * @param int    $port       Port.
	 * @param string $encryption none|ssl|tls.
	 * @param bool   $auth       Whether to authenticate.
	 * @param string $user       Username.
	 * @param string $pass       Password (plaintext).
	 * @param string $provider   Force a provider slug ('' = detect from host).
	 * @return array
	 */
	private static function smtp_conn( $host, $port, $encryption, $auth, $user, $pass, $provider ) {
		$host = strtolower( trim( (string) $host ) );
		if ( '' === $provider ) {
			$provider = 'other';
			foreach ( SendForce_Providers::get_providers() as $slug => $p ) {
				if ( ! empty( $p['host'] ) && strtolower( $p['host'] ) === $host ) {
					$provider = $slug;
					break;
				}
			}
		}
		$conn               = self::conn( $provider, 'smtp' );
		$conn['host']       = $host;
		$conn['port']       = absint( $port ) ? absint( $port ) : 587;
		$conn['encryption'] = in_array( $encryption, array( 'none', 'ssl', 'tls' ), true ) ? $encryption : 'tls';
		$conn['auth']       = (bool) $auth;
		$conn['username']   = (string) $user;
		$conn['password']   = (string) $pass;
		return $conn;
	}

	/**
	 * Wrap a connection as a plan item, adding status and notes.
	 *
	 * @param string $key    Stable import key.
	 * @param array  $conn   SendForce connection fields.
	 * @param string $role   primary|fallback|extra.
	 * @param string $source Source plugin name.
	 * @param array  $notes  Notes so far.
	 * @return array
	 */
	private static function item( $key, $conn, $role, $source, $notes ) {
		$provider       = $conn['provider'];
		$info           = SendForce_Providers::get_provider( $provider );
		$provider_label = $info ? $info['label'] : ucfirst( $provider );
		$status         = empty( $notes ) ? 'ok' : 'needs_action';

		// Only API mode signs in with OAuth; Gmail / Outlook over SMTP use an
		// app password, which imports like any other SMTP password.
		if ( 'api' === $conn['connection_type'] && in_array( $provider, self::OAUTH_PROVIDERS, true ) ) {
			$status  = 'needs_action';
			$notes[] = __( 'Sign-in must be redone: open this connection and click Connect. First add the redirect URL shown in that form to your Google or Microsoft app.', 'sendforce-mail-relay' );
		}

		if ( 'smtp' === $conn['connection_type'] && 'phpmail' !== $provider ) {
			$summary = $conn['host'] . ':' . $conn['port'] . ' (' . strtoupper( $conn['encryption'] ) . ')';
		} elseif ( 'phpmail' === $provider ) {
			$summary = __( 'Sends through the server\'s PHP mail()', 'sendforce-mail-relay' );
		} else {
			$summary = '' !== $conn['api_key'] ? __( 'API credentials found', 'sendforce-mail-relay' ) : __( 'API credentials missing', 'sendforce-mail-relay' );
		}

		return array(
			'key'            => $key,
			/* translators: 1: provider name, 2: source plugin name */
			'label'          => sprintf( __( '%1$s (from %2$s)', 'sendforce-mail-relay' ), $provider_label, $source ),
			'provider'       => $provider,
			'provider_label' => $provider_label,
			'mode'           => $conn['connection_type'],
			'role'           => $role,
			'status'         => $status,
			'notes'          => $notes,
			'summary'        => $summary,
			'conn'           => $conn,
			'route_from'     => array(),
		);
	}

	/**
	 * A plan item for a mailer SendForce has no equivalent for.
	 *
	 * @param string $key    Stable import key.
	 * @param string $mailer Source mailer id.
	 * @param string $source Source plugin name.
	 * @return array
	 */
	private static function unsupported( $key, $mailer, $source ) {
		$names = array(
			'zoho'           => 'Zoho Mail',
			'tosend'         => 'ToSend',
			'cloudflare'     => 'Cloudflare Email',
			// Post SMTP transports.
			'zohomail_api'   => 'Zoho Mail',
			'cloudflare_api' => 'Cloudflare Email',
			'office365_api'  => 'Microsoft 365 (Post SMTP Pro)',
			'mailtrap_api'   => 'Mailtrap',
			'sendpulse_api'  => 'SendPulse',
			'emailit_api'    => 'Emailit',
			'maileroo_api'   => 'Maileroo',
			'sweego_api'     => 'Sweego',
		);
		$name  = $names[ $mailer ] ?? ucfirst( (string) $mailer );
		return array(
			'key'            => $key,
			/* translators: 1: mailer name, 2: source plugin name */
			'label'          => sprintf( __( '%1$s (from %2$s)', 'sendforce-mail-relay' ), $name, $source ),
			'provider'       => '',
			'provider_label' => $name,
			'mode'           => '',
			'role'           => 'extra',
			'status'         => 'unsupported',
			'notes'          => array( __( 'SendForce has no equivalent for this mailer, so it can\'t be imported.', 'sendforce-mail-relay' ) ),
			'summary'        => '',
			'route_from'     => array(),
		);
	}

	/**
	 * Whether anything has already been imported from a given source, so the
	 * UI can offer "Check again" rather than "Import settings" a second time.
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function already_imported( $source ) {
		foreach ( (array) get_option( 'sendforce_connections', array() ) as $conn ) {
			if ( is_array( $conn ) && ! empty( $conn['imported_from'] ) && $source === $conn['imported_from'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Import keys of connections already imported, mapped to their SendForce id.
	 *
	 * @return array<string,string>
	 */
	private static function existing_import_keys() {
		$out = array();
		foreach ( (array) get_option( 'sendforce_connections', array() ) as $c ) {
			if ( is_array( $c ) && ! empty( $c['import_key'] ) && ! empty( $c['id'] ) ) {
				$out[ $c['import_key'] ] = $c['id'];
			}
		}
		return $out;
	}
}
