<?php
/**
 * Copyright block helpers and render.
 *
 * @package Aggressive_Apparel
 * @since 1.2.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Blocks;

use Aggressive_Apparel\Core\Legal_Identity;

/**
 * Copyright notice formatting and server render.
 */
class Copyright {

	/**
	 * Owner source: site title.
	 */
	public const OWNER_SOURCE_SITE_TITLE = 'site_title';

	/**
	 * Owner source: Settings → General legal name.
	 */
	public const OWNER_SOURCE_LEGAL_NAME = 'legal_name';

	/**
	 * Owner source: custom name.
	 */
	public const OWNER_SOURCE_CUSTOM = 'custom';

	/**
	 * Custom legal-entity attribute value.
	 */
	public const LEGAL_ENTITY_CUSTOM = 'custom';

	/**
	 * Whether Schema.org JSON-LD was already printed this request.
	 *
	 * @var bool
	 */
	private static bool $schema_emitted = false;

	/**
	 * Cached legal-entity presets from JSON.
	 *
	 * @var list<string>|null
	 */
	private static ?array $presets = null;

	/**
	 * Register editor data + hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_data' ) );
	}

	/**
	 * Expose legal identity values to the block editor.
	 *
	 * @return void
	 */
	public static function enqueue_editor_data(): void {
		$data = array(
			'legalName'    => Legal_Identity::get_legal_name(),
			'privacyUrl'   => Legal_Identity::get_privacy_url(),
			'termsUrl'     => Legal_Identity::get_terms_url(),
			'privacyLabel' => __( 'Privacy', 'aggressive-apparel' ),
			'termsLabel'   => __( 'Terms', 'aggressive-apparel' ),
		);

		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			return;
		}

		wp_register_script( 'aggressive-apparel-copyright-data', false, array(), AGGRESSIVE_APPAREL_VERSION, true );
		wp_add_inline_script(
			'aggressive-apparel-copyright-data',
			'window.aggressiveApparelCopyright = ' . $json . ';',
			'before'
		);
		wp_enqueue_script( 'aggressive-apparel-copyright-data' );
	}

	/**
	 * Load legal-entity presets from the shared JSON file.
	 *
	 * @return list<string>
	 */
	public static function legal_entity_presets(): array {
		if ( null !== self::$presets ) {
			return self::$presets;
		}

		$paths = array(
			AGGRESSIVE_APPAREL_DIR . '/build/blocks/copyright/legal-entity-presets.json',
			AGGRESSIVE_APPAREL_DIR . '/src/blocks/copyright/legal-entity-presets.json',
		);

		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$data = wp_json_file_decode( $path, array( 'associative' => true ) );
			if ( ! is_array( $data ) ) {
				continue;
			}

			$presets = array();
			foreach ( $data as $item ) {
				if ( is_string( $item ) && '' !== $item ) {
					$presets[] = sanitize_text_field( $item );
				}
			}

			if ( array() !== $presets ) {
				self::$presets = array_values( array_unique( $presets ) );
				return self::$presets;
			}
		}

		self::$presets = array(
			'LLC',
			'L.L.C.',
			'Inc.',
			'Corp.',
			'Corporation',
			'Ltd.',
			'LLP',
			'LP',
			'Co.',
			'PLC',
			'GmbH',
		);

		return self::$presets;
	}

	/**
	 * Sanitize a copyright start year to a valid calendar year.
	 *
	 * @param mixed $year         Raw year value from block attributes.
	 * @param int   $current_year Current calendar year.
	 * @return int Year not greater than $current_year.
	 */
	public static function sanitize_year( $year, int $current_year ): int {
		$parsed = absint( $year );

		if ( $parsed < 1000 || $parsed > $current_year ) {
			return $current_year;
		}

		return $parsed;
	}

	/**
	 * Build the year portion of a copyright notice.
	 *
	 * @param bool     $show_start   Whether to show a start–current range.
	 * @param mixed    $start_year   Start year (string or int).
	 * @param string   $separator    Separator between years.
	 * @param int|null $current_year Optional current year override (tests).
	 * @return string Year or year-range string.
	 */
	public static function year_display(
		bool $show_start,
		$start_year,
		string $separator = '–',
		?int $current_year = null
	): string {
		$current = $current_year ?? (int) gmdate( 'Y' );
		$start   = self::sanitize_year( $start_year, $current );

		if ( $show_start && $start < $current ) {
			return (string) $start . $separator . (string) $current;
		}

		return (string) $current;
	}

	/**
	 * Extract the primary (end) year from a year display string for Schema.org.
	 *
	 * @param string $year_display Year or range.
	 * @return string Four-digit year.
	 */
	public static function schema_year( string $year_display ): string {
		if ( preg_match( '/(\d{4})\s*$/', $year_display, $matches ) ) {
			return $matches[1];
		}

		return (string) gmdate( 'Y' );
	}

	/**
	 * Resolve a legal-entity designation from block attributes.
	 *
	 * @param string $legal_entity Selected preset, "custom", or empty.
	 * @param string $custom       Custom designation when $legal_entity is "custom".
	 * @return string Designation string, or empty when none / invalid.
	 */
	public static function resolve_legal_entity( string $legal_entity, string $custom = '' ): string {
		$legal_entity = sanitize_text_field( $legal_entity );

		if ( '' === $legal_entity ) {
			return '';
		}

		if ( self::LEGAL_ENTITY_CUSTOM === $legal_entity ) {
			return sanitize_text_field( $custom );
		}

		if ( ! in_array( $legal_entity, self::legal_entity_presets(), true ) ) {
			return '';
		}

		return $legal_entity;
	}

	/**
	 * Format an owner name with an optional legal-entity designation.
	 *
	 * @param string $owner_name   Base owner / site title.
	 * @param string $legal_entity Selected preset, "custom", or empty.
	 * @param string $custom       Custom designation when $legal_entity is "custom".
	 * @return string Formatted owner string.
	 */
	public static function format_owner(
		string $owner_name,
		string $legal_entity = '',
		string $custom = ''
	): string {
		$base   = trim( sanitize_text_field( $owner_name ) );
		$entity = self::resolve_legal_entity( $legal_entity, $custom );

		if ( '' === $base ) {
			return $entity;
		}

		if ( '' === $entity ) {
			return $base;
		}

		$base_lower   = strtolower( $base );
		$entity_lower = strtolower( $entity );

		if (
			str_ends_with( $base_lower, ', ' . $entity_lower )
			|| str_ends_with( $base_lower, ' ' . $entity_lower )
			|| $base_lower === $entity_lower
		) {
			return $base;
		}

		return $base . ', ' . $entity;
	}

	/**
	 * Resolve owner source from prepared + raw block attributes.
	 *
	 * @param array<string, mixed> $attributes Prepared block attributes.
	 * @param array<string, mixed> $raw_attrs  Raw attrs from parsed block (pre-defaults).
	 * @return string `site_title`, `legal_name`, or `custom`.
	 */
	public static function resolve_owner_source( array $attributes, array $raw_attrs = array() ): string {
		if ( array_key_exists( 'ownerSource', $raw_attrs ) ) {
			return self::normalize_owner_source( sanitize_key( (string) $raw_attrs['ownerSource'] ) );
		}

		if ( array_key_exists( 'useSiteTitle', $raw_attrs ) ) {
			return ! empty( $raw_attrs['useSiteTitle'] )
				? self::OWNER_SOURCE_SITE_TITLE
				: self::OWNER_SOURCE_CUSTOM;
		}

		return self::normalize_owner_source(
			sanitize_key( (string) ( $attributes['ownerSource'] ?? self::OWNER_SOURCE_SITE_TITLE ) )
		);
	}

	/**
	 * Normalize an owner-source value.
	 *
	 * @param string $source Raw source.
	 * @return string `site_title`, `legal_name`, or `custom`.
	 */
	public static function normalize_owner_source( string $source ): string {
		if ( self::OWNER_SOURCE_CUSTOM === $source ) {
			return self::OWNER_SOURCE_CUSTOM;
		}

		if ( self::OWNER_SOURCE_LEGAL_NAME === $source ) {
			return self::OWNER_SOURCE_LEGAL_NAME;
		}

		return self::OWNER_SOURCE_SITE_TITLE;
	}

	/**
	 * Resolve the base owner name before legal-entity formatting.
	 *
	 * @param array<string, mixed> $attributes Prepared block attributes.
	 * @param string               $owner_source Resolved owner source.
	 * @return string
	 */
	public static function resolve_base_owner( array $attributes, string $owner_source ): string {
		$owner_name = sanitize_text_field( (string) ( $attributes['ownerName'] ?? '' ) );
		$site_title = (string) get_bloginfo( 'name', 'display' );

		if ( self::OWNER_SOURCE_LEGAL_NAME === $owner_source ) {
			$legal_name = Legal_Identity::get_legal_name();
			if ( '' !== $legal_name ) {
				return $legal_name;
			}
			return '' !== $site_title ? $site_title : $owner_name;
		}

		if ( self::OWNER_SOURCE_SITE_TITLE === $owner_source || '' === $owner_name ) {
			if ( '' !== $site_title ) {
				return $site_title;
			}
		}

		return $owner_name;
	}

	/**
	 * Allowed legal-link separators.
	 *
	 * @return list<string>
	 */
	public static function legal_link_separators(): array {
		return array( '|', '·', '•', '–', '-', '/' );
	}

	/**
	 * Sanitize a legal-links separator to an allowed value.
	 *
	 * @param string $separator Raw separator.
	 * @return string
	 */
	public static function sanitize_legal_links_separator( string $separator ): string {
		$separator = sanitize_text_field( $separator );
		return in_array( $separator, self::legal_link_separators(), true ) ? $separator : '|';
	}

	/**
	 * Markup for a legal-link separator glyph.
	 *
	 * @param string $separator Separator character.
	 * @return string
	 */
	public static function format_legal_separator_html( string $separator ): string {
		$separator = self::sanitize_legal_links_separator( $separator );

		return sprintf(
			' <span class="wp-block-aggressive-apparel-copyright__legal-sep" aria-hidden="true">%s</span> ',
			esc_html( $separator )
		);
	}

	/**
	 * Build Privacy / Terms link markup.
	 *
	 * @param string $separator Separator between links.
	 * @return string
	 */
	public static function build_legal_links_html( string $separator = '|' ): string {
		$links = array();

		$privacy_url = Legal_Identity::get_privacy_url();
		if ( '' !== $privacy_url ) {
			$links[] = sprintf(
				'<a class="wp-block-aggressive-apparel-copyright__legal-link" href="%1$s">%2$s</a>',
				esc_url( $privacy_url ),
				esc_html__( 'Privacy', 'aggressive-apparel' )
			);
		}

		$terms_url = Legal_Identity::get_terms_url();
		if ( '' !== $terms_url ) {
			$links[] = sprintf(
				'<a class="wp-block-aggressive-apparel-copyright__legal-link" href="%1$s">%2$s</a>',
				esc_url( $terms_url ),
				esc_html__( 'Terms', 'aggressive-apparel' )
			);
		}

		/**
		 * Filters copyright legal link HTML fragments (already escaped anchors).
		 *
		 * @param list<string> $links Privacy / Terms anchors.
		 */
		$links = apply_filters( 'aggressive_apparel_copyright_legal_links', $links );

		if ( ! is_array( $links ) || array() === $links ) {
			return '';
		}

		$safe = array();
		foreach ( $links as $link ) {
			if ( is_string( $link ) && '' !== $link ) {
				$safe[] = $link;
			}
		}

		if ( array() === $safe ) {
			return '';
		}

		$sep_html = self::format_legal_separator_html( $separator );

		return sprintf(
			'<span class="wp-block-aggressive-apparel-copyright__legal-links">%s</span>',
			implode( $sep_html, $safe )
		);
	}

	/**
	 * Build Schema.org JSON-LD for copyright holder / year.
	 *
	 * @param string $owner_name   Formatted owner name.
	 * @param string $year_display Year or range display.
	 * @return string Script tag HTML, or empty when disabled / already emitted.
	 */
	public static function build_schema_html( string $owner_name, string $year_display ): string {
		if ( self::$schema_emitted || '' === $owner_name ) {
			return '';
		}

		$home = home_url( '/' );
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'Organization',
					'@id'   => $home . '#organization',
					'name'  => $owner_name,
					'url'   => $home,
				),
				array(
					'@type'           => 'WebSite',
					'@id'             => $home . '#website',
					'url'             => $home,
					'name'            => (string) get_bloginfo( 'name', 'display' ),
					'copyrightHolder' => array(
						'@id' => $home . '#organization',
					),
					'copyrightYear'   => self::schema_year( $year_display ),
				),
			),
		);

		/**
		 * Filters the copyright Schema.org JSON-LD graph.
		 *
		 * Return an empty array to skip output.
		 *
		 * @param array<string, mixed> $data Schema payload.
		 */
		$data = apply_filters( 'aggressive_apparel_copyright_schema', $data );

		if ( ! is_array( $data ) || array() === $data ) {
			return '';
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return '';
		}

		self::$schema_emitted = true;

		return '<script type="application/ld+json">' . $json . '</script>';
	}

	/**
	 * Reset schema emission flag (tests).
	 *
	 * @return void
	 */
	public static function reset_schema_emitted_for_tests(): void {
		self::$schema_emitted = false;
	}

	/**
	 * Flush cached presets (tests).
	 *
	 * @return void
	 */
	public static function flush_presets_cache_for_tests(): void {
		self::$presets = null;
	}

	/**
	 * Render the copyright block HTML.
	 *
	 * @param array<string, mixed> $attributes Prepared block attributes.
	 * @param \WP_Block|null       $block      Block instance (for raw attr back-compat).
	 * @return string
	 */
	public static function render( array $attributes, $block = null ): string {
		$raw_attrs = array();
		if ( $block instanceof \WP_Block && isset( $block->parsed_block['attrs'] ) && is_array( $block->parsed_block['attrs'] ) ) {
			$raw_attrs = $block->parsed_block['attrs'];
		}

		$owner_source        = self::resolve_owner_source( $attributes, $raw_attrs );
		$legal_entity        = sanitize_text_field( (string) ( $attributes['legalEntity'] ?? '' ) );
		$legal_entity_custom = sanitize_text_field( (string) ( $attributes['legalEntityCustom'] ?? '' ) );
		$show_start          = (bool) ( $attributes['showStartYear'] ?? false );
		$start_year          = $attributes['startYear'] ?? 2024;
		$separator           = sanitize_text_field( (string) ( $attributes['separator'] ?? '–' ) );
		$prefix              = sanitize_text_field( (string) ( $attributes['prefix'] ?? '©' ) );
		$suffix              = (string) ( $attributes['suffix'] ?? '' );
		$text_align          = sanitize_text_field( (string) ( $attributes['textAlign'] ?? '' ) );
		$show_legal_links    = (bool) ( $attributes['showLegalLinks'] ?? false );
		$show_schema         = (bool) ( $attributes['showSchema'] ?? true );
		$legal_links_sep     = self::sanitize_legal_links_separator(
			(string) ( $attributes['legalLinksSeparator'] ?? '|' )
		);
		$current_year        = (int) gmdate( 'Y' );

		$resolved_entity = self::resolve_legal_entity( $legal_entity, $legal_entity_custom );
		$owner_name      = self::format_owner(
			self::resolve_base_owner( $attributes, $owner_source ),
			$legal_entity,
			$legal_entity_custom
		);

		$year_display = self::year_display(
			$show_start,
			$start_year,
			$separator,
			$current_year
		);

		/**
		 * Filters the copyright notice parts before HTML assembly.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $parts {
		 *     Copyright parts.
		 *
		 *     @type string $prefix           Symbol or text before the year.
		 *     @type string $year_display     Year or year range.
		 *     @type string $owner_name       Rights holder name (includes legal entity when set).
		 *     @type string $legal_entity     Resolved legal-entity designation (e.g. LLC).
		 *     @type string $owner_source     Owner source.
		 *     @type string $suffix           Optional rich-text suffix (may contain safe HTML).
		 *     @type string $separator        Year-range separator.
		 *     @type bool   $show_legal_links Whether to append Privacy / Terms links.
		 *     @type bool   $show_schema      Whether to emit Schema.org JSON-LD.
		 * }
		 * @param array<string, mixed> $attributes Block attributes.
		 */
		$parts = apply_filters(
			'aggressive_apparel_copyright_parts',
			array(
				'prefix'                => $prefix,
				'year_display'          => $year_display,
				'owner_name'            => $owner_name,
				'legal_entity'          => $resolved_entity,
				'owner_source'          => $owner_source,
				'suffix'                => $suffix,
				'separator'             => $separator,
				'show_legal_links'      => $show_legal_links,
				'show_schema'           => $show_schema,
				'legal_links_separator' => $legal_links_sep,
			),
			$attributes
		);

		$parts = array(
			'prefix'                => sanitize_text_field( (string) ( $parts['prefix'] ?? $prefix ) ),
			'year_display'          => sanitize_text_field( (string) ( $parts['year_display'] ?? $year_display ) ),
			'owner_name'            => sanitize_text_field( (string) ( $parts['owner_name'] ?? $owner_name ) ),
			'legal_entity'          => sanitize_text_field( (string) ( $parts['legal_entity'] ?? $resolved_entity ) ),
			'owner_source'          => self::normalize_owner_source( (string) ( $parts['owner_source'] ?? $owner_source ) ),
			'suffix'                => (string) ( $parts['suffix'] ?? $suffix ),
			'separator'             => sanitize_text_field( (string) ( $parts['separator'] ?? $separator ) ),
			'show_legal_links'      => (bool) ( $parts['show_legal_links'] ?? $show_legal_links ),
			'show_schema'           => (bool) ( $parts['show_schema'] ?? $show_schema ),
			'legal_links_separator' => self::sanitize_legal_links_separator(
				(string) ( $parts['legal_links_separator'] ?? $legal_links_sep )
			),
		);

		$copyright_text = trim(
			sprintf(
				'%s %s %s',
				$parts['prefix'],
				$parts['year_display'],
				$parts['owner_name']
			)
		);

		$suffix_html = '';
		if ( '' !== $parts['suffix'] ) {
			$suffix_html = sprintf(
				'<span class="wp-block-aggressive-apparel-copyright__suffix">%s</span>',
				wp_kses_post( $parts['suffix'] )
			);
		}

		$legal_links_html = '';
		if ( $parts['show_legal_links'] ) {
			$legal_links_html = self::build_legal_links_html( $parts['legal_links_separator'] );
			if ( '' !== $legal_links_html ) {
				$legal_links_html = self::format_legal_separator_html( $parts['legal_links_separator'] ) . $legal_links_html;
			}
		}

		$extra_attrs = array();
		if ( '' !== $text_align ) {
			$extra_attrs['class'] = 'has-text-align-' . $text_align;
		}

		$html = sprintf(
			'<p %1$s><span class="wp-block-aggressive-apparel-copyright__text">%2$s</span>%3$s%4$s</p>',
			get_block_wrapper_attributes( $extra_attrs ),
			esc_html( $copyright_text ),
			$suffix_html,
			$legal_links_html
		);

		if ( $parts['show_schema'] ) {
			$html .= self::build_schema_html( $parts['owner_name'], $parts['year_display'] );
		}

		/**
		 * Filters the final copyright block HTML.
		 *
		 * @since 1.1.0
		 *
		 * @param string               $html       Rendered HTML.
		 * @param array<string, mixed> $attributes Block attributes.
		 * @param array<string, mixed> $parts      Sanitized copyright parts.
		 */
		return (string) apply_filters( 'aggressive_apparel_copyright_html', $html, $attributes, $parts );
	}
}
