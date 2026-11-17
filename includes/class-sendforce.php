<?php
/**
 * Main plugin class.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce
 *
 * Orchestrates all plugin components.
 */
class SendForce {

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register privacy policy content.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Initialize mailer (SMTP configuration).
		$mailer = new SendForce_Mailer();
		$mailer->init();

		// Initialize logger.
		$logger = new SendForce_Logger();
		$logger->init();

		// Initialize queue.
		$queue = new SendForce_Queue();
		$queue->init();

		// Initialize alerts.
		$alerts = new SendForce_Alerts();
		$alerts->init();

		// Initialize OAuth handler (Gmail / Microsoft 365 connect-button flow).
		$oauth = new SendForce_OAuth();
		$oauth->init();

		// Initialize admin.
		if ( is_admin() ) {
			$admin = new SendForce_Admin();
			$admin->init();
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * Note: Since WP 4.6, WordPress automatically loads translations for
	 * plugins hosted on wordpress.org. This is kept for local/custom installs.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'sendforce-mail-relay', false, dirname( SENDFORCE_PLUGIN_BASENAME ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}

	/**
	 * Register privacy policy content.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$settings = get_option( 'sendforce_settings', array() );

		$content  = '<h2>' . esc_html__( 'SendForce Mail Relay - Email Logging', 'sendforce-mail-relay' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'SendForce Mail Relay may store the following data in your WordPress database:', 'sendforce-mail-relay' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Recipient email address, subject line, and email headers of all outgoing emails.', 'sendforce-mail-relay' ) . '</li>';

		if ( ! empty( $settings['log_email_body'] ) ) {
			$content .= '<li>' . esc_html__( 'Email body content (currently enabled — can be disabled in SendForce Mail Relay Settings).', 'sendforce-mail-relay' ) . '</li>';
		}

		$content .= '<li>' . esc_html__( 'Send status (sent or failed), error messages, and timestamp.', 'sendforce-mail-relay' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . sprintf(
			/* translators: %d: number of days */
			esc_html__( 'Log entries are automatically deleted after %d days. All data is permanently removed when the plugin is uninstalled.', 'sendforce-mail-relay' ),
			absint( $settings['log_retention_days'] ?? 30 )
		) . '</p>';
		$content .= '<p>' . esc_html__( 'When you send emails via a third-party provider (e.g. SendGrid, Mailgun, Amazon SES), your email data is transmitted to that provider subject to their own privacy policy.', 'sendforce-mail-relay' ) . '</p>';

		wp_add_privacy_policy_content( 'SendForce Mail Relay', wp_kses_post( $content ) );
	}
}
