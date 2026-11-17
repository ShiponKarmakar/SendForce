<?php
/**
 * Plugin deactivator.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Deactivator
 *
 * Handles plugin deactivation tasks.
 */
class SendForce_Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'sendforce_process_queue' );
		wp_clear_scheduled_hook( 'sendforce_cleanup_logs' );
		wp_clear_scheduled_hook( 'sendforce_send_daily_summary' );
	}
}
