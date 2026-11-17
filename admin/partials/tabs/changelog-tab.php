<?php
/**
 * Changelog helpers — parse + format readme.txt for inline display.
 *
 * Since 1.0.27 the changelog is rendered as a collapsible accordion inside
 * the "What's New" tab (upcoming-tab.php) rather than as a standalone tab,
 * so users only see one navigation entry. This file contains the parser
 * functions both tabs use. Direct visits to `?tab=changelog` still resolve
 * to the same upcoming-tab.php view (back-compat alias in dashboard-page.php).
 *
 * @package SendForce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and parse the plugin's readme.txt changelog section.
 *
 * Cached per-request via a static. readme.txt is plugin code that doesn't
 * change between requests, so no transient is needed.
 *
 * @return array List of release entries, newest first:
 *               [ ['version' => '1.0.27', 'bullets' => [ 'Bullet 1', … ] ], … ]
 */
if ( ! function_exists( 'sendforce_parse_changelog' ) ) {
	function sendforce_parse_changelog() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		// Prefer CHANGELOG.md when present — it carries the FULL detailed
		// release history. readme.txt's Changelog section is the condensed
		// version shipped to WordPress.org (which enforces a 5,000-word cap
		// on that section). When CHANGELOG.md is missing we fall back to
		// readme.txt so the tab is never empty.
		$md_path     = SENDFORCE_PLUGIN_DIR . 'CHANGELOG.md';
		$readme_path = SENDFORCE_PLUGIN_DIR . 'readme.txt';

		if ( is_readable( $md_path ) ) {
			$cache = sendforce_parse_changelog_from_md( $md_path );
			if ( ! empty( $cache ) ) {
				return $cache;
			}
		}

		$cache = sendforce_parse_changelog_from_readme( $readme_path );
		return $cache;
	}
}

/**
 * Parse a CHANGELOG.md file. Each release starts at a `## X.Y.Z` (or
 * `### X.Y.Z`) heading; only `*`-prefixed lines below it become entries, so
 * the GitHub-only presentation around them — release-date lines, the jump
 * index, `<details>` wrappers — is ignored here by construction.
 *
 * @param string $path Absolute path to CHANGELOG.md.
 * @return array Same shape as sendforce_parse_changelog().
 */
if ( ! function_exists( 'sendforce_parse_changelog_from_md' ) ) {
	function sendforce_parse_changelog_from_md( $path ) {
		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Match a version heading. Accepted forms:
		// `## 1.2.1` / `### 1.0.40` — current layout (### for releases folded
		// into the collapsible "Earlier releases" block on GitHub), and
		// `## = 1.2.1 =` — the original readme-style form, kept so an older
		// file still parses. The heading must END at the version: a heading
		// like "## Earlier releases (1.0.x)" is a section title, not a release.
		$pattern = '/^#{2,3}[ \t]*(?:=[ \t]*)?([0-9]+(?:\.[0-9]+){1,2})(?:[ \t]*=)?[ \t]*$/m';
		if ( ! preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$releases = array();
		$count    = count( $matches[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$version = $matches[1][ $i ][0];
			$start   = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
			$end     = ( $i + 1 < $count ) ? $matches[0][ $i + 1 ][1] : strlen( $contents );
			$body    = trim( substr( $contents, $start, $end - $start ) );

			$bullets = array();
			foreach ( explode( "\n", $body ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				// Skip section sub-headings like `**Sending — …**` so they
				// don't pollute the bullet list; only literal `*`-prefixed
				// bullets become entries.
				if ( 0 !== strpos( $line, '*' ) ) {
					continue;
				}
				// Skip ** wrapped bold lines that don't have a leading bullet.
				if ( preg_match( '/^\*\*[^*]+\*\*$/', $line ) ) {
					continue;
				}
				$bullets[] = ltrim( $line, '* ' );
			}

			$releases[] = array(
				'version' => $version,
				'bullets' => $bullets,
			);
		}

		return $releases;
	}
}

/**
 * Legacy readme.txt parser — kept as a fallback when CHANGELOG.md is missing.
 *
 * @param string $path Absolute path to readme.txt.
 * @return array
 */
if ( ! function_exists( 'sendforce_parse_changelog_from_readme' ) ) {
	function sendforce_parse_changelog_from_readme( $path ) {
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! preg_match( '/==\s*Changelog\s*==\s*\n(.+?)(?:\n==\s|\z)/s', $contents, $m ) ) {
			return array();
		}
		$block = $m[1];

		$pattern = '/^=\s*([0-9]+(?:\.[0-9]+){1,2})\s*=\s*$/m';
		if ( ! preg_match_all( $pattern, $block, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$releases = array();
		$count    = count( $matches[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$version = $matches[1][ $i ][0];
			$start   = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
			$end     = ( $i + 1 < $count ) ? $matches[0][ $i + 1 ][1] : strlen( $block );
			$body    = trim( substr( $block, $start, $end - $start ) );

			$bullets = array();
			foreach ( explode( "\n", $body ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( 0 === strpos( $line, '*' ) ) {
					$line = ltrim( $line, '* ' );
				}
				$bullets[] = $line;
			}

			$releases[] = array(
				'version' => $version,
				'bullets' => $bullets,
			);
		}

		return $releases;
	}
}

/**
 * Render readme.txt's inline formatting safely:
 *  - `**bold**`  → <strong>
 *  - `` `code` `` → <code>
 *
 * IMPORTANT: HTML-encode the raw line BEFORE applying the markdown rules.
 * Without this, any literal `<` or `>` inside a bullet (very common in
 * technical changelogs — e.g. `[HTTP <code>] <body>`) gets interpreted by
 * the browser as a real HTML tag, producing stray empty `<code>` elements
 * that float at the page level as gray pills. After escaping, those `<`/`>`
 * become `&lt;`/`&gt;`, then `<strong>` and `<code>` are added by the regex
 * as the only HTML tags in the output — guaranteed balanced and contained.
 *
 * @param string $line Raw line text.
 * @return string Safe HTML.
 */
if ( ! function_exists( 'sendforce_format_changelog_line' ) ) {
	function sendforce_format_changelog_line( $line ) {
		// Step 1: escape everything (< → &lt;, > → &gt;, & → &amp;, etc.)
		$safe = esc_html( (string) $line );
		// Step 2: re-introduce ONLY the markdown-derived tags we control.
		$safe = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe );
		$safe = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $safe );
		return $safe;
	}
}
