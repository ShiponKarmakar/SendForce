# SendForce Mail Relay

Reliable SMTP and email-API delivery for WordPress, with email logging, a send queue with retry, smart routing between connections, and failure alerts.

- **WordPress.org:** https://wordpress.org/plugins/sendforce-mail-relay/
- **Live demo:** [open in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/sendforce-mail-relay/blueprint.json), which runs in your browser with nothing to install. Simulation Mode is on, so no email is sent.

## Providers

Any SMTP server, PHP Mail, or the native API of: Amazon SES, Brevo, Elastic Email, Gmail, MailerSend, Mailgun, Mailjet, Mandrill, Microsoft 365, Moosend, Pepipost/Netcore, Postmark, Resend, SendGrid, SendLayer, SMTP.com, SMTP2GO, SocketLabs, SparkPost and Zoho ZeptoMail.

## Features

- Email log, plus a send queue with automatic retry
- Smart Routing: send different mail through different connections
- Fallback connection when the primary fails
- Failure alerts by email, Telegram, Slack, Discord, Microsoft Teams, Google Chat, Pushover or a custom webhook
- One-click import from WP Mail SMTP, FluentSMTP, Easy WP SMTP, Post SMTP, SMTP Mailer and WP SMTP (Kadence Mail)
- Simulation Mode for staging sites
- WP-CLI commands and Multisite support

## Requirements

WordPress 5.9 or later and PHP 7.4 or later.

## Installation

Install from **Plugins → Add New** in WordPress by searching for "SendForce Mail Relay", or download the latest release from WordPress.org.

## Changelog

The full history is in [CHANGELOG.md](CHANGELOG.md). The WordPress.org listing in [readme.txt](readme.txt) carries a condensed version.

## License

GPLv2 or later. See [license.txt](license.txt).
