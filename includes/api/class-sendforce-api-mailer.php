<?php
/**
 * Base API mailer class.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SendForce_API_Mailer
 *
 * Abstract base class for all API-based email providers.
 * Intercepts wp_mail via pre_wp_mail filter and sends via HTTP API.
 */
abstract class SendForce_API_Mailer {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Last HTTP request/response captured by make_request().
	 * Used by SendForce_Mailer::send_via_api() to forward debug data to the
	 * email log (when developer_debug is enabled).
	 *
	 * @var array
	 */
	protected $last_debug = array();

	/**
	 * From address/name chosen for the current email by the code that sent
	 * it (a From: header or the wp_mail_from / wp_mail_from_name filters), set
	 * by apply_message_from(). Empty when nothing chose one.
	 *
	 * @var string
	 */
	protected $message_from_email = '';

	/**
	 * See $message_from_email.
	 *
	 * @var string
	 */
	protected $message_from_name = '';

	/**
	 * True for providers that can only send as the signed-in mailbox
	 * (Microsoft 365 rejects any other From with "SendAs denied"; Gmail
	 * rewrites it). Those keep using Sender Identity / the admin email and
	 * ignore a From chosen by the sending plugin.
	 *
	 * @var bool
	 */
	protected $from_bound_to_account = false;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the captured request/response payload from the most recent send.
	 *
	 * @return array
	 */
	public function get_last_debug() {
		return $this->last_debug;
	}

	/**
	 * Send an email via the provider API.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Subject.
	 * @param string       $message     Message body.
	 * @param string|array $headers     Headers.
	 * @param array        $attachments Attachments.
	 * @return bool True on success, false on failure.
	 */
	abstract public function send( $to, $subject, $message, $headers = '', $attachments = array() );

	/**
	 * Get the provider name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Get the decrypted API key.
	 *
	 * @return string
	 */
	protected function get_api_key() {
		// trim() catches the very common case where a user copy-pastes a key
		// from a provider dashboard and accidentally grabs trailing whitespace
		// or a stray newline. Some providers (e.g. SendGrid) reject auth
		// headers containing non-printable characters with a generic 401.
		return trim( (string) SendForce_Mailer::decrypt_password( $this->settings['api_key'] ?? '' ) );
	}

	/**
	 * Strip a leading auth-header prefix (e.g. "Bearer ", "Zoho-enczapikey ")
	 * if the user pasted the full Authorization header value from a provider's
	 * docs instead of the bare token. Without this, we'd send headers like
	 * "Authorization: Bearer Bearer SG.xxxxx" which every provider rejects.
	 *
	 * @param string $value  Raw token value (typically from get_api_key()).
	 * @param string $prefix Prefix to strip if present (case-insensitive),
	 *                       e.g. "Bearer " — include the trailing space if
	 *                       the prefix has one in the auth header.
	 * @return string Trimmed token without the prefix.
	 */
	protected function strip_auth_prefix( $value, $prefix ) {
		$value = trim( (string) $value );
		if ( '' !== $prefix && 0 === stripos( $value, $prefix ) ) {
			$value = trim( substr( $value, strlen( $prefix ) ) );
		}
		return $value;
	}

	/**
	 * Get the from email.
	 *
	 * @return string
	 */
	protected function get_from_email() {
		if ( '' !== $this->message_from_email ) {
			return $this->message_from_email;
		}
		return ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_option( 'admin_email' );
	}

	/**
	 * Get the from name.
	 *
	 * @return string
	 */
	protected function get_from_name() {
		if ( '' !== $this->message_from_name ) {
			return $this->message_from_name;
		}
		return ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_option( 'blogname' );
	}

	/**
	 * Pick up the From the sending code chose for this email, the way
	 * WordPress core's wp_mail() does for SMTP sends: a From: header, then
	 * the wp_mail_from / wp_mail_from_name filters. SendForce's own Sender
	 * Identity is one of those filters, so when it's set it still wins.
	 *
	 * Before 1.1.2 API sends skipped this entirely and always used Sender
	 * Identity or the admin email, so a From set by WooCommerce or a form
	 * plugin was silently replaced — and the same email got a different From
	 * depending on whether its route used an SMTP or an API connection.
	 *
	 * When nothing chose a From (no header, and no filter changed core's
	 * wordpress@ default) the previous fallback is kept, so emails that never
	 * had a From keep the address they were sent from before.
	 *
	 * Call once per email, before send(). No-op for mailbox-bound providers.
	 *
	 * @param string|array $headers wp_mail headers.
	 */
	public function apply_message_from( $headers ) {
		$this->message_from_email = '';
		$this->message_from_name  = '';
		if ( $this->from_bound_to_account ) {
			return;
		}

		list( $header_email, $header_name ) = self::parse_from_header( $headers );
		$core_default_email                = self::core_default_from_email();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		$email = (string) apply_filters( 'wp_mail_from', '' !== $header_email ? $header_email : $core_default_email );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		$name = (string) apply_filters( 'wp_mail_from_name', '' !== $header_name ? $header_name : 'WordPress' );

		// Chosen = set in a header, or a filter replaced core's default.
		// Anything that isn't a valid address falls back as before.
		if ( ( '' !== $header_email || $email !== $core_default_email ) && is_email( $email ) ) {
			$this->message_from_email = $email;
		}
		if ( '' !== $header_name || 'WordPress' !== $name ) {
			$this->message_from_name = trim( str_replace( array( "\r", "\n", "\0" ), '', wp_strip_all_tags( $name ) ) );
		}
	}

	/**
	 * Read the From: header the same way core's wp_mail() does.
	 *
	 * @param string|array $headers wp_mail headers.
	 * @return array [ email, name ] — empty strings when absent.
	 */
	protected static function parse_from_header( $headers ) {
		if ( empty( $headers ) ) {
			return array( '', '' );
		}
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}
		$email = '';
		$name  = '';
		foreach ( $headers as $header ) {
			if ( false === strpos( (string) $header, ':' ) ) {
				continue;
			}
			list( $field, $content ) = explode( ':', (string) $header, 2 );
			if ( 'from' !== strtolower( trim( $field ) ) ) {
				continue;
			}
			$content     = trim( $content );
			$bracket_pos = strpos( $content, '<' );
			if ( false !== $bracket_pos ) {
				if ( $bracket_pos > 0 ) {
					$name = trim( str_replace( '"', '', substr( $content, 0, $bracket_pos ) ) );
				}
				$email = trim( str_replace( '>', '', substr( $content, $bracket_pos + 1 ) ) );
			} elseif ( '' !== $content ) {
				$email = $content;
			}
		}
		return array( $email, $name );
	}

	/**
	 * Core's default From when nothing sets one: wordpress@ + the site's host
	 * without "www.". Must match core so a wp_mail_from filter that compares
	 * against it behaves the same here as in an SMTP send.
	 *
	 * @return string
	 */
	protected static function core_default_from_email() {
		$host = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		return 'wordpress@' . $host;
	}

	/**
	 * Normalize recipients to array.
	 *
	 * @param string|array $to Recipients.
	 * @return array
	 */
	protected function normalize_to( $to ) {
		if ( is_string( $to ) ) {
			$to = explode( ',', $to );
		}
		return array_map( 'trim', $to );
	}

	/**
	 * Prepare attachment files into a normalized array each provider can map
	 * to its own JSON schema. Reads each path off disk, base64-encodes it,
	 * looks up its MIME type, and returns the safe-to-serialize structure.
	 *
	 * Unreadable / missing files are skipped silently — wp_mail()'s own
	 * attachment handling does the same so we match that behaviour.
	 *
	 * @param array $attachments List of absolute file paths from wp_mail().
	 * @return array Each entry: [
	 *     'filename'      => 'invoice.pdf',
	 *     'content_b64'   => '<base64 with no line breaks>',
	 *     'mime'          => 'application/pdf',
	 *     'size'          => 12345,  // bytes
	 * ]
	 */
	protected function prepare_attachments( $attachments ) {
		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}
		$out = array();
		foreach ( $attachments as $path ) {
			if ( ! is_string( $path ) || ! is_readable( $path ) ) {
				continue;
			}
			$bytes = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- @ to skip unreadable files cleanly.
			if ( false === $bytes ) {
				continue;
			}
			$filename = basename( $path );
			$ftype    = wp_check_filetype( $filename );
			$mime     = ! empty( $ftype['type'] ) ? $ftype['type'] : 'application/octet-stream';
			$out[]    = array(
				'filename'    => $filename,
				'content_b64' => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- API providers require base64.
				'mime'        => $mime,
				'size'        => strlen( $bytes ),
			);
		}
		return $out;
	}

	/**
	 * Prepare attachments with raw bytes (no base64) for providers that need
	 * multipart/form-data uploads (e.g. Mailgun /messages endpoint).
	 *
	 * @param array $attachments File paths.
	 * @return array Each entry: ['filename' => x, 'raw' => bytes, 'mime' => z].
	 */
	protected function prepare_attachments_raw( $attachments ) {
		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}
		$out = array();
		foreach ( $attachments as $path ) {
			if ( ! is_string( $path ) || ! is_readable( $path ) ) {
				continue;
			}
			$bytes = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $bytes ) {
				continue;
			}
			$filename = basename( $path );
			$ftype    = wp_check_filetype( $filename );
			$out[]    = array(
				'filename' => $filename,
				'raw'      => $bytes,
				'mime'     => ! empty( $ftype['type'] ) ? $ftype['type'] : 'application/octet-stream',
			);
		}
		return $out;
	}

	/**
	 * Build a multipart/form-data request body for providers like Mailgun that
	 * require it for attachments. Returns [body, content_type] so the caller
	 * can pass them to make_request() / wp_remote_post().
	 *
	 * @param array $fields Scalar form fields, or arrays (e.g. multiple 'to'
	 *                      values get one part each).
	 * @param array $files  Files as returned by prepare_attachments_raw().
	 *                      Each rendered as a part with field name "attachment"
	 *                      (Mailgun convention — pass through as-is for other
	 *                      providers using different field names).
	 * @param string $file_field_name The form field name for files (default
	 *                                "attachment" which Mailgun uses).
	 * @return array ['body' => string, 'content_type' => string]
	 */
	protected function build_multipart_form_data( $fields, $files = array(), $file_field_name = 'attachment' ) {
		$boundary = '----=_SFmpb_' . wp_generate_password( 16, false, false );
		$body     = '';

		foreach ( $fields as $name => $value ) {
			$values = is_array( $value ) ? $value : array( $value );
			foreach ( $values as $v ) {
				$body .= "--{$boundary}\r\n";
				$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
				$body .= (string) $v . "\r\n";
			}
		}

		foreach ( $files as $f ) {
			$body .= "--{$boundary}\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $file_field_name . '"; '
				. $this->content_disposition_filename( (string) $f['filename'] ) . "\r\n";
			$body .= 'Content-Type: ' . $f['mime'] . "\r\n\r\n";
			$body .= $f['raw'] . "\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		return array(
			'body'         => $body,
			'content_type' => 'multipart/form-data; boundary=' . $boundary,
		);
	}

	/**
	 * Build the filename portion of a Content-Disposition header safely.
	 *
	 * Two problems this solves:
	 *  1. Header injection — a filename containing CR/LF could otherwise inject
	 *     arbitrary MIME headers into the multipart body. We strip them.
	 *  2. Non-ASCII filenames — a raw UTF-8 name in the legacy quoted
	 *     `filename="..."` parameter arrives as mojibake (or is rejected) at
	 *     some providers. Per RFC 5987/6266 we emit a sanitized ASCII fallback
	 *     in `filename="..."` AND an RFC 5987 `filename*=UTF-8''...` parameter
	 *     that modern parsers prefer, recovering the real name.
	 *
	 * @param string $filename Raw (possibly UTF-8) attachment filename.
	 * @return string e.g. filename="invoice.pdf" or
	 *                filename="re_sume_.pdf"; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf
	 */
	protected function content_disposition_filename( $filename ) {
		// Drop CR/LF outright — never allowed in a header (injection guard).
		$filename = str_replace( array( "\r", "\n" ), '', $filename );
		if ( '' === $filename ) {
			$filename = 'attachment';
		}

		// ASCII fallback for the legacy quoted-string parameter: replace any
		// byte outside printable ASCII, plus the quote/backslash that would
		// break the quoted string, with an underscore.
		$ascii = preg_replace( '/[^\x20-\x7E]/', '_', $filename );
		$ascii = str_replace( array( '"', '\\' ), '_', $ascii );
		if ( '' === $ascii ) {
			$ascii = 'attachment';
		}

		$disposition = 'filename="' . $ascii . '"';

		// If the original carried non-ASCII bytes, add the RFC 5987 form so
		// compliant parsers recover the true UTF-8 name. rawurlencode over-
		// encodes a few unreserved chars (~ ! etc.) which RFC 5987 permits.
		if ( preg_match( '/[\x80-\xFF]/', $filename ) ) {
			$disposition .= "; filename*=UTF-8''" . rawurlencode( $filename );
		}

		return $disposition;
	}

	/**
	 * When multipart_text is on and the message is HTML, return the derived
	 * plain-text alternative. Otherwise null. JSON-API providers that have
	 * separate text/HTML body fields (SendGrid `content[]`, Postmark
	 * `TextBody`+`HtmlBody`, Brevo `textContent`+`htmlContent`, Mailjet
	 * `TextPart`+`HTMLPart`, etc.) call this to populate the text part.
	 *
	 * @param string $html_message HTML body of the message.
	 * @param array  $parsed       parse_headers() output, used to detect HTML.
	 * @return string|null Plain-text version, or null if not applicable.
	 */
	protected function get_plain_text_alt( $html_message, $parsed ) {
		$is_html = isset( $parsed['content_type'] ) && 'text/html' === $parsed['content_type'];
		if ( ! $is_html || empty( $this->settings['multipart_text'] ) ) {
			return null;
		}
		return $this->html_to_plain( $html_message );
	}

	/**
	 * RFC 2047 encode a header value if it contains non-ASCII characters.
	 * Required for headers that may contain UTF-8 like Subject and From-name.
	 * Returns the value as-is when it's pure ASCII so headers stay readable
	 * for the common case.
	 *
	 * @param string $value Raw header value.
	 * @return string Either the original value or `=?UTF-8?B?<base64>?=`.
	 */
	protected function encode_header( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		// Strip CR/LF/NUL first — a newline inside a header value would let a
		// crafted subject/from-name inject additional MIME headers (Bcc, extra
		// To, a forged body) into the raw message. Newlines are never valid
		// inside a single RFC 2822 header value.
		$value = str_replace( array( "\r", "\n", "\0" ), '', $value );
		if ( '' === $value ) {
			return '';
		}
		// Any byte >= 0x80 means non-ASCII. mb_check_encoding could be used
		// but a regex is faster and avoids the dependency.
		if ( preg_match( '/[\x80-\xFF]/', $value ) ) {
			return '=?UTF-8?B?' . base64_encode( $value ) . '?='; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 2047 mandates base64.
		}
		return $value;
	}

	/**
	 * Strip CR/LF/NUL from an address-style header value (From email, To, Cc,
	 * Bcc, Reply-To). These values don't pass through encode_header(), so they
	 * are sanitized here to prevent MIME header injection.
	 *
	 * @param string $value Raw address value.
	 * @return string
	 */
	protected function header_addr( $value ) {
		return str_replace( array( "\r", "\n", "\0" ), '', (string) $value );
	}

	/**
	 * Best-effort HTML → plain text conversion for the multipart/alternative
	 * text/plain part. Not a full Markdown-style render — just preserves
	 * paragraph and line-break structure so plain-text clients show
	 * something readable rather than the user's whole HTML markup.
	 *
	 * @param string $html HTML body.
	 * @return string Plain-text approximation.
	 */
	protected function html_to_plain( $html ) {
		$text = (string) $html;
		// Insert newlines for common block elements so they survive tag-stripping.
		$text = preg_replace( '#<br\s*/?>#i', "\n", $text );
		$text = preg_replace( '#</p\s*>#i', "\n\n", $text );
		$text = preg_replace( '#</li\s*>#i', "\n", $text );
		$text = preg_replace( '#</h[1-6]\s*>#i', "\n\n", $text );
		// Strip remaining tags (also drops <script> / <style> contents).
		// IMPORTANT: pass false for $remove_breaks — passing true collapses
		// every whitespace run (including the \n\n we just inserted for
		// paragraph breaks) into a single space, defeating the purpose.
		$text = wp_strip_all_tags( $text, false );
		// Decode entities so &amp; → & etc.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Collapse 3+ newlines to 2.
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Build a single MIME body part (Content-Type + Content-Transfer-Encoding
	 * headers + base64-encoded payload). Used by build_raw_mime() for both
	 * the message body and attachment parts.
	 *
	 * @param string $content_type  e.g. "text/html; charset=UTF-8".
	 * @param string $payload       Raw bytes (will be base64-encoded).
	 * @param array  $extra_headers Additional headers as ['Header-Name' => 'value'].
	 * @return string MIME part text (no leading boundary line).
	 */
	private function mime_part( $content_type, $payload, $extra_headers = array() ) {
		$out  = 'Content-Type: ' . $content_type . "\r\n";
		$out .= "Content-Transfer-Encoding: base64\r\n";
		foreach ( $extra_headers as $hname => $hvalue ) {
			$out .= $hname . ': ' . $hvalue . "\r\n";
		}
		$out .= "\r\n";
		$out .= chunk_split( base64_encode( (string) $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 transfer encoding required by MIME.
		return $out;
	}

	/**
	 * Build an attachment MIME part from a file path. Skips non-existent or
	 * unreadable files silently — wp_mail() does the same.
	 *
	 * @param string $path Absolute file path.
	 * @return string|null MIME part, or null when file is unreadable.
	 */
	private function attachment_part( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$filename = basename( $path );
		$ftype    = wp_check_filetype( $filename );
		$mime     = ! empty( $ftype['type'] ) ? $ftype['type'] : 'application/octet-stream';
		$bytes    = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $bytes ) {
			return null;
		}

		// Encode the filename safely for RFC 2822 MIME. Strip CR/LF (header
		// injection guard), provide an ASCII fallback in the legacy quoted
		// params, and — when the name has non-ASCII bytes — add the RFC 2231
		// `name*`/`filename*` extended params so UTF-8 filenames (WooCommerce
		// invoices, uploads named in other scripts) arrive intact instead of
		// as mojibake. Gmail, Outlook and Apple Mail all honour filename*.
		$clean_name = str_replace( array( "\r", "\n" ), '', $filename );
		if ( '' === $clean_name ) {
			$clean_name = 'attachment';
		}
		$ascii_name = str_replace( array( '"', '\\' ), '_', preg_replace( '/[^\x20-\x7E]/', '_', $clean_name ) );
		if ( '' === $ascii_name ) {
			$ascii_name = 'attachment';
		}

		$ct_params = '; name="' . $ascii_name . '"';
		$cd_value  = 'attachment; filename="' . $ascii_name . '"';
		if ( preg_match( '/[\x80-\xFF]/', $clean_name ) ) {
			$encoded    = rawurlencode( $clean_name );
			$ct_params .= "; name*=UTF-8''" . $encoded;
			$cd_value  .= "; filename*=UTF-8''" . $encoded;
		}

		return $this->mime_part(
			$mime . $ct_params,
			$bytes,
			array( 'Content-Disposition' => $cd_value )
		);
	}

	/**
	 * Build a raw RFC 2822 message suitable for providers that require it
	 * (Amazon SES SendEmail with Raw content, Gmail API users.messages.send).
	 *
	 * Handles:
	 *  - RFC 2047 encoding of Subject and From-name when they contain UTF-8
	 *  - multipart/alternative when the message is HTML and the
	 *    `multipart_text` setting is enabled (auto-derives a text/plain
	 *    fallback via html_to_plain())
	 *  - multipart/mixed wrapping when there are attachments
	 *  - Bcc inclusion controlled per-provider (SES handles Bcc via the
	 *    Destination API field, so Bcc must NOT appear in the MIME headers;
	 *    Gmail extracts Bcc from the raw MIME, so it must appear there).
	 *
	 * @param array $args {
	 *   @type string $from_email
	 *   @type string $from_name
	 *   @type array  $to            Recipient email addresses.
	 *   @type array  $cc            Optional.
	 *   @type array  $bcc           Optional.
	 *   @type string $reply_to      Optional.
	 *   @type string $subject
	 *   @type string $message       Body text or HTML.
	 *   @type string $content_type  'text/html' | 'text/plain'.
	 *   @type array  $attachments   Optional file paths.
	 *   @type bool   $include_bcc   Whether to include Bcc: header (default true).
	 * }
	 * @return string Full raw RFC 2822 message including headers.
	 */
	protected function build_raw_mime( $args ) {
		$args = array_merge(
			array(
				'from_email'   => '',
				'from_name'    => '',
				'to'           => array(),
				'cc'           => array(),
				'bcc'          => array(),
				'reply_to'     => '',
				'subject'      => '',
				'message'      => '',
				'content_type' => 'text/plain',
				'attachments'  => array(),
				'include_bcc'  => true,
			),
			$args
		);

		$is_html         = 'text/html' === $args['content_type'];
		$multipart_alt   = $is_html && ! empty( $this->settings['multipart_text'] );
		$has_attachments = ! empty( $args['attachments'] );

		// ---- Top-level headers (everything except Content-Type) ----
		$from_name  = $this->encode_header( (string) $args['from_name'] );
		$from_email = $this->header_addr( (string) $args['from_email'] );
		$from       = $from_name ? ( $from_name . ' <' . $from_email . '>' ) : $from_email;

		$headers  = 'From: ' . $from . "\r\n";
		$headers .= 'To: ' . implode( ', ', array_map( array( $this, 'header_addr' ), (array) $args['to'] ) ) . "\r\n";
		if ( ! empty( $args['cc'] ) ) {
			$headers .= 'Cc: ' . implode( ', ', array_map( array( $this, 'header_addr' ), (array) $args['cc'] ) ) . "\r\n";
		}
		if ( $args['include_bcc'] && ! empty( $args['bcc'] ) ) {
			$headers .= 'Bcc: ' . implode( ', ', array_map( array( $this, 'header_addr' ), (array) $args['bcc'] ) ) . "\r\n";
		}
		if ( ! empty( $args['reply_to'] ) ) {
			$headers .= 'Reply-To: ' . $this->header_addr( (string) $args['reply_to'] ) . "\r\n";
		}
		$headers .= 'Subject: ' . $this->encode_header( (string) $args['subject'] ) . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";

		// ---- Body assembly ----
		$mixed_boundary = '----=_SF_mix_' . wp_generate_password( 16, false, false );
		$alt_boundary   = '----=_SF_alt_' . wp_generate_password( 16, false, false );

		// Helper: build the "message body" section (either single part or
		// multipart/alternative if multipart_text is on).
		$build_message_section = function () use ( $args, $is_html, $multipart_alt, $alt_boundary ) {
			if ( $multipart_alt ) {
				$plain = $this->html_to_plain( $args['message'] );
				$out   = "--{$alt_boundary}\r\n";
				$out  .= $this->mime_part( 'text/plain; charset=UTF-8', $plain );
				$out  .= "\r\n--{$alt_boundary}\r\n";
				$out  .= $this->mime_part( 'text/html; charset=UTF-8', $args['message'] );
				$out  .= "\r\n--{$alt_boundary}--\r\n";
				return array(
					'headers' => 'Content-Type: multipart/alternative; boundary="' . $alt_boundary . '"' . "\r\n",
					'body'    => $out,
				);
			}
			$type = $is_html ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
			return array(
				'headers' => 'Content-Type: ' . $type . "\r\nContent-Transfer-Encoding: base64\r\n",
				'body'    => chunk_split( base64_encode( (string) $args['message'] ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 transfer encoding required by MIME.
			);
		};

		if ( $has_attachments ) {
			// multipart/mixed wraps the message section + each attachment.
			$msg_section = $build_message_section();
			$headers    .= 'Content-Type: multipart/mixed; boundary="' . $mixed_boundary . '"' . "\r\n";

			$body  = "\r\n--{$mixed_boundary}\r\n";
			$body .= $msg_section['headers'] . "\r\n" . $msg_section['body'];

			foreach ( $args['attachments'] as $att ) {
				$apart = $this->attachment_part( $att );
				if ( null === $apart ) {
					continue;
				}
				$body .= "\r\n--{$mixed_boundary}\r\n";
				$body .= $apart;
			}
			$body .= "\r\n--{$mixed_boundary}--\r\n";
			return $headers . $body;
		}

		$msg_section = $build_message_section();
		$headers    .= $msg_section['headers'];
		return $headers . "\r\n" . $msg_section['body'];
	}

	/**
	 * Parse headers into an associative array.
	 *
	 * @param string|array $headers Raw headers.
	 * @return array ['content_type' => '', 'cc' => [], 'bcc' => [], 'reply_to' => '']
	 */
	protected function parse_headers( $headers ) {
		$parsed = array(
			'content_type' => 'text/plain',
			'cc'           => array(),
			'bcc'          => array(),
			'reply_to'     => '',
		);

		if ( empty( $headers ) ) {
			return $parsed;
		}

		if ( is_string( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		foreach ( $headers as $header ) {
			if ( false === strpos( $header, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $header, 2 );
			$name  = strtolower( trim( $name ) );
			$value = trim( $value );

			switch ( $name ) {
				case 'content-type':
					if ( false !== strpos( $value, 'text/html' ) ) {
						$parsed['content_type'] = 'text/html';
					}
					break;
				case 'cc':
					$parsed['cc'] = array_merge( $parsed['cc'], array_map( 'trim', explode( ',', $value ) ) );
					break;
				case 'bcc':
					$parsed['bcc'] = array_merge( $parsed['bcc'], array_map( 'trim', explode( ',', $value ) ) );
					break;
				case 'reply-to':
					$parsed['reply_to'] = $value;
					break;
			}
		}

		return $parsed;
	}

	/**
	 * Make an HTTP POST request.
	 *
	 * @param string $url     API URL.
	 * @param array  $headers HTTP headers.
	 * @param mixed  $body    Request body (string or array).
	 * @return array|WP_Error Response or error.
	 */
	protected function make_request( $url, $headers, $body ) {
		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => is_array( $body ) ? wp_json_encode( $body ) : $body,
			'timeout' => 30,
		);

		$provider = $this->get_name();

		/**
		 * Fires before any provider API request is sent.
		 *
		 * @param string $url      Endpoint URL.
		 * @param array  $args     wp_remote_post arguments (headers, body, timeout).
		 * @param string $provider Provider display name.
		 */
		do_action( 'sendforce_api_request', $url, $args, $provider );

		$response = wp_remote_post( $url, $args );

		/**
		 * Fires after a provider API response (success or WP_Error) is received.
		 *
		 * @param string         $url      Endpoint URL.
		 * @param array|WP_Error $response wp_remote_post response.
		 * @param string         $provider Provider display name.
		 */
		do_action( 'sendforce_api_response', $url, $response, $provider );

		// Capture for the email log + WP_DEBUG_LOG mirror, with secrets redacted.
		$safe_headers = $this->redact_headers( $headers );
		$resp_code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$resp_body    = is_wp_error( $response )
			? $response->get_error_message()
			: (string) wp_remote_retrieve_body( $response );

		$safe_req_body  = is_string( $args['body'] ) ? $this->redact_body( $args['body'] ) : '';
		// Some providers (Mandrill, Mailgun verbose errors, AWS SES debug responses)
		// echo the original request payload back in the error body, which would
		// re-leak the API key. Always redact the response body too.
		$safe_resp_body = $this->redact_body( $resp_body );

		$this->last_debug = array(
			'provider'    => $provider,
			'url'         => $url,
			'req_headers' => $safe_headers,
			'req_body'    => mb_strimwidth( $safe_req_body, 0, 4000, '…' ),
			'resp_code'   => $resp_code,
			'resp_body'   => mb_strimwidth( $safe_resp_body, 0, 4000, '…' ),
			'timestamp'   => current_time( 'mysql' ),
		);

		SendForce_Mailer::log_debug( "API {$provider} → {$resp_code}", $this->last_debug );

		return $response;
	}

	/**
	 * Redact secrets from request headers before logging.
	 *
	 * @param array $headers Request headers.
	 * @return array
	 */
	protected function redact_headers( $headers ) {
		$out = array();
		foreach ( (array) $headers as $name => $value ) {
			$lower = strtolower( (string) $name );
			if ( in_array( $lower, array( 'authorization', 'x-api-key', 'api-key', 'x-server-api-key', 'x-postmark-server-token' ), true ) ) {
				$out[ $name ] = '[REDACTED]';
			} else {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Redact common secret fields from a JSON-encoded request body.
	 * Some providers (Mandrill, MailerSend, SendGrid v2, etc.) embed the
	 * API key inside the JSON payload rather than the Authorization header,
	 * so header redaction alone is insufficient.
	 *
	 * @param string $body Raw body string.
	 * @return string Body with sensitive values replaced by [REDACTED].
	 */
	protected function redact_body( $body ) {
		if ( ! is_string( $body ) || '' === $body ) {
			return '';
		}

		$secret_keys     = array( 'key', 'api_key', 'apikey', 'api_token', 'token', 'password', 'secret', 'access_token', 'serverid', 'server_id', 'client_secret' );
		$secret_patterns = array(
			'/\b(' . implode( '|', $secret_keys ) . ')\s*[:=]\s*"[^"]+"/i',
			'/\b(' . implode( '|', $secret_keys ) . ')=([^&\s]+)/i',
		);

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			// Body wasn't valid JSON — fall back to a regex sweep so a malformed
			// or non-JSON payload still gets secrets stripped before it lands in the log.
			return preg_replace( $secret_patterns, '$1: "[REDACTED]"', $body );
		}

		array_walk_recursive(
			$decoded,
			function ( &$value, $field ) use ( $secret_keys ) {
				if ( in_array( strtolower( (string) $field ), $secret_keys, true ) && ! empty( $value ) ) {
					$value = '[REDACTED]';
				}
			}
		);
		return wp_json_encode( $decoded );
	}

	/**
	 * Check if an HTTP response indicates success.
	 *
	 * @param array|WP_Error $response  HTTP response.
	 * @param array          $ok_codes  Acceptable HTTP status codes.
	 * @return bool
	 */
	protected function is_success( $response, $ok_codes = array( 200, 201, 202 ) ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return in_array( $code, $ok_codes, true );
	}

	/**
	 * Get error message from response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string
	 */
	protected function get_error( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );
		return sprintf( 'HTTP %d: %s', $code, substr( $body, 0, 300 ) );
	}

	/**
	 * Get the API mailer instance for the current provider.
	 *
	 * @param string $provider Provider key.
	 * @param array  $settings Plugin settings.
	 * @return SendForce_API_Mailer|null
	 */
	public static function get_instance( $provider, $settings ) {
		$map = array(
			'sendgrid'     => 'SendForce_API_Sendgrid',
			'mailgun'      => 'SendForce_API_Mailgun',
			'brevo'        => 'SendForce_API_Brevo',
			'sparkpost'    => 'SendForce_API_Sparkpost',
			'postmark'     => 'SendForce_API_Postmark',
			'ses'          => 'SendForce_API_SES',
			'elasticemail' => 'SendForce_API_Elasticemail',
			'smtp2go'      => 'SendForce_API_SMTP2GO',
			'pepipost'     => 'SendForce_API_Pepipost',
			'smtpcom'      => 'SendForce_API_SMTPcom',
			'socketlabs'   => 'SendForce_API_Socketlabs',
			'moosend'      => 'SendForce_API_Moosend',
			'outlook'      => 'SendForce_API_Outlook',
			'gmail'        => 'SendForce_API_Gmail',
			'sendlayer'    => 'SendForce_API_Sendlayer',
			'mailjet'      => 'SendForce_API_Mailjet',
			'mailersend'   => 'SendForce_API_Mailersend',
			'mandrill'     => 'SendForce_API_Mandrill',
			'resend'       => 'SendForce_API_Resend',
			'zoho'         => 'SendForce_API_Zoho',
		);

		if ( ! isset( $map[ $provider ] ) ) {
			return null;
		}

		$class = $map[ $provider ];
		if ( class_exists( $class ) ) {
			return new $class( $settings );
		}

		return null;
	}
}
