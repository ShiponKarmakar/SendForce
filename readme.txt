=== SendForce Mail SMTP Relay - SMTP and Email API ===
Contributors: shiponkarmakar
Donate link: https://profiles.wordpress.org/shiponkarmakar/
Tags: email, email log, gmail, outlook, smtp
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 1.2.2
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
* **Import from WP Mail SMTP, FluentSMTP and Easy WP SMTP** — switch in one click: connections, credentials, sender identity, fallback and FluentSMTP's per-sender routing come across, with a preview first and nothing in SendForce overwritten.
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

= Can I import my settings from WP Mail SMTP, FluentSMTP or Easy WP SMTP? =

Yes. While the other plugin is still active, click **Import settings** — in the notice SendForce shows, or any time under **SendForce → Settings → Import from another plugin** (still there after you dismiss the notice). There is also a WP-CLI command, `wp sendforce import run <source>`, which is handy for staging sites and bulk work. On Multisite you import per site, and if WP Mail SMTP or Easy WP SMTP is set to one network-wide configuration, only a network administrator can import it. It previews exactly what will come across, then adds the connections (credentials re-encrypted with SendForce's own key), sender identity and fallback; FluentSMTP's per-sender routing becomes Smart Routing rules. Nothing already in SendForce is overwritten, and importing twice doesn't create duplicates. Gmail and Microsoft 365 API connections need one extra step: click Connect in SendForce to sign in again (Gmail or Outlook over SMTP with an app password imports as-is). If you used Easy WP SMTP's One-Click Setup, there are no app credentials saved on your site to copy, so SendForce asks you to create your own Google or Microsoft app. Credentials you keep in wp-config.php are not copied into the database — you'll be asked to enter them. Send a test email from SendForce, then deactivate the other plugin.

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

= 1.0.40 =
* Compliance: The update notice's script is now enqueued instead of printed inline, and a few template outputs are now explicitly escaped (WordPress.org Plugin Check).
* Security: Hardened the encoding of the OAuth connection popup page.

= 1.0.39 =
* Fixed: Admin notices (the "conflicting SMTP plugin" warning and the credential-recovery notice) could have their last row clipped by the plugin's top navigation bar. They now have proper clearance.
* Fixed: Hardened the conflict-notice script so its dismiss and close buttons keep working reliably.

= 1.0.38 =
* Compatibility: Tested up to WordPress 7.1.

= 1.0.37 =
* Fixed: Saved SMTP passwords and API keys could become permanently unreadable if the site's security keys/salts were regenerated (by a security plugin, host migration or site clone). Credentials are now encrypted with a dedicated key that does not change with the salts, so they survive these events. Existing credentials are migrated automatically — no need to re-enter anything.
* Fixed: Passwords and API keys containing `<`, `>` or `%` were silently corrupted when saved, causing authentication failures that looked like a wrong password. The same corruption affected notification-channel secrets (Telegram/Slack bot token, Pushover keys) and the Amazon SES access key; all are now stored verbatim, with proper output escaping on the Alerts screen.
* Added: A clear admin warning when a stored credential cannot be decrypted, instead of silently attempting to send with an empty password.
* Fixed: With the email queue enabled, messages could be sent twice on sites in a positive-UTC timezone. The queue's internal processing lease was recorded in UTC while everything else used site-local time, so a second cron run (or the "Process Queue Now" button) could reclaim a message that was still being sent and deliver it again. Leases are now recorded in site-local time.
* Fixed: If a message could not be written to the queue (database error), WordPress was told the send succeeded and the email was silently lost. It is now delivered immediately instead of being dropped.
* Fixed: Uninstalling the plugin now removes the routing-rules table and routing/credential-notice options it previously left behind.
* Fixed: A round-robin routing rule whose connection list contained a gap (e.g. after a connection was deleted) could fail to pick a target.
* Improved: The admin interface's JavaScript-rendered text is now fully translatable. Dynamic labels, buttons, confirmation dialogs, the OAuth connection flow and the Smart Routing rule builder previously always displayed in English regardless of site language; they now use WordPress's translation system, so translations from translate.wordpress.org apply automatically.
* Fixed: The "updated to vX" notice now dismisses permanently when you click "See what's new", not only when using the small dismiss button.

= 1.0.34 =
* UI: Redesigned the admin top navigation — tabs are now grouped by purpose (Overview, Setup, Activity, What's New) with subtle dividers, and the active tab uses a clearer brand-coloured highlight.
* Compliance: Set the WordPress.org listing display name to "SendForce Mail SMTP Relay - SMTP and Email API" — it no longer uses the discouraged "Free" / "WP" / "Plugin" terms or third-party trademarks.
* Security: Hardened the Amazon SES and Gmail raw-MIME send path against email header injection — CR/LF characters are now stripped from every address (To/Cc/Bcc/Reply-To/From) and the subject, closing a path where untrusted input passed into wp_mail() could inject a Bcc or extra recipients.
* Fix: Encrypted credential storage no longer corrupts roughly 1 in 4,400 saved secrets. The encryption IV is now length-delimited instead of joined to the ciphertext with "::" (which could collide with a binary IV). Existing saved credentials keep working, and decryption now fails closed on a damaged value instead of returning it.
* Fix: With the email queue enabled, an API connection no longer sends a duplicate (once via the API, once via SMTP fallthrough) during a test send, and Simulation Mode is no longer bypassed by a test send.
* Fix: With the queue enabled and an SMTP primary plus a fallback connection, a fallback-recovered email is no longer re-queued and re-sent on the next cron run.

= 1.0.33 =
* Compliance: Shortened the plugin display name to "SendForce Mail Relay", removed comparative/promotional wording from the description, fixed three stale third-party policy links in the External Services section, and pointed the in-plugin review link at the full reviews page — per the WordPress.org Plugin Directory guidelines.
* Fix: The Settings provider grid no longer briefly flashes all 24 providers before collapsing on first load — it now renders collapsed (15 shown + "Show All Providers") server-side, so there's no flash even before JavaScript runs.
* Security: **Hardened alert webhooks against SSRF (server-side request forgery).** User-supplied webhook URLs (Slack, Discord, Microsoft Teams, Google Chat, and the generic Webhook channel) are now validated before SendForce sends to them — any URL that resolves to a private, loopback, link-local, or reserved IP range (including the cloud metadata address 169.254.169.254) is refused. Both IPv4 and IPv6 are checked. A new `sendforce_allow_private_webhook_hosts` filter lets self-hosted setups opt back in for legitimate internal collectors.
* Fix: **Attachment filenames with non-ASCII or special characters now encode correctly** across all API providers. Filenames are RFC 5987 / RFC 2231 encoded (multipart/form-data for Mailgun; raw MIME for Amazon SES and Gmail), so UTF-8 names — e.g. WooCommerce invoices named with accents or non-Latin scripts — arrive intact instead of as garbled text or being silently dropped by the provider. Also closes an edge case where a CR/LF character in a filename could inject MIME headers.
* Performance: **Dashboard and chart statistics are now cached.** The dashboard's lifetime sent/failed counts and the daily-activity chart (which previously ran up to 180 COUNT queries per load on the 90-day range) are cached for one hour and refreshed automatically whenever an email is logged or logs are deleted. The admin dashboard is noticeably faster on sites with large email logs.

= 1.0.32 =
* Compatibility: Verified compatible with WordPress 7.0 — bumped the "Tested up to" header so the wp.org compatibility warning clears for sites already running the latest WordPress.
* Maintenance: Refreshed the wp.org plugin directory tags (`email`, `email log`, `gmail`, `outlook`, `smtp`) to better match how users actually search. `gmail` and `outlook` in particular are high-intent searches that match our existing Gmail and Microsoft 365 OAuth flows but weren't previously tagged.

= 1.0.31 =
* Fix: **Email Log "Connection" filter was dropped when paginating or exporting** (regression from 1.0.30). The pagination and export URLs now preserve the active provider filter, so a filtered view stays filtered when paging or exporting.
* Polish: Accessibility (`aria-label` on icon-only Log/Queue buttons), proper plural forms via `_n()` on the bulk-action counter, and minor i18n/SVG-whitelist fixes. See CHANGELOG.md for detail.

= 1.0.30 =
* New: **Filter Email Log by connection.** A new "Connection" dropdown in the Email Log filter bar lets you narrow the table down to emails sent through a specific provider (Amazon SES, SendGrid, Microsoft 365, etc.). Useful when you run several connections side-by-side and want to audit just one — e.g. "show me everything WooCommerce sent via SendGrid this week". Options are populated from connections that actually have log entries (no empty dropdown choices). Existing log rows from before 1.0.30 don't carry a provider value and stay visible under the "All connections" default.
* New: **"Copy diagnostic snapshot" button** on the Email Log detail modal. Bundles the log ID, timestamp, status, error, headers, attachments, and the (already-redacted) raw API request/response into one clipboard-friendly text block — pre-formatted for pasting straight into a support ticket. Plugin / WordPress / PHP versions are included automatically. No more copy-paste-merge of six different fields when reporting an issue.
* Internal: Added `provider` column to `wp_sendforce_logs` (varchar(50), indexed). Populated automatically going forward from the captured debug payload (when available) or the active connection's configured provider. Existing rows are not back-filled; only new log entries get the column populated. dbDelta handles the schema migration on plugin upgrade.

= 1.0.29 =
* Fix: **Stray gray "pill" elements rendering on the What's New tab** (introduced in 1.0.28). The changelog accordion's markdown-to-HTML converter wrapped backtick-delimited text in `<code>` tags but didn't escape the inner content first — so when a bullet contained literal `<tag>` text inside backticks (very common in technical changelogs), the browser interpreted the embedded text as a real HTML tag and produced floating empty `<code>` elements that rendered as gray pills against the page background. Fix: `esc_html()` the raw line before applying the markdown transformations. The two regex rules (`**bold**` → `<strong>` and backtick → `<code>`) now run on already-escaped text, guaranteeing balanced tags and no HTML injection from bullet content. Affects 1.0.28 only.
* Fix: **WordPress 6.7+ "Translation loading triggered too early" notice** caused by the custom cron schedule's display label being translated inside the `cron_schedules` filter. The filter fires whenever WP looks up scheduled events (which can happen before `init`, during plugins_loaded or even setup_theme), so calling `__( …, 'sendforce-mail-relay' )` there ran the translation function before the textdomain was loaded. Both filter callbacks (in the activator and the mailer) now use a literal English string for the cron schedule's `display` label — that label is only shown in dev tools like WP Crontrol or `wp-cli cron list`, never user-facing, so leaving it untranslated is harmless. Resolves the deprecation notice on sites running WP_DEBUG with WP 6.7 or newer.

= 1.0.28 =
* New: **Test send now shows the full provider response inline** on the Email Test page. When a test fails the headline shows the short reason ("Failed to send via Microsoft 365 API") and a new "Provider response" panel below it shows the raw HTTP response body — pretty-printed when it's JSON, with a one-click Copy button for support tickets. Previously the full Graph/SES/Mailgun response was only visible by clicking into the Email Log; users debugging a misconfiguration now see everything they need without leaving the test page.
* New: **HTTPS warning on webhook URL fields** (Slack, Discord, Microsoft Teams, Google Chat, generic Webhook). As soon as you paste/type a URL starting with `http://` an inline red warning appears explaining that the alert message (site name, failed recipient, error details) would be sent unencrypted. Catches typos and homemade webhook receivers before save.
* New: **"Welcome to v{X.Y.Z}" admin notice** after the plugin updates, with a one-click button to the in-admin "What's New" tab. Per-user dismissible, only shows once per version, never on fresh installs or on SendForce's own settings pages (where the tab is already visible). Helps users discover what changed without having to read the wp.org changelog page.
* New: **Failure-alert rate limiting**, default 5-minute window per channel. A burst of failed sends (e.g. WooCommerce sending 100 order emails through an expired API key) no longer floods Slack/Telegram/Discord/Teams with 100 identical pings — within the window the first one fires, the rest are counted and rolled up into a "(+N additional failures were suppressed)" note on the next alert after the window expires. Filter `sendforce_alerts_throttle_seconds` to change the window length, set it to 0 to disable throttling entirely.
* Docs: Added **CHANGELOG.md** with the complete, full-detail release history for every version (no length limit). The condensed Changelog in this readme.txt covers the WordPress.org 5,000-word cap; CHANGELOG.md carries the full story for anyone who wants it.
* In-admin: The **What's New** tab now reads from CHANGELOG.md when present (falls back to readme.txt). Browse every release's full bullet list inline in WordPress without going to wp.org or GitHub.
* Older entries (1.0.0 – 1.0.20) in this readme.txt are now one-line summaries; their full detail lives in CHANGELOG.md and the in-admin Changelog accordion. Recent releases (1.0.21+) remain fully detailed in both places.

= 1.0.27 =
* Security (defense-in-depth): **Bulk-delete logs and queue retry/delete actions now require `manage_options` capability** in addition to the existing nonce check. The forms that issued these requests already live on admin-only pages, so a non-admin couldn't normally obtain a valid nonce — but explicit capability checks match WordPress.org Plugin Check expectations and protect against any future flow that might mint a nonce in a less-restricted context.
* Privacy: **Email Log now stores only the filename basenames of attachments** instead of full filesystem paths. Previously rows like `/var/www/example.com/wp-content/uploads/woocommerce_uploads/2026/invoice-12345.pdf` were stored verbatim — leaking the server directory layout, shared-hosting username paths, and customer order IDs from WooCommerce invoice filenames. From 1.0.27 the same row stores only `invoice-12345.pdf`. Existing rows are not rewritten; only new log entries get basename-only.

= 1.0.26 =
* New: Attachments now work in API mode for all 18 API providers (1.0.25 covered Amazon SES and Gmail; this closes the remaining 16). Files passed to `wp_mail()` — WooCommerce invoices, form uploads, certificates — are base64-encoded into each provider's native schema.
* New: The "Multi-Part Plain Text" setting now applies to API-mode sends across all providers, adding a plain-text alternative alongside HTML for better deliverability.
* Fix: Queued emails that fail now record the actual provider error (expired token, unverified domain, wrong key) instead of a generic "Failed to send email." message.
* New: "Process Queue Now" button, a queue-disabled info notice, and a "Next automatic run" timestamp on the Queue tab; test sends now bypass the queue so they deliver synchronously.
* New: "What's New" tab combining the roadmap with a collapsible full-changelog accordion (replaces "What's Next"; old URLs still resolve).

= 1.0.25 =
* New: Email attachments now work with Amazon SES and Gmail API modes (multipart/mixed). Multi-Part Plain Text setting also applies in API mode.
* Fix: Non-ASCII subjects and From-names (Café, résumé, 주문) are now RFC 2047 base64-encoded.
* Refactor: SES and Gmail mailers share a single `build_raw_mime()` helper. Full release detail in CHANGELOG.md.

= 1.0.24 =
* Fix (critical): Microsoft 365 / Gmail OAuth `AADSTS50011` redirect URI mismatch. Root cause was `add_query_arg()` not URL-encoding values, leaving `&` characters bare inside redirect_uri. Switched to `http_build_query()` with `PHP_QUERY_RFC3986`.
* New: OAuth consent now opens in a popup window with graceful redirect fallback; callback splash signals the parent via localStorage + postMessage + closed-polling (survives COOP severance).
* Fix: Microsoft 365, Amazon SES, Gmail, and Mailgun send failures now surface the actual provider error in the UI and Email Log (all four mailers now route through `make_request()`).
* New: Tolerate users pasting `Bearer TOKEN` / `Zoho-enczapikey TOKEN` prefixes — auto-stripped before constructing the auth header. All API keys are also auto-trimmed.
* New: Per-provider API-key hint under the field. Client-side + server-side rejection of GUID-shaped Azure Secret IDs pasted into the Client Secret field. Rewrote Gmail and Microsoft 365 OAuth setup instructions. Full release detail in CHANGELOG.md.

= 1.0.23 =
* Fix: OAuth redirect URI is now constructed defensively against third-party `admin_url` filters (Wordfence, LiteSpeed Cache).
* i18n: Email Log "Next run" timestamp now uses the site's configured date/time format.
* Hardening: Expanded `wp_kses` SVG whitelist used for channel logos on the Alerts tab.

= 1.0.21 =
* New: "Clean Now" button on the Email Log + auto-clean status row showing retention, next run, and a deep-link to settings.
* New: "via rule: <label>" badge on Email Log entries when an email was redirected by a Smart Routing rule.
* New: "Connection missing" warning on routing rule cards when a rule points at a deleted connection.
* New: Real channel logos on the Alerts tab (Slack, Discord, Teams, Telegram, Google Chat, Pushover, Webhook).
* Improvement: Test Routing tool now uses the exact same matcher as the live mailer (no drift between predicted and actual routing).
* Fix: Alert channels were occasionally wiped when saving the Summary Email form. Full release detail in CHANGELOG.md.

= 1.0.20 =
* Admin top navigation layout fixes at narrow widths (tablet/mobile, mid-size laptops). Tab labels collapse to icon-only below 1340px. Logo gets proper left padding on mobile.

= 1.0.19 =
* New: **Smart Routing Rules** — send different emails through different connections by recipient domain/pattern, source plugin (auto-detected for WooCommerce, CF7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms, Forminator, EDD, MemberPress, LearnDash, MailPoet, BuddyPress, bbPress), subject contains, time of day, weekday. Top-down evaluation, first match wins. Includes Routing tab, inline rule editor, master enable toggle, `from_email` / `from_domain` conditions, "via rule" badge in Email Log, starter presets, "What's Next" roadmap tab, styled confirm dialogs.

= 1.0.18 =
* Fixed misleading "Connected" status on Gmail / Microsoft 365 OAuth panel — now checks API mode + Client ID + Client Secret + refresh token all present (not just any password field value). Added amber "partial credentials" state. Hint to switch to API mode when in SMTP. Polished status indicator dot.

= 1.0.17 =
* Initialize `oauth_email` field on newly created connections so the schema is consistent from save time.

= 1.0.16 =
* New: **One-click "Connect with Google" and "Connect with Microsoft" OAuth buttons** replace manual refresh-token generation via OAuth Playground / Azure CLI. Refresh token captured automatically, stored encrypted, panel shows "Connected as user@example.com" with Disconnect button. Bring-your-own-credentials — no third-party OAuth proxy. Microsoft 365 supports both delegated and app-only flows. Setup help panel with copy-able redirect URI.

= 1.0.15 =
* Audited all 20 provider integrations against current docs. 18 verified spec-compliant. Fixed Moosend HTML content-type detection (explicit `IsContentHtml` flag in both HTML and plain-text paths).

= 1.0.14 =
* Fixed Email Queue orphan-row bug: rows stuck in "processing" state after cron timeout / PHP crash now recover via a 10-minute lease (`next_retry_at`) on the next cron run.

= 1.0.13 =
* Security: hardened PHP-mail fallback against header injection (strips CR/LF/null from To, Subject, headers). Validated attachment paths against safe roots (prevents path traversal). Sanitized fallback-connection label. Server-side `wp_strip_all_tags` on provider errors returned to the browser.

= 1.0.12 =
* Renamed "Default Connection" → "Primary Connection" throughout the admin UI (internal storage key unchanged). Provider status dot reflects configuration status (not brand colour). Fixed fallback flow so `wp_mail()` returns true on fallback success; clearer recovery message. Suppressed spurious SMTP-warning when active path was API or PHP-Mail.

= 1.0.11 =
* Plugin Name header is now clean brand name "SendForce Mail Relay" (was long SEO title); Description is a single-line summary. WordPress.org listing keeps the longer SEO title for directory search.

= 1.0.10 =
* New UI controls for **Developer Debug** toggle and **Max Retry Attempts** (1–10). Hardened From-email validation (admin notice on invalid). Replaced native `<select>` dropdowns with branded styled component (full keyboard support — Arrow/Enter/Escape). Default/Fallback selectors auto-refresh after connection save. Form-control polish (chevron, focus ring, hover, disabled states).

= 1.0.9 =
* Adjusted the WordPress.org listing title. Internal product name unchanged.

= 1.0.8 =
* New: **Automatic fallback connection** — when primary send fails, SendForce retries via configured fallback. Works across any API → API, API → SMTP, SMTP → API, SMTP → SMTP, or any → PHP-mail combination. Both attempts logged; recovered emails get a "Recovered via fallback" badge. Improved readme Description for WordPress.org discoverability.

= 1.0.7 =
* Public display name updated for WordPress.org search index discoverability. Refreshed Tags to highest-volume search terms. Polished connection-row Edit/Delete buttons with dashicons. Added Dashboard / Settings / Email Log / Queue / Test / Alerts sub-menu entries.

= 1.0.6 =
* Shortened WordPress admin sidebar label to "SF Mail Relay" so it fits one line. Added sub-menu entries reachable from sidebar fly-out.

= 1.0.5 =
* Removed accidental `.DS_Store` file from `assets/images/providers/`. Added `phpcs:ignore` annotations with rationale to interpolated table-name `$wpdb` queries. Restructured WP-CLI log-delete query for proper phpcs handling.

= 1.0.4 =
* Updated bundled Chart.js to 4.5.0. Expanded External Services section in readme. Removed inline `<style>`/`<script>` blocks from PHP output (proper enqueue). Surface actual provider HTTP status / response body in failure messages. Added "Raw API request / response" panel to Email Log detail modal. Masked-dots placeholder for saved password/API-key fields. Hardened secret redaction (covers `client_secret`, response bodies, malformed-JSON fallback). Defused CSV formula injection on log export. Gated on-load DB schema upgrade to admin/WP-CLI. Added confirmation prompts to destructive WP-CLI commands.

= 1.0.3 =
* Added WP-CLI commands (`wp sendforce test`, `queue`, `log`, `sysinfo`). Added developer hooks (`sendforce_before_send`, `sendforce_after_send`, `sendforce_log_entry`, `sendforce_api_request`, `sendforce_api_response`). Added Developer Debug setting to mirror SMTP/API debug to `debug.log`. Added raw API request/response capture in new `debug_data` log column (with Authorization redaction). Email Log export as CSV/JSON. "Copy Debug Info" button on Email Test screen. Replaced stale "SMTP-Manager" User-Agent on Resend with "SendForce-Mail-Relay".

= 1.0.1 =
* Added Microsoft 365 Graph API and Gmail API support. Added 5 new providers (SendLayer, Mailjet, MailerSend, Mandrill, Resend). Added Zoho ZeptoMail API. Added 3 new notification channels (Microsoft Teams, Google Chat, Custom Webhook). SVG provider logos. Collapsible provider grid. Fixed multiple API mailer bugs (SendLayer field names, Pepipost endpoint, SparkPost CC, SES BCC, MailerSend Reply-To). Added CC/BCC/Reply-To to Elastic Email, SMTP2GO, SMTP.com, SocketLabs, Moosend, Pepipost. Auto-set first connection as Default. Bundled Chart.js locally. Fixed all Plugin Check warnings. Renamed main file to `sendforce-mail-relay.php`.

= 1.0.0 =
* Initial release. SMTP with 18 provider presets. HTTP API for 12 providers (SendGrid, Mailgun, SES, Brevo, SparkPost, Postmark, Elastic Email, SMTP2GO, Pepipost, SMTP.com, SocketLabs, Moosend). From name/email override. Email logging with search/filter. Email queue with exponential backoff retry. Email Test page with live SMTP debug. AES-256-CBC encrypted credential storage. Auto log cleanup. Modern admin dashboard with 7-day send chart.

== Upgrade Notice ==

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

= 1.0.30 =
Two Email Log enhancements: filter by connection (audit one provider at a time) and a one-click "Copy diagnostic snapshot" button on log detail (bundles all the info needed for a support ticket into one clipboard blob). Schema migration adds a `provider` column to the log table — applied automatically on update.

= 1.0.29 =
Fixes (1) stray gray "pill" elements rendering on the What's New tab — a layout glitch introduced in 1.0.28 where literal `<tag>` text inside changelog bullets was interpreted as real HTML — and (2) the WordPress 6.7+ "Translation loading triggered too early" notice caused by translating the cron schedule's display label. Recommended upgrade for all 1.0.28 users.

= 1.0.28 =
UX polish release. Test send shows full provider response inline (with Copy button). HTTPS warning on webhook URL fields. "Welcome" admin notice after update points to the What's New tab. Failure-alert rate limiting (5-min default window per channel) prevents flood pings during burst failures. Plus a new CHANGELOG.md with the complete full-detail release history.

= 1.0.27 =
Small security + privacy release. Adds `manage_options` capability checks to log bulk-delete and queue retry/delete actions (defense-in-depth). Email Log now stores only attachment filenames instead of full filesystem paths to prevent server-layout leakage. No breaking changes.

= 1.0.26 =
Attachments and multipart_text now work in API mode for all 18 supported providers — 1.0.25 covered SES and Gmail, this release adds the remaining 16. Email Queue now records real provider error messages on failed sends (was a generic message), gets a "Process Queue Now" button, and test sends correctly bypass the queue. Plus a new "What's New" admin tab combining the roadmap with a collapsible release-history accordion. No breaking changes.

= 1.0.25 =
Adds attachment support for Amazon SES API mode (previously dropped silently — affected WooCommerce PDF invoices and any plugin-attached files). Also enables the "Multi-Part Plain Text" setting for SES and Gmail API modes (deliverability improvement) and adds RFC 2047 encoding for non-ASCII subjects and sender names. Recommended upgrade if you use SES API mode or send email in non-English languages.

= 1.0.24 =
**Critical OAuth fix.** Resolves the persistent `AADSTS50011` / redirect URI mismatch error when connecting Microsoft 365 or Gmail — the authorize URL was being built without URL-encoding the redirect_uri value, so Microsoft / Google saw it truncated at the first `&`. Strongly recommended upgrade if you use OAuth providers. Also: "Connect with provider" now opens in a popup window so you don't lose your settings page (falls back to full-page redirect when popups are blocked).

= 1.0.23 =
Fixes intermittent Microsoft 365 / Gmail OAuth `AADSTS50011` redirect URI mismatch errors caused by third-party security/caching plugins filtering `admin_url`. Also: Email Log "Next run" timestamp respects your site's date/time format, and the channel-logo SVG attribute whitelist is hardened. Recommended upgrade if you use OAuth (Gmail or M365).

= 1.0.21 =
Email Log gets a "Clean Now" button and an at-a-glance auto-clean status row. Routed emails now show the matching rule in the log. Routing rules pointing at deleted connections now show a warning. Real brand logos for Slack/Discord/Teams/Telegram/Google Chat/Pushover/Webhook on the Alerts tab. Fixes a rare race where saving the Summary Email form could wipe alert channels.

= 1.0.20 =
Fixes admin top-bar layout issues at narrow widths — overlap with the WP admin bar, oversized top gap, and horizontal scroll on mid-size screens.

= 1.0.19 =
Adds Smart Routing Rules — route emails through different connections based on recipient, source plugin (WooCommerce / Contact Form 7 / WPForms / etc.), subject, time, and more. Existing setups keep working unchanged.

= 1.0.18 =
Fixes a false-positive "Connected" indicator on the OAuth panel for Gmail / Microsoft 365 connections.

= 1.0.17 =
Minor data-consistency fix on top of v1.0.16's OAuth release. Safe upgrade.

= 1.0.16 =
Major UX upgrade for Gmail and Microsoft 365: one-click "Connect with provider" OAuth buttons replace the manual refresh-token workflow. Existing setups keep working.

= 1.0.15 =
Provider audit + Moosend HTML rendering fix. Recommended for everyone, especially Moosend users.

= 1.0.14 =
Reliability fix for the Email Queue: rows orphaned in "processing" status by a crashed cron run are now auto-recovered on the next run. Recommended for everyone using the queue.

= 1.0.13 =
Security release: hardens the PHP-mail and SMTP fallback paths against header-injection and path-traversal attacks from malicious upstream wp_mail filters. Sanitizes the fallback-connection label on read.

= 1.0.12 =
Renames "Default Connection" to "Primary Connection" in the admin UI. Fixes the fallback flow so `wp_mail()` returns true when the fallback succeeds, with a clear test-email message naming both connections. Provider status dot is now green/red based on configuration.

= 1.0.11 =
Cleans up how the plugin appears on the WordPress admin Plugins page. The big bold title and description column are no longer cluttered with the long SEO string.

= 1.0.10 =
Activates two previously-orphaned settings (Developer Debug, Max Retry Attempts) by adding their UI controls. Replaces every native <select> with a fully-styled brand-coloured dropdown component. Hardens form validation.

= 1.0.9 =
Refined the WordPress.org listing title to surface "SMTP" inside the brand for better directory search visibility. No code-level changes; in-product branding is unchanged.

= 1.0.8 =
Adds real automatic fallback connection logic — when your primary mail send fails, SendForce retries via the fallback connection automatically. Works across any combination of SMTP and API providers.

= 1.0.7 and earlier =
Initial release through 1.0.7: core SMTP and email-API sending, additional providers (SendLayer, Mailjet, MailerSend, Mandrill, Resend), Microsoft 365 and Gmail API support, WP-CLI commands, developer hooks, log export, and External Services documentation. See CHANGELOG.md for the full detail.
