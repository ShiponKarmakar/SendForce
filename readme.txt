=== SendForce Mail SMTP Relay - SMTP and Email API ===
Contributors: shiponkarmakar
Donate link: https://profiles.wordpress.org/shiponkarmakar/
Tags: email, email log, gmail, outlook, smtp
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reliable SMTP and email-API delivery for WordPress, with email logging, queue, retry, smart routing and failure alerts.

== Description ==

**SendForce Mail Relay is a free, open-source WordPress SMTP and email-API plugin** that fixes the "WordPress emails not being delivered" problem. Send WooCommerce order emails, Contact Form 7 / WPForms / Gravity Forms submissions, password resets, registration confirmations, and any other `wp_mail()` traffic through Amazon SES, SendGrid, Mailgun, Brevo (Sendinblue), Postmark, Zoho ZeptoMail, Gmail API, Microsoft 365, or any standard SMTP server — with full email logging, queue, retry, and failure alerts.

= Try the live demo =

Launch a full WordPress site with SendForce Mail Relay already set up — no install, nothing to sign up for. It runs in your browser on WordPress Playground, preloaded with two example connections, a Smart Routing rule and a week of email history. Simulation Mode is on, so no email is ever sent.

https://playground.wordpress.net/?blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/sendforce-mail-relay/blueprint.json

= Video overview =

https://youtu.be/dUv4HolX5dk

= Why SendForce Mail Relay =

* **Free and open-source.** Every feature is included — no premium tier, no email-volume cap, no provider paywall.
* **24+ providers, one plugin.** Built-in presets and HTTP API integrations for major transactional email services — pick what fits your budget, deliverability needs, and region.
* **Built-in debugging.** Live SMTP debug log on test email, raw API request/response capture in the email log, "Copy Debug Info" button for support tickets, and WP-CLI commands. Designed for developers and agencies who need to diagnose email problems fast.
* **Multiple connections with automatic fallback.** Configure a primary connection (e.g. Amazon SES) plus a fallback (e.g. Mailgun). If the primary returns an error, the fallback takes over — no email lost.

= Supported email providers =

**HTTP API sending (recommended — faster and more reliable than SMTP):**

* Amazon SES (AWS Simple Email Service)
* SendGrid (by Twilio)
* Mailgun (by Sinch)
* Brevo (formerly Sendinblue)
* SparkPost
* Postmark
* Zoho ZeptoMail
* Microsoft 365 / Outlook (Microsoft Graph API)
* Gmail / Google Workspace (Gmail API)
* SMTP2GO
* SMTP.com
* Elastic Email
* SocketLabs
* Moosend
* SendLayer
* Mailjet
* MailerSend
* Mandrill (by Mailchimp)
* Resend
* Netcore Email API (formerly Pepipost)

**SMTP sending (works with any provider, including custom servers):**

* Gmail / Google Workspace SMTP (smtp.gmail.com)
* Microsoft 365 / Outlook SMTP (smtp.office365.com)
* Yahoo Mail SMTP
* Zoho Mail SMTP
* Turbo SMTP
* Any other SMTP server (host, port, username, password)

= Features =

* **Multiple connections with automatic fallback** — configure a primary connection (e.g. Amazon SES) plus a fallback (e.g. Mailgun, SMTP, or PHP mail). If the primary returns an error, the fallback is attempted automatically. Both attempts are logged so you have a full audit trail; recovered emails carry a "Recovered via fallback" badge in the log detail view.
* **Smart Routing Rules** — send different emails through different connections based on rules you define. Match by recipient domain, recipient pattern (glob), sender email or domain, source plugin (auto-detected for WooCommerce, Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms, Forminator, Easy Digital Downloads, MemberPress, LearnDash, MailPoet, BuddyPress, bbPress and more), subject contains, time of day, or weekday. Rules are evaluated top-down (first match wins) and send through the connection you choose (including a PHP Mail connection). Includes inline rule editor, master enable/disable toggle, "Test Routing" tool, and a "via rule:" badge on the Email Log so you can see exactly which rule fired for any send.
* **One-click provider presets** — pre-filled host, port, encryption, and API endpoint for every supported provider. Just paste your API key or password.
* **Email Log** — every outgoing email logged with status, recipient, subject, headers, error message, and timestamp. Filter by status, search by keyword, filter by date range, export to CSV or JSON.
* **Email Queue with retry and per-connection pacing** — queue mode prevents rate-limit errors and timeouts on bulk sends. Set a "Max emails per minute" on each connection (e.g. a slow pace for Gmail API, a fast one for Amazon SES). Failed emails auto-retry on exponential backoff (5 min → 15 min → 45 min).
* **Email Test page** — send a test email with live SMTP debug log output. Diagnose connection problems instantly.
* **Failure alerts** — push delivery failures to Telegram, Slack, Discord, Microsoft Teams, Google Chat, Pushover, or any custom webhook.
* **Sender Identity override** — force every email to use a custom From name and From address site-wide.
* **Conflict detection** — warns you if another SMTP plugin (WP Mail SMTP, FluentSMTP, Easy WP SMTP, Post SMTP, etc.) is active so you can deactivate it before things break.
* **Import from WP Mail SMTP, FluentSMTP, Easy WP SMTP, Post SMTP, SMTP Mailer and WP SMTP** — switch in one click: connections, credentials, sender identity, fallback and per-sender routing (FluentSMTP, WP SMTP) come across, with a preview first and nothing in SendForce overwritten.
* **Encrypted credential storage** — SMTP passwords and API keys are encrypted at rest using AES-256-CBC with a dedicated per-site key, so they survive security-key/salt regeneration.
* **WP-CLI commands** — send test emails, process and inspect the queue, view or clean up the email log, and export system info from the command line. See the FAQ for the full list.
* **Developer hooks** — `sendforce_before_send`, `sendforce_after_send`, `sendforce_log_entry`, `sendforce_api_request`, `sendforce_api_response` for extending the plugin without forking.
* **`WP_DEBUG_LOG` integration** — optional mirroring of SMTP and API debug output to `debug.log`.
* **Automatic log cleanup** — configurable retention period (default 30 days).
* **Privacy-friendly** — optional disable of email-body logging for GDPR / privacy compliance.
* **WordPress Multisite** — Network Activate once and every site sets itself up automatically, including sites added later. Each site has its own connections, logs and queue; deleting a site or uninstalling the plugin cleans up every site's data.

= Works with all major WordPress plugins =

SendForce Mail Relay hooks into WordPress's core `wp_mail()` function, so it works with **every** plugin that sends email — including WooCommerce, Easy Digital Downloads, Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms, Forminator, MemberPress, LearnDash, BuddyPress, bbPress, MailPoet (transactional), and any custom plugin or theme that uses `wp_mail()`.

== Installation ==

1. Upload the `sendforce-mail-relay` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin installer.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **SendForce Mail Relay > Settings** to configure your SMTP server or API credentials.
4. Select your email provider from the provider grid.
5. Enter your credentials and click **Save Settings**.
6. Go to **SendForce Mail Relay > Email Test** and send a test email to verify your configuration.

== Frequently Asked Questions ==

= Do I need an SMTP server or API account? =

Yes. You need access to an SMTP server or a transactional email provider account. Most providers (Gmail, Outlook, etc.) offer SMTP access. Transactional services like SendGrid, Mailgun, or Amazon SES offer both SMTP and HTTP API access.

= Is my SMTP password or API key stored securely? =

Yes. All credentials are encrypted using AES-256-CBC encryption before being stored in the database. The encryption key is a dedicated random secret generated once for your site, so regenerating your WordPress security keys/salts (via a security plugin, host migration or site clone) no longer invalidates your saved passwords.

= What does the email queue do? =

When enabled, emails are stored in a database queue instead of being sent immediately. A WP-Cron job processes the queue every minute by default (you can choose every 1, 5, 10, 15 or 30 minutes in Settings), sending emails in batches; each run stops after about 20 seconds so it stays within typical host PHP time limits. You can also set a "Max emails per minute" on each connection to pace sending for providers with strict rate limits, such as the Gmail API. Failed emails are retried with exponential backoff (5 min → 15 min → 45 min).

Tip: WP-Cron only runs when your site receives visits, so on low-traffic sites the queue can lag. For reliable timing, disable WP-Cron and trigger it from a real server cron job every minute.

= Will SendForce Mail Relay send ALL WordPress emails including WooCommerce? =

Yes. SendForce Mail Relay hooks into WordPress's `phpmailer_init` action (SMTP mode) or `pre_wp_mail` filter (API mode). Since every plugin — including WooCommerce, contact form plugins, and membership plugins — sends email via WordPress's `wp_mail()` function, SendForce Mail Relay will handle all of your outgoing emails.

= Will this plugin conflict with other SMTP plugins? =

You should only use one SMTP plugin at a time. Please deactivate any other SMTP or email plugins (FluentSMTP, WP Mail SMTP, etc.) before activating SendForce Mail Relay.

= What data is logged? =

By default, SendForce Mail Relay logs: recipient email, subject, headers, status (sent/failed), error message if any, and timestamp. Email body logging is optional and can be disabled in Settings > Queue & Logging for privacy compliance.

= What happens to my data when I uninstall the plugin? =

All plugin data (database tables, settings, and scheduled events) is completely removed when you delete the plugin through the WordPress admin. On a Multisite network, this happens on every site.

= Which From address do my emails use? =

If you set a From email in Settings → Sender Identity, every email uses it, on every connection and every Smart Routing rule. If you leave it empty, each email keeps the From address the sending plugin chose (for example WooCommerce's store address); emails that don't set one use your site's admin email. Smart Routing never changes the From — it only picks which connection sends. Gmail and Microsoft 365 API connections always send as the connected account. Your email provider must accept the From you use: most only allow addresses on domains you've verified with them.

= Can I import my settings from another SMTP plugin? =

Yes, from six plugins. While the other plugin is still active, click **Import settings** — in the notice SendForce shows, or any time under **SendForce → Settings → Import from another plugin** (still there after you dismiss the notice). There is also a WP-CLI command, `wp sendforce import run <source>`, which is handy for staging sites and bulk work. On Multisite you import per site, and if WP Mail SMTP or Easy WP SMTP is set to one network-wide configuration, only a network administrator can import it. Supported: WP Mail SMTP, FluentSMTP, Easy WP SMTP, Post SMTP, SMTP Mailer and WP SMTP (now called Kadence Mail). It previews exactly what will come across, then adds the connections (credentials re-encrypted with SendForce's own key), sender identity and fallback; per-sender routing from FluentSMTP and WP SMTP becomes Smart Routing rules. Mailers SendForce doesn't support are listed in the preview and left out. Nothing already in SendForce is overwritten, and importing twice doesn't create duplicates. Gmail and Microsoft 365 API connections need one extra step: click Connect in SendForce to sign in again (Gmail or Outlook over SMTP with an app password imports as-is). If you used Easy WP SMTP's One-Click Setup, there are no app credentials saved on your site to copy, so SendForce asks you to create your own Google or Microsoft app. Credentials you keep in wp-config.php are not copied into the database — you'll be asked to enter them. Send a test email from SendForce, then deactivate the other plugin.

= Does it support WP-CLI? =

Yes. All commands live under `wp sendforce`:

* `wp sendforce test you@example.com` — send a test email (optional `--subject=` and `--message=`)
* `wp sendforce queue process` — process the email queue now
* `wp sendforce queue stats` — show queue counts by status
* `wp sendforce queue clear --status=failed` — clear queued emails (asks for confirmation)
* `wp sendforce log list --limit=50` — list recent log entries
* `wp sendforce log tail --status=failed` — show the latest failures
* `wp sendforce log count` — count log entries
* `wp sendforce log delete --before=2026-01-01` — delete old log entries (asks for confirmation)
* `wp sendforce import list` — show which SMTP plugins can be imported from
* `wp sendforce import preview easy-wp-smtp` — show what would be imported, without changing anything
* `wp sendforce import run fluent-smtp` — import it (asks for confirmation; add `--yes` to skip)
* `wp sendforce sysinfo` — print system info for support tickets

On Multisite, add `--url=` to choose the site. Running `wp sendforce queue process` from a server cron job every minute is a reliable way to process the queue without depending on site visits.

= Does it work on WordPress Multisite? =

Yes. Network Activate the plugin and each site sets itself up on its first visit, including sites created later. Every site is configured separately, with its own connections, sender identity, logs, queue and routing rules; there is no shared network-wide connection yet. When you delete a site, its SendForce data is removed with it. Importing from another SMTP plugin also works per site; if WP Mail SMTP is configured network-wide, only a network administrator can import those settings, since they belong to the whole network. If you use a real server cron instead of WP-Cron, make sure it runs cron for every site, not just the main site, so each site's queue and log cleanup run on time.

= Is there a demo I can try before installing? =

Yes. The [live demo](https://playground.wordpress.net/?blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/sendforce-mail-relay/blueprint.json) runs a real WordPress site in your browser through WordPress Playground, with SendForce already configured. It comes with two sample connections (Amazon SES and Mailgun), a Smart Routing rule and 38 logged emails, so you can explore the dashboard, logs and routing straight away. Simulation Mode is enabled, so nothing is actually sent. The demo runs the version currently published here, and nothing you do in it touches your own site.

= Does this plugin track me or phone home? =

No. SendForce Mail Relay does not collect any data, make any analytics calls, or communicate with any external server except when sending emails through your configured provider.

== External Services ==

SendForce Mail Relay is an email-delivery plugin. It does NOT connect to any external service automatically. A connection is made ONLY after you explicitly configure a provider and either (a) WordPress sends an outgoing email, or (b) you click "Send Test Email", or (c) you enable a notification channel and an email-failure alert is dispatched.

For each configured email provider, the data sent on every send is the same set of fields WordPress would otherwise hand to the local mail() function: the recipient address(es), CC, BCC, Reply-To, From name/address, subject, message body, headers, and attachments. No additional telemetry or analytics is transmitted by this plugin.

For each configured notification channel (Telegram, Slack, Discord, Microsoft Teams, Google Chat, Pushover, custom Webhook), the data sent is a short status message you compose in the plugin (default: failed-delivery summary or daily summary).

= Email-delivery providers (configurable in Settings → Connections) =

**SendGrid (by Twilio)**
* Service: https://sendgrid.com
* When data is sent: every WordPress email when SendGrid is the active connection, plus on test-email click.
* Endpoint: https://api.sendgrid.com/v3/mail/send
* Privacy Policy: https://www.twilio.com/en-us/legal/privacy
* Terms of Service: https://www.twilio.com/en-us/legal/tos

**Mailgun (by Sinch)**
* Service: https://www.mailgun.com
* When data is sent: same as above, but to Mailgun.
* Endpoint: https://api.mailgun.net/v3/{domain}/messages (US) or https://api.eu.mailgun.net/v3/{domain}/messages (EU).
* Privacy Policy: https://www.mailgun.com/legal/privacy-policy/
* Terms of Service: https://www.mailgun.com/legal/terms/

**Amazon SES (AWS Simple Email Service)**
* Service: https://aws.amazon.com/ses/
* When data is sent: same.
* Endpoint: https://email.{region}.amazonaws.com/v2/email/outbound-emails (region you select).
* Privacy Policy: https://aws.amazon.com/privacy/
* Terms of Service: https://aws.amazon.com/service-terms/

**Brevo (formerly Sendinblue)**
* Service: https://www.brevo.com
* Endpoint: https://api.brevo.com/v3/smtp/email
* Privacy Policy: https://www.brevo.com/legal/privacypolicy/
* Terms of Service: https://www.brevo.com/legal/termsofuse/

**SparkPost**
* Service: https://www.sparkpost.com
* Endpoint: https://api.sparkpost.com/api/v1/transmissions
* Privacy Policy: https://www.sparkpost.com/policies/privacy/
* Terms of Service: https://www.sparkpost.com/policies/tou/

**Postmark**
* Service: https://postmarkapp.com
* Endpoint: https://api.postmarkapp.com/email
* Privacy Policy: https://postmarkapp.com/privacy-policy
* Terms of Service: https://postmarkapp.com/terms-of-service

**Elastic Email**
* Service: https://elasticemail.com
* Endpoint: https://api.elasticemail.com/v4/emails
* Privacy Policy: https://elasticemail.com (linked from the site footer; Elastic Email does not publish a stable deep-link URL)
* Terms of Service: https://elasticemail.com (linked from the site footer; Elastic Email does not publish a stable deep-link URL)

**SMTP2GO**
* Service: https://www.smtp2go.com
* Endpoint: https://api.smtp2go.com/v3/email/send
* Privacy Policy: https://www.smtp2go.com/privacy/
* Terms of Service: https://www.smtp2go.com/terms/

**Netcore Email API (formerly Pepipost)**
* Service: https://netcorecloud.com
* Endpoint: https://emailapi.netcorecloud.net/v5/mail/send
* Privacy Policy: https://netcorecloud.com/privacy-policy
* Terms of Service: https://netcorecloud.com (request the latest terms via the contact form on the homepage; Netcore does not publish a stable terms-of-use URL).

**SMTP.com**
* Service: https://www.smtp.com
* Endpoint: https://api.smtp.com/v4/messages
* Privacy Policy: https://www.smtp.com/policies/privacy-policy/
* Terms of Service: https://www.smtp.com/legal/terms/

**SocketLabs**
* Service: https://www.socketlabs.com
* Endpoint: https://inject.socketlabs.com/api/v1/email
* Privacy Policy: https://www.socketlabs.com/legal/privacy-policy/
* Terms of Service: https://www.socketlabs.com/legal/

**Moosend (by Sitecore)**
* Service: https://moosend.com
* Endpoint: https://api.moosend.com/v3/
* Privacy Policy: https://moosend.com/privacy-policy/
* Terms of Service: https://moosend.com/terms/

**SendLayer**
* Service: https://sendlayer.com
* Endpoint: https://console.sendlayer.com/api/v1/email
* Privacy Policy: https://www.sendlayer.com/privacy-policy/
* Terms of Service: https://www.sendlayer.com/terms-of-service/

**Mailjet (by Sinch)**
* Service: https://www.mailjet.com
* Endpoint: https://api.mailjet.com/v3.1/send
* Privacy Policy: https://sinch.com/legal/privacy-notice/
* Terms of Service: https://sinch.com/legal/

**MailerSend**
* Service: https://www.mailersend.com
* Endpoint: https://api.mailersend.com/v1/email
* Privacy Policy: https://www.mailersend.com/legal/privacy-policy
* Terms of Service: https://www.mailersend.com/legal/terms-of-use

**Mandrill (by Mailchimp)**
* Service: https://mailchimp.com/developer/transactional/
* Endpoint: https://mandrillapp.com/api/1.0/messages/send.json
* Privacy Policy: https://mailchimp.com/legal/privacy/
* Terms of Service: https://mailchimp.com/legal/terms/

**Resend**
* Service: https://resend.com
* Endpoint: https://api.resend.com/emails
* Privacy Policy: https://resend.com/legal/privacy-policy
* Terms of Service: https://resend.com/legal/terms-of-service

**Zoho ZeptoMail**
* Service: https://www.zoho.com/zeptomail/
* Endpoint: https://api.zeptomail.com/v1.1/email
* Privacy Policy: https://www.zoho.com/privacy.html
* Terms of Service: https://www.zoho.com/zeptomail/terms.html

**Microsoft 365 / Outlook (Microsoft Graph API and SMTP)**
* Service: https://www.microsoft.com/microsoft-365
* Endpoint (API): https://graph.microsoft.com/v1.0/users/{user}/sendMail (OAuth token from https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token)
* Endpoint (SMTP): smtp.office365.com:587
* Privacy Policy: https://privacy.microsoft.com/en-US/privacystatement
* Terms of Service: https://www.microsoft.com/en-us/servicesagreement/

**Gmail / Google Workspace (Gmail API and SMTP)**
* Service: https://workspace.google.com
* Endpoint (API): https://gmail.googleapis.com/gmail/v1/users/me/messages/send (OAuth token from https://oauth2.googleapis.com/token)
* Endpoint (SMTP): smtp.gmail.com:587
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://policies.google.com/terms

= Notification channels (configurable in Settings → Alerts) =

These services are contacted ONLY when a notification channel is enabled AND a delivery failure (or daily-summary) event triggers an alert. The data sent is the short status text shown in the plugin's alert preview.

**Telegram (Bot API)**
* Service: https://telegram.org
* When data is sent: when a Telegram channel is enabled, a failed-delivery alert or daily-summary alert is generated.
* Endpoint: https://api.telegram.org/bot{token}/sendMessage
* Privacy Policy: https://telegram.org/privacy
* Terms of Service: https://telegram.org/tos

**Pushover**
* Service: https://pushover.net
* Endpoint: https://api.pushover.net/1/messages.json
* Privacy Policy: https://pushover.net/privacy
* Terms of Service: https://pushover.net/terms

**Slack (Incoming Webhooks)**
* Service: https://slack.com
* Endpoint: the webhook URL you paste (https://hooks.slack.com/services/...).
* Privacy Policy: https://slack.com/trust/privacy/privacy-policy
* Terms of Service: https://slack.com/legal

**Discord (Webhooks)**
* Service: https://discord.com
* Endpoint: the webhook URL you paste (https://discord.com/api/webhooks/...).
* Privacy Policy: https://discord.com/privacy
* Terms of Service: https://discord.com/terms

**Microsoft Teams (Incoming Webhooks)**
* Service: https://www.microsoft.com/microsoft-teams/
* Endpoint: the Teams webhook URL you paste.
* Privacy Policy: https://privacy.microsoft.com/en-US/privacystatement
* Terms of Service: https://www.microsoft.com/en-us/servicesagreement/

**Google Chat (Incoming Webhooks)**
* Service: https://chat.google.com
* Endpoint: the Google Chat webhook URL you paste.
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://workspace.google.com/terms/premier_terms.html

**Custom Webhook**
* When data is sent: when a custom Webhook channel is enabled and an alert is generated, a JSON payload is POSTed to the URL you paste.
* Endpoint: the URL you paste.
* Privacy Policy / Terms: governed by the operator of the URL you provide.

= Bundled third-party libraries (no network call) =

**Chart.js (https://www.chartjs.org)** — bundled locally at assets/js/chart.umd.min.js (v4.5.0). Used to render the email-stats chart on the dashboard. Loaded entirely from your own server; no CDN call is made.

== Privacy Policy ==

SendForce Mail Relay stores the following data in your WordPress database:

* **Email logs** (if logging is enabled): recipient email address, subject, headers, email body (optional), sent status, error messages, and timestamps.
* **Email queue** (if queue is enabled): same data as above, plus retry attempt count and next retry time.
* **Plugin settings**: your SMTP host, port, username, and encrypted password or API key.

Email body logging can be disabled in **Settings > Queue & Logging**. Log entries are automatically deleted after the configured retention period (default: 30 days). All data is deleted when the plugin is uninstalled.

We recommend adding a note to your site's privacy policy if you log email content, informing users that outgoing email metadata may be stored.

== Screenshots ==

1. Modern dashboard with connection status, email statistics, and 7-day send chart.
2. Settings page with visual provider grid and SMTP/API tab switcher.
3. Email Log with status filters, keyword search, and date range filtering.
4. Email Test page with live SMTP debug log output.
5. Email Queue management with retry statistics.

== Changelog ==

For the **complete** release history with full detail on every change, see the [full changelog on GitHub](https://github.com/ShiponKarmakar/SendForce/blob/main/CHANGELOG.md) (also bundled with the plugin as CHANGELOG.md) or open the **What's New** tab inside SendForce in your WordPress admin. The section below is condensed to fit within WordPress.org's 5,000-word changelog limit.

= 1.2.3 =
* New: Import from Post SMTP, SMTP Mailer and WP SMTP (Kadence Mail): connections, credentials, sender, fallback and WP SMTP's per-sender routing, with a preview first and nothing in SendForce overwritten.
* Fixed: Saving a new connection could create it twice if the same request reached the server twice, for example a browser retry, or clicking Save again after a "Request failed" message when the save had gone through.
* Changed: The 1.0.x entries in this changelog are now one summary, and old upgrade notices are removed, to stay within WordPress.org's limit. The full history is in CHANGELOG.md.

= 1.2.2 =
* Improved: General Settings are now grouped into Sending, Email Log, Email Queue and Advanced. Each group shows its current state (for example "On · 30 days") so you can find any setting at a glance, and the panel no longer hides its last settings and Save button on smaller screens.
* New: choose how often the Email Queue sends: every 1, 5, 10, 15 or 30 minutes (Settings → Email Queue → Send Interval). Every minute stays the default. Thanks to @dimalifragis for the suggestion.
* Fixed: emails from plugins that mark HTML only through WordPress's `wp_mail_content_type` filter, such as Directorist, arrived as plain text showing raw HTML code when the Email Queue was on or an API provider was used. SendForce now decides between HTML and plain text exactly the way WordPress does. Thanks to @dimalifragis for the report.

= 1.2.1 =
* Fixed: with "Multi-Part Plain Text" turned on, plain-text emails (password resets, notifications, the test email) were delivered as HTML on SMTP and PHP Mail connections. The option now only applies to HTML emails, as intended. Thanks to @dimalifragis for the report.
* Fixed: a PHP deprecation notice ("Creation of dynamic property WP_PHPMailer::$Exceptions") written to the debug log on every SMTP send on PHP 8.2 and later.
* New: a Simulation Mode badge in the SendForce header. When sending is simulated, every SendForce screen now says so — previously the only sign was the toggle buried in Settings, so a site could quietly stop sending email and look perfectly normal. Click the badge to go straight to the setting.
* New: the Email Test tab now warns you before you send, instead of only refusing the test afterwards, with a button that takes you straight to the setting.

= 1.2.0 =
* Fixed: Microsoft 365 (Graph API) sent emails without their attachments — the files were accepted and then left out of the message entirely. Thanks to @ydekinder for the detailed report.
* Fixed: Zoho ZeptoMail dropped attachments in the same way.
* Fixed: attachments over Microsoft's 3 MB limit now give a clear message naming the files, instead of a confusing server error.
* New: a Setup screen after activation. Installing SendForce now opens a welcome screen that leads with migration — every SMTP plugin it can import from is listed with "Import settings" and "Deactivate" — followed by the two steps that matter: connect a provider, send a test email.
* New: the redirect happens once, for the admin who activated the plugin. It is skipped on bulk activation, on network activation, and on any site that already has a connection, so updates and reactivations never hijack your page.
* Changed: the "another SMTP plugin is active" warning now appears only on SendForce's own screens instead of every page in wp-admin.
* Changed: once you have imported from another plugin, its row shows an "Imported" badge and the button becomes "Check again", instead of still reading "Import settings".
* Fixed: uninstall now removes every cache and flag the plugin creates — the dashboard and daily statistics caches, alert throttles and the onboarding records were previously left behind.
* Fixed: admin scripts and styles are now cache-busted per file, so browsers never serve an outdated copy after the plugin's files change.
* Fixed: finishing setup now ends on a "You're all set" screen instead of leaving you on the test-email tab, and the feature cards appear once you are done rather than before you start.
* Fixed: the setup wizard now returns you to itself after you save your first connection, instead of leaving you on the Settings tab with the wizard unfinished.
* New: once you have a connection, the Setup screen steps aside for good — SendForce opens on the Dashboard instead, including after a reactivation.
* Fixed: importing from the Setup screen now refreshes it, so the steps and progress reflect the connection you just imported, with a summary of what came across.

= 1.1.3 =
* New: `wp sendforce import list|preview|run <source>` imports from the command line, with the same preview and confirmation as the admin UI.
* Note: if your SparkPost account is on the EU region, the import now tells you SendForce only supports the US region, instead of copying a key that cannot send.
* New: Import from WP Mail SMTP, FluentSMTP and Easy WP SMTP, including on Multisite. While one of them is active, an "Import settings" button (in the conflict notice, and on the Settings tab) previews what will come across, then adds its connections, credentials, sender identity and fallback to SendForce; FluentSMTP per-sender routing becomes Smart Routing rules. Existing SendForce settings are never overwritten and re-importing doesn't create duplicates.
* Changed: More of the plugin is translatable — WP-CLI messages, Gmail and Microsoft 365 sign-in errors, and a few remaining admin labels now go through WordPress translation.
* Changed: The WordPress admin sidebar label is now "SendForce" (was "SF Mail Relay").

= 1.1.2 =
* New: WordPress Multisite support. After Network Activate, every site sets itself up on its first visit, including sites created later. Previously only the main site was set up, so other sites' emails were not logged or queued.
* New: Deleting a site from a network now removes its SendForce data, and uninstalling the plugin cleans up every site.
* Fixed: On updates, setup now completes on the first request of any kind, not only when an admin opens the dashboard.
* Fixed: Uninstalling now also removes the queue's per-minute pacing counters.
* Fixed: With an API connection (SendGrid, Amazon SES, Mailgun and others) and no Sender Identity set, the From address chosen by the sending plugin — such as WooCommerce's store address — was replaced with the site's admin email. API connections now keep it, the same as SMTP connections, including on Smart Routing rules. Gmail and Microsoft 365 API connections are unchanged, as they always send as the connected account.
* Docs: Added a WP-CLI FAQ listing every `wp sendforce` command.

= 1.1.1 =
* Docs: Added a video overview to the plugin description.

= 1.1.0 =
* New: Per-connection sending pace. Set "Max emails per minute" on any connection and the Email Queue paces sends through it — e.g. a slow pace for the Gmail API and a fast one for Amazon SES. Connections are paced independently, and a backlog on one never holds up the others.
* Improved: While enabled, the Email Queue now runs every minute (was every 5 minutes), and each run stops after about 20 seconds so it stays within host PHP time limits. Sites that don't use the queue keep the 5-minute check.
* Improved: The Queue tab now shows each failed or retrying email's last provider error.
* Changed: The roadmap on the What's New tab no longer shows target dates or versions, and reflects what has shipped.
* Fixed: With the Email Queue enabled, emails sent through an API connection (Gmail API, Amazon SES, SendGrid, etc.) were delivered immediately instead of being queued, and an API email processed from the queue could also be sent a second time through PHP mail.
* Fixed: If two queue runs overlapped (for example a scheduled run and "Process Queue Now"), the same email could be sent twice. Each email is now claimed by exactly one run.
* Fixed: With the Email Queue enabled, a Smart Routing rule could leave its connection active for later emails in the same page load.

= 1.0.0 – 1.0.40 =
* The first release line: SMTP and email-API sending for 24+ providers, Microsoft 365 and Gmail API, automatic fallback connection, Email Log, Email Queue with retries, failure alerts, WP-CLI commands, developer hooks and encrypted credential storage. Every 1.0.x change is listed in the [full changelog on GitHub](https://github.com/ShiponKarmakar/SendForce/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 1.2.3 =
Adds import from Post SMTP, SMTP Mailer and WP SMTP, and fixes a new connection sometimes being saved twice.

= 1.2.2 =
Fixes emails from plugins like Directorist showing raw HTML code when the Email Queue is on or an API provider is used. Adds a choice of Email Queue send interval.

= 1.2.1 =
Fixes plain-text emails arriving as HTML when "Multi-Part Plain Text" is on (SMTP and PHP Mail). Also adds a Simulation Mode badge so a paused site is never silent.

= 1.2.0 =
Important for Microsoft 365 and Zoho ZeptoMail users: attachments were accepted and then left out of the sent email. This release fixes that. New installs also get a Setup screen. Your existing settings are unchanged.

= 1.1.3 =
Adds one-click import from WP Mail SMTP, FluentSMTP and Easy WP SMTP. Your existing SendForce settings are not changed.

= 1.1.2 =
Adds full WordPress Multisite support, and API connections now keep the From address set by plugins like WooCommerce. If your provider only accepts specific verified addresses, set one in Settings → Sender Identity before updating.

= 1.1.0 =
Recommended if you use the Email Queue: fixes API connections bypassing the queue and possible duplicate sends, and adds per-connection "Max emails per minute" pacing. While enabled, the queue now runs every minute.

= 1.0.40 =
WordPress.org compliance and hardening release. Updating from 1.0.36 or earlier? This also includes 1.0.37's credential hardening: saved SMTP passwords and API keys now survive security-key/salt changes.
