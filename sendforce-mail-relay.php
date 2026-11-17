<?php
/**
 * Plugin Name: SendForce Mail Relay
 * Plugin URI:  https://wordpress.org/plugins/sendforce-mail-relay/
 * Description: SMTP and email-API mailer for WordPress, with email logging, queue, retry, smart routing and failure alerts.
 * Version:     1.2.3
 * Author:      Shipon Karmakar
 * Author URI:  https://profiles.wordpress.org/shiponkarmakar/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sendforce-mail-relay
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'SENDFORCE_VERSION', '1.2.3' );
define( 'SENDFORCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SENDFORCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SENDFORCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload plugin classes.
 *
 * @param string $class_name The class name to load.
 */
function sendforce_autoload( $class_name ) {
	// Only handle our classes.
	if ( 0 !== strpos( $class_name, 'SendForce_' ) && 'SendForce' !== $class_name ) {
		return;
	}

	// Convert class name to file name.
	$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	// Check includes directory.
	$includes_path = SENDFORCE_PLUGIN_DIR . 'includes/' . $file_name;
	if ( file_exists( $includes_path ) ) {
		require_once $includes_path;
		return;
	}

	// Check includes/api directory.
	$api_path = SENDFORCE_PLUGIN_DIR . 'includes/api/' . $file_name;
	if ( file_exists( $api_path ) ) {
		require_once $api_path;
		return;
	}

	// Check admin directory.
	$admin_path = SENDFORCE_PLUGIN_DIR . 'admin/' . $file_name;
	if ( file_exists( $admin_path ) ) {
		require_once $admin_path;
		return;
	}
}
spl_autoload_register( 'sendforce_autoload' );

/**
 * Plugin activation.
 */
function sendforce_activate( $network_wide = false ) {
	SendForce_Activator::activate();

	// Offer the Setup screen once, to the admin who just activated the plugin.
	// Not on a network activation (that admin is in the network dashboard and
	// the settings are per site), and not when the site has been set up before,
	// so reactivating after an update never hijacks the page.
	if ( $network_wide || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$connections = (array) get_option( 'sendforce_connections', array() );
	if ( ! empty( $connections ) || get_option( 'sendforce_setup_done' ) ) {
		return;
	}
	set_transient( 'sendforce_setup_redirect_' . get_current_user_id(), 1, 60 );
}
register_activation_hook( __FILE__, 'sendforce_activate' );

/**
 * Send the admin who just activated SendForce to the Setup screen, once.
 *
 * WordPress activates several plugins in one request when you use the bulk
 * action (it adds `activate-multi`); redirecting then would strand the other
 * activations, so that case is skipped and the transient simply expires.
 */
function sendforce_maybe_setup_redirect() {
	if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$key = 'sendforce_setup_redirect_' . get_current_user_id();
	if ( ! get_transient( $key ) ) {
		return;
	}
	delete_transient( $key );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a core flag, no state change.
	if ( isset( $_GET['activate-multi'] ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=sendforce-mail-relay&tab=setup' ) );
	exit;
}
add_action( 'admin_init', 'sendforce_maybe_setup_redirect' );

/**
 * Plugin deactivation.
 *
 * Cron events live in each site's own options, so a Network Deactivate has to
 * clear them on every site, not just the main one. (Activation needs no such
 * loop — each site sets itself up on its next request; see
 * sendforce_maybe_setup_site().)
 *
 * @param bool $network_wide Whether this is a Network Deactivate.
 */
function sendforce_deactivate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $sendforce_site_id ) {
			switch_to_blog( $sendforce_site_id );
			SendForce_Deactivator::deactivate();
			restore_current_blog();
		}
		return;
	}
	SendForce_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'sendforce_deactivate' );

/**
 * Run one-time data migration from old smtpflow_* naming on first load after update.
 */
function sendforce_maybe_migrate() {
	if ( get_option( 'smtpflow_settings' ) || get_option( 'smtpflow_connections' ) || get_option( 'smtpflow_alerts' ) ) {
		SendForce_Activator::migrate_from_old_version();
	}
}
add_action( 'plugins_loaded', 'sendforce_maybe_migrate', 1 );

/**
 * Initialize the plugin.
 */
function sendforce_init() {
	$plugin = new SendForce();
	$plugin->run();
}
add_action( 'plugins_loaded', 'sendforce_init' );

/**
 * Set up the current site the first time the plugin runs on it, and again
 * once after each plugin update (tables, default options, cron events).
 *
 * This covers three cases the activation hook can't:
 *  - plugin updates (the activation hook only fires on activate);
 *  - the other sites of a Multisite network after Network Activate (the hook
 *    only fires on the main site);
 *  - Multisite sites created after the plugin was network-activated.
 *
 * It runs on every kind of request, not just admin: on a network, a sub-site's
 * first visitor or cron run may arrive before any admin does, and without its
 * tables that site's emails can't be logged or queued. The cost is one
 * autoloaded option read per request; the setup itself runs once per site per
 * version, so visitors can't trigger it repeatedly.
 */
function sendforce_maybe_setup_site() {
	if ( SENDFORCE_VERSION === get_option( 'sendforce_db_version' ) ) {
		return;
	}

	// Several first visits can arrive at once. Only one may run setup, or
	// they could each schedule the same cron events.
	if ( ! sendforce_acquire_setup_lock() ) {
		return; // Another request is setting this site up right now.
	}

	// This request read its options when it started, so another request may
	// have finished setup in the meantime. Re-read from the database (the
	// cron array included) before doing any work.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( 'sendforce_db_version', 'options' );
	if ( SENDFORCE_VERSION !== get_option( 'sendforce_db_version' ) ) {
		SendForce_Activator::setup_site();
	}

	sendforce_release_setup_lock();
}
add_action( 'plugins_loaded', 'sendforce_maybe_setup_site', 5 );

/**
 * Take the per-site setup lock. Returns true if this request now holds it.
 *
 * add_option() can't be used as a lock: it is an INSERT ... ON DUPLICATE KEY
 * UPDATE, so it overwrites an existing row instead of failing. INSERT IGNORE
 * on the unique option_name inserts the row for exactly one request. A lock
 * older than a minute belongs to a request that died mid-setup; it is taken
 * over with a compare-and-swap so only one waiting request wins.
 *
 * @return bool
 */
function sendforce_acquire_setup_lock() {
	global $wpdb;
	$now = time();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic lock; the Options API has no insert-if-absent.
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('sendforce_setup_lock', %s, 'no')", $now ) );
	if ( 1 === (int) $inserted ) {
		return true;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must bypass the options cache.
	$held = (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'sendforce_setup_lock'" );
	if ( (int) $held > $now - MINUTE_IN_SECONDS ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- compare-and-swap takeover of a stale lock.
	$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'sendforce_setup_lock' AND option_value = %s", $now, $held ) );
	return 1 === (int) $taken;
}

/**
 * Release the per-site setup lock.
 */
function sendforce_release_setup_lock() {
	global $wpdb;
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'sendforce_setup_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_delete( 'sendforce_setup_lock', 'options' );
}

/**
 * When a Multisite site is deleted, drop its SendForce tables along with
 * WordPress's own (core only drops the tables it knows about).
 *
 * @param string[] $tables  Table names core will drop.
 * @param int      $site_id ID of the site being deleted.
 * @return string[]
 */
function sendforce_drop_site_tables( $tables, $site_id ) {
	global $wpdb;
	$prefix = $wpdb->get_blog_prefix( $site_id );
	foreach ( array( 'logs', 'queue', 'routes' ) as $table ) {
		$tables[] = $prefix . 'sendforce_' . $table;
	}
	return $tables;
}
add_filter( 'wpmu_drop_tables', 'sendforce_drop_site_tables', 10, 2 );

/**
 * One-time migration (1.0.37): re-encrypt stored credentials with the stable
 * `sendforce_enc_key` secret instead of the rotatable wp_salt('auth') key.
 *
 * Before 1.0.37 the encryption key was derived purely from the site's auth
 * salt, so anything that rotated salts — a security plugin's "regenerate
 * security keys", a host migration, a staging push — silently destroyed every
 * saved password and API key. Users experienced this as "updating the plugin
 * wiped my SMTP settings".
 *
 * This runs while the legacy key still works and rewrites each secret under
 * the stable key, after which salt rotation is harmless. Values that cannot be
 * decrypted are left untouched rather than overwritten, so a partially broken
 * install never loses data it might still recover.
 */
function sendforce_maybe_migrate_encryption_keys() {
	if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	if ( get_option( 'sendforce_enc_key_migrated' ) ) {
		return;
	}
	if ( ! class_exists( 'SendForce_Mailer' ) ) {
		return;
	}

	$connections = get_option( 'sendforce_connections', array() );
	if ( ! is_array( $connections ) ) {
		update_option( 'sendforce_enc_key_migrated', '1', false );
		return;
	}

	$changed = false;
	foreach ( $connections as $i => $conn ) {
		if ( ! is_array( $conn ) ) {
			continue;
		}
		foreach ( array( 'password', 'api_key' ) as $field ) {
			$stored = (string) ( $conn[ $field ] ?? '' );
			if ( '' === $stored ) {
				continue;
			}
			SendForce_Mailer::$last_decrypt_failed = false;
			$plain                                 = SendForce_Mailer::decrypt_password( $stored );
			// Skip unrecoverable values — overwriting them would discard the
			// only copy of a secret the user might still be able to salvage.
			if ( SendForce_Mailer::$last_decrypt_failed || '' === $plain ) {
				continue;
			}
			$reencrypted = SendForce_Mailer::encrypt_password( $plain );
			if ( '' !== $reencrypted && $reencrypted !== $stored ) {
				$connections[ $i ][ $field ] = $reencrypted;
				$changed                     = true;
			}
		}
	}

	if ( $changed ) {
		update_option( 'sendforce_connections', $connections );
	}
	update_option( 'sendforce_enc_key_migrated', '1', false );
}
add_action( 'plugins_loaded', 'sendforce_maybe_migrate_encryption_keys', 6 );

// Register WP-CLI commands when running in CLI context.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SENDFORCE_PLUGIN_DIR . 'includes/class-sendforce-cli.php';
}
