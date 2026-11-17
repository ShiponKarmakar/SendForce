<?php
/**
 * Uninstall SendForce Mail Relay.
 *
 * Fired when the plugin is uninstalled. Removes all plugin data — on a
 * Multisite network, from every site.
 *
 * @package SendForce
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the plugin's per-site data (tables, options, transients, cron
 * events) from the current site. On Multisite, call it after switch_to_blog():
 * $wpdb->prefix, $wpdb->options and the cron array all follow the switch.
 */
function sendforce_uninstall_site() {
	global $wpdb;

	// Drop custom tables (new and legacy names).
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sendforce_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sendforce_queue" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sendforce_routes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smtpflow_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smtpflow_queue" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Delete options (new and legacy names).
	delete_option( 'sendforce_settings' );
	delete_option( 'sendforce_connections' );
	delete_option( 'sendforce_db_version' );
	delete_option( 'sendforce_alerts' );
	delete_option( 'smtpflow_settings' );
	delete_option( 'smtpflow_connections' );
	delete_option( 'smtpflow_db_version' );
	delete_option( 'smtpflow_alerts' );
	delete_option( 'sendforce_conflict_dismissed' ); // Legacy — now uses transients.
	delete_option( 'sendforce_enc_key' );           // Credential encryption secret.
	delete_option( 'sendforce_enc_key_migrated' );
	delete_option( 'sendforce_routing_enabled' );   // Smart Routing master switch.
	delete_option( 'sendforce_setup_lock' );        // Per-site setup lock (normally already gone).
	delete_option( 'sendforce_setup_done' );        // "Setup screen has been shown" flag (1.2.0).

	// Delete transients.
	delete_transient( 'sendforce_broken_credentials' );

	// Sweep every transient this plugin owns, rather than naming them one by
	// one: the list drifted before (the dashboard-stats, daily-stats and
	// alert-throttle caches were all left behind). Everything we set is
	// prefixed `sendforce_`, so one pattern covers current and future keys.
	// `smm_conflict_dismissed_*` is the pre-rename legacy prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_sendforce\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_sendforce\\_%'
		    OR option_name LIKE '\\_transient\\_smm\\_conflict\\_dismissed\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_smm\\_conflict\\_dismissed\\_%'"
	);

	// Clear scheduled events (new and legacy names).
	wp_clear_scheduled_hook( 'sendforce_process_queue' );
	wp_clear_scheduled_hook( 'sendforce_cleanup_logs' );
	wp_clear_scheduled_hook( 'sendforce_send_daily_summary' );
	wp_clear_scheduled_hook( 'smtpflow_process_queue' );
	wp_clear_scheduled_hook( 'smtpflow_cleanup_logs' );
	wp_clear_scheduled_hook( 'smtpflow_send_daily_summary' );
}

if ( is_multisite() ) {
	// Every site may have its own tables and settings: a network-activated
	// plugin sets each site up on its first request. 'number' => 0 = no limit.
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $sendforce_site_id ) {
		switch_to_blog( $sendforce_site_id );
		sendforce_uninstall_site();
		restore_current_blog();
	}
} else {
	sendforce_uninstall_site();
}

// User meta is shared by the whole network, so it is removed once, outside
// the per-site loop (welcome-notice + conflict-notice dismissals).
delete_metadata( 'user', 0, 'sendforce_welcome_seen_version', '', true );
delete_metadata( 'user', 0, 'sendforce_conflict_dismissed', '', true );
