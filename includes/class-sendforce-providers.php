<?php
/**
 * SMTP provider presets.
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_Providers
 */
class SendForce_Providers {

	/**
	 * Get all available provider presets.
	 *
	 * @return array
	 */
	public static function get_providers() {
		return array(
			// --- Row 1: Defaults + Major Providers ---
			'phpmail'      => array(
				'label'         => __( 'PHP Mail', 'sendforce-mail-relay' ),
				'host'          => '',
				'port'          => '',
				'encryption'    => '',
				'icon'          => 'PHP',
				'color'         => '#8892BF',
				'api_supported' => false,
				'phpmail'       => true,
			),
			'other'        => array(
				'label'         => __( 'Other SMTP', 'sendforce-mail-relay' ),
				'host'          => '',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'SMTP',
				'color'         => '#4F6D7A',
				'api_supported' => false,
			),
			'gmail'        => array(
				'label'          => __( 'Google', 'sendforce-mail-relay' ),
				'host'           => 'smtp.gmail.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'G',
				'color'          => '#EA4335',
				'api_supported'  => true,
				'username_label' => __( 'Gmail Address', 'sendforce-mail-relay' ),
				'password_label' => __( 'App Password', 'sendforce-mail-relay' ),
				'username_help'  => __( 'Your full Gmail address (e.g. you@gmail.com). You must generate an App Password from your Google Account security settings.', 'sendforce-mail-relay' ),
				'api_fields'     => array( 'api_key', 'gmail_client_id' ),
				'api_key_label'  => __( 'Client Secret', 'sendforce-mail-relay' ),
			),
			'outlook'      => array(
				'label'          => __( 'Microsoft 365', 'sendforce-mail-relay' ),
				'host'           => 'smtp-mail.outlook.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'M',
				'color'          => '#0078D4',
				'api_supported'  => true,
				'api_fields'     => array( 'api_key', 'outlook_tenant_id', 'outlook_client_id' ),
				'api_key_label'  => __( 'Client Secret', 'sendforce-mail-relay' ),
			),
			'sendgrid'     => array(
				'label'            => __( 'SendGrid', 'sendforce-mail-relay' ),
				'host'             => 'smtp.sendgrid.net',
				'port'             => 587,
				'encryption'       => 'tls',
				'icon'             => 'SG',
				'color'            => '#1A82E2',
				'api_supported'    => true,
				'username_label'   => __( 'Username', 'sendforce-mail-relay' ),
				'username_default' => 'apikey',
				'username_help'    => __( 'SendGrid SMTP requires the literal word "apikey" as username (not your actual API key).', 'sendforce-mail-relay' ),
				'password_label'   => __( 'API Key', 'sendforce-mail-relay' ),
				'api_fields'       => array( 'api_key' ),
				'api_key_label'    => __( 'API Key', 'sendforce-mail-relay' ),
			),

			// --- Row 2: Popular Transactional Services ---
			'ses'          => array(
				'label'          => __( 'Amazon SES', 'sendforce-mail-relay' ),
				'host'           => 'email-smtp.us-east-1.amazonaws.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'aws',
				'color'          => '#FF9900',
				'api_supported'  => true,
				'username_label' => __( 'SMTP Username', 'sendforce-mail-relay' ),
				'password_label' => __( 'SMTP Password', 'sendforce-mail-relay' ),
				'has_region'     => true,
				'api_fields'     => array( 'api_key', 'ses_region' ),
				'api_key_label'  => __( 'Secret Access Key', 'sendforce-mail-relay' ),
				'api_key_help'   => __( 'Get this from AWS Console → IAM → Users → your user → Security credentials → Create access key. The Secret Access Key is shown ONCE at creation (40+ mixed-case characters with /, +, =), so copy it immediately. The Access Key ID (starts with AKIA, 20 chars) goes in the field above. The IAM user needs the policy "AmazonSESFullAccess" or at minimum permission "ses:SendEmail" / "ses:SendRawEmail" on the SES region you select. New AWS accounts start in the SES Sandbox — you can only send to verified email addresses until you request production access in the SES console.', 'sendforce-mail-relay' ),
				'api_extra'      => array(
					'username' => __( 'Access Key ID', 'sendforce-mail-relay' ),
				),
			),
			'brevo'        => array(
				'label'         => __( 'Brevo', 'sendforce-mail-relay' ),
				'host'          => 'smtp-relay.brevo.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'B',
				'color'         => '#0B996E',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'mailgun'      => array(
				'label'          => __( 'Mailgun', 'sendforce-mail-relay' ),
				'host'           => 'smtp.mailgun.org',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'MG',
				'color'          => '#F06B56',
				'api_supported'  => true,
				'api_fields'     => array( 'api_key', 'mailgun_domain', 'mailgun_region' ),
				'api_key_label'  => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'postmark'     => array(
				'label'          => __( 'Postmark', 'sendforce-mail-relay' ),
				'host'           => 'smtp.postmarkapp.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'PM',
				'color'          => '#FFDE00',
				'api_supported'  => true,
				'username_label' => __( 'Server Token (SMTP Username)', 'sendforce-mail-relay' ),
				'password_label' => __( 'Server Token (SMTP Password)', 'sendforce-mail-relay' ),
				'username_help'  => __( 'Enter your Postmark Server Token — use the same value for both username and password.', 'sendforce-mail-relay' ),
				'api_fields'     => array( 'api_key' ),
				'api_key_label'  => __( 'Server API Token', 'sendforce-mail-relay' ),
			),
			'sparkpost'    => array(
				'label'         => __( 'SparkPost', 'sendforce-mail-relay' ),
				'host'          => 'smtp.sparkpostmail.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'SP',
				'color'         => '#FA6423',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),

			// --- Row 3: More Popular Services ---
			'zoho'         => array(
				'label'             => __( 'Zoho ZeptoMail', 'sendforce-mail-relay' ),
				'host'              => 'smtp.zeptomail.com',
				'port'              => 587,
				'encryption'        => 'tls',
				'icon'              => 'Z',
				'color'             => '#E8413C',
				'api_supported'     => true,
				'api_fields'        => array( 'api_key' ),
				'api_key_label'     => __( 'Send Mail Token', 'sendforce-mail-relay' ),
				'api_key_help'      => __( 'Get this from ZeptoMail → Mail Agents → your agent → Setup Info → API tab → "Send Mail Token". Paste only the token itself (e.g. wSsVR60o+cz1XaH8m1Su…) — if you paste the full "Zoho-enczapikey TOKEN" line from the docs, SendForce will strip the prefix automatically. Make sure the From address domain is verified in your ZeptoMail account; unverified domains are rejected.', 'sendforce-mail-relay' ),
				'username_label'    => __( 'SMTP Username', 'sendforce-mail-relay' ),
				'username_default'  => 'emailapikey',
				'username_help'     => __( 'ZeptoMail SMTP requires the literal word "emailapikey" as username. Use your Send Mail Token as the password.', 'sendforce-mail-relay' ),
			),
			'elasticemail' => array(
				'label'         => __( 'Elastic Email', 'sendforce-mail-relay' ),
				'host'          => 'smtp.elasticemail.com',
				'port'          => 2525,
				'encryption'    => 'tls',
				'icon'          => 'EE',
				'color'         => '#3C5B96',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'sendlayer'    => array(
				'label'          => __( 'SendLayer', 'sendforce-mail-relay' ),
				'host'           => 'smtp.sendlayer.net',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'SL',
				'color'          => '#6C3DEB',
				'api_supported'  => true,
				'api_fields'     => array( 'api_key' ),
				'api_key_label'  => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'mailjet'      => array(
				'label'          => __( 'Mailjet', 'sendforce-mail-relay' ),
				'host'           => 'in-v3.mailjet.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'MJ',
				'color'          => '#9B59B6',
				'api_supported'  => true,
				'username_label' => __( 'API Key (Public)', 'sendforce-mail-relay' ),
				'password_label' => __( 'Secret Key (Private)', 'sendforce-mail-relay' ),
				'api_fields'     => array( 'api_key' ),
				'api_key_label'  => __( 'Secret Key (Private)', 'sendforce-mail-relay' ),
				'api_extra'      => array(
					'username' => __( 'API Key (Public)', 'sendforce-mail-relay' ),
				),
			),
			'mailersend'   => array(
				'label'          => __( 'MailerSend', 'sendforce-mail-relay' ),
				'host'           => 'smtp.mailersend.net',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'MS',
				'color'          => '#2F68E6',
				'api_supported'  => true,
				'api_fields'     => array( 'api_key' ),
				'api_key_label'  => __( 'API Token', 'sendforce-mail-relay' ),
			),

			// --- Row 4+: Less Common (hidden by default) ---
			'smtp2go'      => array(
				'label'         => __( 'SMTP2GO', 'sendforce-mail-relay' ),
				'host'          => 'mail.smtp2go.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'S2',
				'color'         => '#2B63C1',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'pepipost'     => array(
				'label'         => __( 'Netcore', 'sendforce-mail-relay' ),
				'host'          => 'smtp.pepipost.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'NC',
				'color'         => '#00C4B4',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'smtpcom'      => array(
				'label'         => __( 'SMTP.com', 'sendforce-mail-relay' ),
				'host'          => 'smtp.smtp.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'S',
				'color'         => '#1F3864',
				'api_supported' => true,
				'api_fields'    => array( 'api_key', 'smtpcom_channel' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'resend'       => array(
				'label'            => __( 'Resend', 'sendforce-mail-relay' ),
				'host'             => 'smtp.resend.com',
				'port'             => 465,
				'encryption'       => 'ssl',
				'icon'             => 'RE',
				'color'            => '#000000',
				'api_supported'    => true,
				'username_default' => 'resend',
				'username_label'   => __( 'Username', 'sendforce-mail-relay' ),
				'username_help'    => __( 'Resend SMTP requires the literal word "resend" as username.', 'sendforce-mail-relay' ),
				'password_label'   => __( 'API Key', 'sendforce-mail-relay' ),
				'api_fields'       => array( 'api_key' ),
				'api_key_label'    => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'mandrill'     => array(
				'label'          => __( 'Mandrill', 'sendforce-mail-relay' ),
				'host'           => 'smtp.mandrillapp.com',
				'port'           => 587,
				'encryption'     => 'tls',
				'icon'           => 'MD',
				'color'          => '#295C39',
				'api_supported'  => true,
				'username_label' => __( 'Mailchimp Email', 'sendforce-mail-relay' ),
				'password_label' => __( 'API Key', 'sendforce-mail-relay' ),
				'api_fields'     => array( 'api_key' ),
				'api_key_label'  => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'turbosmtp'    => array(
				'label'         => __( 'Turbo SMTP', 'sendforce-mail-relay' ),
				'host'          => 'pro.turbo-smtp.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'TS',
				'color'         => '#FF6600',
				'api_supported' => false,
			),
			'socketlabs'   => array(
				'label'         => __( 'SocketLabs', 'sendforce-mail-relay' ),
				'host'          => 'smtp.socketlabs.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'SL',
				'color'         => '#5C2D91',
				'api_supported' => true,
				'api_fields'    => array( 'api_key', 'socketlabs_server_id' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'moosend'      => array(
				'label'         => __( 'Moosend', 'sendforce-mail-relay' ),
				'host'          => 'smtp.moosend.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'MS',
				'color'         => '#4CAF50',
				'api_supported' => true,
				'api_fields'    => array( 'api_key' ),
				'api_key_label' => __( 'API Key', 'sendforce-mail-relay' ),
			),
			'yahoo'        => array(
				'label'         => __( 'Yahoo Mail', 'sendforce-mail-relay' ),
				'host'          => 'smtp.mail.yahoo.com',
				'port'          => 587,
				'encryption'    => 'tls',
				'icon'          => 'Y!',
				'color'         => '#720E9E',
				'api_supported' => false,
			),
		);
	}

	/**
	 * Get a single provider config.
	 *
	 * @param string $provider_key Provider key.
	 * @return array|null
	 */
	public static function get_provider( $provider_key ) {
		$providers = self::get_providers();
		return isset( $providers[ $provider_key ] ) ? $providers[ $provider_key ] : null;
	}

	/**
	 * Get Amazon SES regions.
	 *
	 * @return array
	 */
	public static function get_ses_regions() {
		return array(
			'us-east-1'      => __( 'US East (N. Virginia)', 'sendforce-mail-relay' ),
			'us-east-2'      => __( 'US East (Ohio)', 'sendforce-mail-relay' ),
			'us-west-1'      => __( 'US West (N. California)', 'sendforce-mail-relay' ),
			'us-west-2'      => __( 'US West (Oregon)', 'sendforce-mail-relay' ),
			'eu-west-1'      => __( 'Europe (Ireland)', 'sendforce-mail-relay' ),
			'eu-west-2'      => __( 'Europe (London)', 'sendforce-mail-relay' ),
			'eu-central-1'   => __( 'Europe (Frankfurt)', 'sendforce-mail-relay' ),
			'ap-south-1'     => __( 'Asia Pacific (Mumbai)', 'sendforce-mail-relay' ),
			'ap-southeast-1' => __( 'Asia Pacific (Singapore)', 'sendforce-mail-relay' ),
			'ap-southeast-2' => __( 'Asia Pacific (Sydney)', 'sendforce-mail-relay' ),
			'ap-northeast-1' => __( 'Asia Pacific (Tokyo)', 'sendforce-mail-relay' ),
			'ca-central-1'   => __( 'Canada (Central)', 'sendforce-mail-relay' ),
			'sa-east-1'      => __( 'South America (Sao Paulo)', 'sendforce-mail-relay' ),
		);
	}
}
