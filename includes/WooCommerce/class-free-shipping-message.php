<?php
/**
 * Free-shipping message wording, templates, and styled segments.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Wording for the free-shipping message and progress bar blocks.
 *
 * Wording resolves in layers, most specific first:
 *
 * 1. Block override — the block's own wording, when "Custom wording for this
 *    block" is on (translated per language by WPML/Polylang via
 *    wpml-config.xml).
 * 2. Legacy emphasis phrase — a block's pre-wording `emphasisText`.
 * 3. Site wording — the two Store Copy fields, shared by every block
 *    (translated by WPML/Polylang string translation).
 * 4. Theme default — gettext templates, translated per locale.
 *
 * Merchants write wording as plain text: `{amount}` for the live amount and
 * `*asterisks*` around highlighted words. Translators get the same sentence
 * as a printf template with `{em}`…`{em_end}` markers (tokens the i18n
 * tooling protects). Both reduce to one template format, which
 * segments() splits for display on the server and buildFreeShippingSegments()
 * in cart-data.ts splits in the browser.
 */
class Free_Shipping_Message {

	/**
	 * Store Copy option: site-wide wording while below the threshold.
	 *
	 * @var string
	 */
	public const PROGRESS_WORDING_OPTION = 'aggressive_apparel_free_shipping_progress_text';

	/**
	 * Store Copy option: site-wide wording once free shipping is unlocked.
	 *
	 * @var string
	 */
	public const UNLOCKED_WORDING_OPTION = 'aggressive_apparel_free_shipping_unlocked_text';

	/**
	 * Wording placeholder for the amount still needed.
	 *
	 * @var string
	 */
	public const AMOUNT_TOKEN = '{amount}';

	/**
	 * Template tokens: printf args, a literal percent, and the
	 * `{em}`…`{em_end}` highlight pair. Mirrored by MESSAGE_TOKEN in
	 * cart-data.ts.
	 *
	 * @var string
	 */
	private const MESSAGE_TOKEN = '/%%|%(?:(\d+)\$)?s|\{em\}|\{em_end\}/';

	/**
	 * Translatable default templates (layer 4).
	 *
	 * Filter: aggressive_apparel_free_shipping_message_i18n
	 *
	 * @return array{
	 *     progressDefault: string,
	 *     progressCustom: string,
	 *     unlockedDefault: string,
	 *     unlockedCustom: string
	 * }
	 */
	public static function get_default_templates(): array {
		$templates = array(
			/* translators: %s: formatted amount the customer still has to spend to get free shipping. {em} and {em_end} wrap the highlighted phrase; keep both. */
			'progressDefault' => __( '%s Away from {em}FREE Shipping{em_end}!', 'aggressive-apparel' ),
			/* translators: 1: formatted amount the customer still has to spend, 2: highlighted free-shipping phrase set in the block. */
			'progressCustom'  => __( '%1$s Away from %2$s!', 'aggressive-apparel' ),
			/* translators: Shown once the cart qualifies for free shipping. {em} and {em_end} wrap the highlighted phrase; keep both. */
			'unlockedDefault' => __( '{em}FREE Shipping{em_end} UNLOCKED!', 'aggressive-apparel' ),
			/* translators: %s: highlighted free-shipping phrase set in the block. */
			'unlockedCustom'  => __( '%s UNLOCKED!', 'aggressive-apparel' ),
		);

		/**
		 * Filter free-shipping message templates (SSR + live JS).
		 *
		 * @param array $templates Message template strings with %s placeholders
		 *                         and optional {em}…{em_end} highlight markers.
		 */
		$filtered = (array) apply_filters( 'aggressive_apparel_free_shipping_message_i18n', $templates );

		return array(
			'progressDefault' => (string) ( $filtered['progressDefault'] ?? $templates['progressDefault'] ),
			'progressCustom'  => (string) ( $filtered['progressCustom'] ?? $templates['progressCustom'] ),
			'unlockedDefault' => (string) ( $filtered['unlockedDefault'] ?? $templates['unlockedDefault'] ),
			'unlockedCustom'  => (string) ( $filtered['unlockedCustom'] ?? $templates['unlockedCustom'] ),
		);
	}

	/**
	 * Templates after site wording (layers 3–4).
	 *
	 * Site wording replaces only the default variants, so a legacy block's
	 * emphasis phrase (layer 2) still wins over it.
	 *
	 * @return array{progressDefault: string, progressCustom: string, unlockedDefault: string, unlockedCustom: string}
	 */
	public static function get_templates(): array {
		$site = self::get_site_wording();

		return self::apply_wording( self::get_default_templates(), $site['progress'], $site['unlocked'], false );
	}

	/**
	 * Interactivity context fields for a block: its templates and phrase.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{emphasisText: string, i18n: array{progressDefault: string, progressCustom: string, unlockedDefault: string, unlockedCustom: string}}
	 */
	public static function block_context( array $attributes ): array {
		$i18n = self::get_templates();

		if ( ! empty( $attributes['useCustomWording'] ) ) {
			$i18n = self::apply_wording(
				$i18n,
				(string) ( $attributes['progressText'] ?? '' ),
				(string) ( $attributes['unlockedText'] ?? '' ),
				true
			);
		}

		return array(
			'emphasisText' => sanitize_text_field( (string) ( $attributes['emphasisText'] ?? '' ) ),
			'i18n'         => $i18n,
		);
	}

	/**
	 * Register the `segments` derived state for a block's store namespace.
	 *
	 * The directive processor renders the segments from each block's own
	 * context; the matching client getter takes over after hydration.
	 *
	 * @param string $store_namespace Interactivity store namespace.
	 * @return void
	 */
	public static function register_segments_state( string $store_namespace ): void {
		wp_interactivity_state(
			$store_namespace,
			array(
				'segments' => static function () use ( $store_namespace ): array {
					return self::segments( wp_interactivity_get_context( $store_namespace ) );
				},
			)
		);
	}

	/**
	 * Saved site wording (layer 3), translated; '' where the default applies.
	 *
	 * An unsaved field keeps the gettext default, so every locale stays
	 * translated until a merchant actually writes wording.
	 *
	 * @return array{progress: string, unlocked: string}
	 */
	public static function get_site_wording(): array {
		$wording = array(
			'progress' => '',
			'unlocked' => '',
		);

		$options = array(
			'progress' => self::PROGRESS_WORDING_OPTION,
			'unlocked' => self::UNLOCKED_WORDING_OPTION,
		);

		foreach ( $options as $state => $option ) {
			$saved = get_option( $option, '' );

			if ( is_string( $saved ) && '' !== trim( $saved ) ) {
				$wording[ $state ] = Feature_Settings::get_store_copy_text( $option );
			}
		}

		return $wording;
	}

	/**
	 * The theme default for one state, in merchant wording syntax.
	 *
	 * @param string $state 'progress' | 'unlocked'.
	 * @return string
	 */
	public static function default_wording( string $state ): string {
		$templates = self::get_default_templates();

		return self::template_to_wording( 'unlocked' === $state ? $templates['unlockedDefault'] : $templates['progressDefault'] );
	}

	/**
	 * Swap wording into a template set.
	 *
	 * @param array{progressDefault: string, progressCustom: string, unlockedDefault: string, unlockedCustom: string} $i18n            Templates.
	 * @param string                                                                                                  $progress        In-progress wording ('' keeps the template).
	 * @param string                                                                                                  $unlocked        Unlocked wording ('' keeps the template).
	 * @param bool                                                                                                    $phrase_variants Also replace the emphasis-phrase variants.
	 * @return array{progressDefault: string, progressCustom: string, unlockedDefault: string, unlockedCustom: string}
	 */
	public static function apply_wording( array $i18n, string $progress, string $unlocked, bool $phrase_variants ): array {
		$progress_template = self::wording_to_template( $progress, true );
		$unlocked_template = self::wording_to_template( $unlocked, false );

		if ( '' !== $progress_template ) {
			$i18n['progressDefault'] = $progress_template;
			if ( $phrase_variants ) {
				$i18n['progressCustom'] = $progress_template;
			}
		}

		if ( '' !== $unlocked_template ) {
			$i18n['unlockedDefault'] = $unlocked_template;
			if ( $phrase_variants ) {
				$i18n['unlockedCustom'] = $unlocked_template;
			}
		}

		return $i18n;
	}

	/**
	 * Convert merchant wording into a template.
	 *
	 * `*words*` become the highlight markers, `{amount}` the amount arg, and
	 * literal percent signs are escaped so they can't act as printf args. An
	 * unpaired asterisk stays literal.
	 *
	 * @param string $wording     Wording, e.g. `{amount} away from *free shipping*`.
	 * @param bool   $with_amount Whether `{amount}` resolves (progress only).
	 * @return string Template, or '' for blank wording.
	 */
	public static function wording_to_template( string $wording, bool $with_amount ): string {
		$text = trim( wp_strip_all_tags( $wording ) );

		if ( '' === $text ) {
			return '';
		}

		$text = str_replace( '%', '%%', $text );
		$text = (string) preg_replace( '/\*([^*\r\n]+)\*/u', '{em}$1{em_end}', $text );

		return str_replace( self::AMOUNT_TOKEN, $with_amount ? '%1$s' : '', $text );
	}

	/**
	 * Convert a default template into merchant wording (reverse of the above).
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template_to_wording( string $template ): string {
		return (string) preg_replace_callback(
			self::MESSAGE_TOKEN,
			static function ( array $token ): string {
				if ( '{em}' === $token[0] || '{em_end}' === $token[0] ) {
					return '*';
				}

				return '%%' === $token[0] ? '%' : self::AMOUNT_TOKEN;
			},
			$template
		);
	}

	/**
	 * Format the message as plain text with the current templates.
	 *
	 * @param float  $remaining Amount still needed.
	 * @param string $emphasis  Legacy emphasis phrase ('' for none).
	 * @param bool   $complete  Whether the threshold has been met.
	 * @return string
	 */
	public static function format( float $remaining, string $emphasis, bool $complete ): string {
		$segments = self::segments(
			array_merge(
				array(
					'remaining'    => $remaining,
					'complete'     => $complete,
					'emphasisText' => $emphasis,
					'i18n'         => self::get_templates(),
				),
				Free_Shipping::get_currency_context()
			)
		);

		return implode( '', array_column( $segments, 'text' ) );
	}

	/**
	 * Split the message into styled segments.
	 *
	 * Server twin of buildFreeShippingSegments() in cart-data.ts: both read the
	 * block's interactivity context, so first paint and live updates match.
	 * The amount and the highlighted words are their own segments; the rest is
	 * plain text.
	 *
	 * @param array<string, mixed> $context Block context (remaining, complete, emphasisText, i18n, currency*).
	 * @return list<array{key: string, text: string, amount: bool, emphasis: bool}>
	 */
	public static function segments( array $context ): array {
		$emphasis = trim( (string) ( $context['emphasisText'] ?? '' ) );
		$i18n     = is_array( $context['i18n'] ?? null ) ? $context['i18n'] : self::get_templates();
		$default  = self::is_default_emphasis( $emphasis );
		$phrase   = array( $emphasis, 'emphasis' );

		if ( ! empty( $context['complete'] ) ) {
			return $default
				? self::split_template( (string) ( $i18n['unlockedDefault'] ?? '' ), array() )
				: self::split_template( (string) ( $i18n['unlockedCustom'] ?? '' ), array( $phrase ) );
		}

		$amount = array(
			self::format_amount( (float) ( $context['remaining'] ?? 0 ), $context ),
			'amount',
		);

		return $default
			? self::split_template( (string) ( $i18n['progressDefault'] ?? '' ), array( $amount ) )
			: self::split_template( (string) ( $i18n['progressCustom'] ?? '' ), array( $amount, $phrase ) );
	}

	/**
	 * Format an amount from currency context fields.
	 *
	 * PHP twin of formatMoney() in cart-data.ts, so server HTML matches the
	 * live update. A whole amount drops its zero decimals ($104, not $104.00);
	 * zero-decimal currencies (JPY) never had any.
	 *
	 * @param float                $amount   Amount in the active currency.
	 * @param array<string, mixed> $currency currencyMinorUnit, currencyPrefix, currencySuffix,
	 *                                       currencyDecimalSeparator, currencyThousandSeparator.
	 * @return string
	 */
	public static function format_amount( float $amount, array $currency ): string {
		$decimals = max( 0, (int) ( $currency['currencyMinorUnit'] ?? 2 ) );
		$parts    = explode( '.', number_format( $amount, $decimals, '.', '' ), 2 );
		$integer  = $parts[0];
		$fraction = $parts[1] ?? '';
		$grouped  = (string) preg_replace(
			'/\B(?=(\d{3})+(?!\d))/',
			(string) ( $currency['currencyThousandSeparator'] ?? ',' ),
			$integer
		);
		$number   = '' === rtrim( $fraction, '0' )
			? $grouped
			: $grouped . (string) ( $currency['currencyDecimalSeparator'] ?? '.' ) . $fraction;

		return (string) ( $currency['currencyPrefix'] ?? '' ) . $number . (string) ( $currency['currencySuffix'] ?? '' );
	}

	/**
	 * Tokenize a template into segments.
	 *
	 * Unbalanced or missing `{em}` markers degrade to plain text, and a
	 * missing printf argument renders nothing — never a PHP 8 sprintf error.
	 *
	 * @param string                      $template Translated template.
	 * @param list<array{string, string}> $args     Placeholder values as [text, kind].
	 * @return list<array{key: string, text: string, amount: bool, emphasis: bool}>
	 */
	private static function split_template( string $template, array $args ): array {
		$parts      = array();
		$emphasized = false;
		$next_arg   = 0;
		$offset     = 0;

		preg_match_all( self::MESSAGE_TOKEN, $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		foreach ( $matches as $match ) {
			$token    = $match[0][0];
			$position = $match[0][1];
			$literal  = $emphasized ? 'emphasis' : 'text';

			self::push_part( $parts, substr( $template, $offset, $position - $offset ), $literal );
			$offset = $position + strlen( $token );

			if ( '{em}' === $token || '{em_end}' === $token ) {
				$emphasized = '{em}' === $token;
			} elseif ( '%%' === $token ) {
				self::push_part( $parts, '%', $literal );
			} else {
				$index = isset( $match[1] ) && $match[1][1] >= 0 ? (int) $match[1][0] - 1 : $next_arg++;
				if ( isset( $args[ $index ] ) ) {
					self::push_part( $parts, $args[ $index ][0], $args[ $index ][1] );
				}
			}
		}

		self::push_part( $parts, substr( $template, $offset ), $emphasized ? 'emphasis' : 'text' );

		$segments = array();
		foreach ( $parts as $i => $part ) {
			$segments[] = array(
				'key'      => $i . '-' . $part[1],
				'text'     => $part[0],
				'amount'   => 'amount' === $part[1],
				'emphasis' => 'emphasis' === $part[1],
			);
		}

		return $segments;
	}

	/**
	 * Append a segment, merging runs of the same non-amount kind.
	 *
	 * @param list<array{string, string}> $parts Segments so far, as [text, kind].
	 * @param string                      $text  Segment text.
	 * @param string                      $kind  'text' | 'emphasis' | 'amount'.
	 * @return void
	 */
	private static function push_part( array &$parts, string $text, string $kind ): void {
		if ( '' === $text ) {
			return;
		}

		$last = count( $parts ) - 1;
		if ( $last >= 0 && 'amount' !== $kind && $parts[ $last ][1] === $kind ) {
			$parts[ $last ][0] .= $text;
			return;
		}

		$parts[] = array( $text, $kind );
	}

	/**
	 * Whether a legacy emphasis phrase is blank or the stock label.
	 *
	 * @param string $emphasis Emphasis phrase from block settings.
	 * @return bool
	 */
	private static function is_default_emphasis( string $emphasis ): bool {
		if ( '' === $emphasis ) {
			return true;
		}

		return 'freeshipping' === strtolower( str_replace( ' ', '', $emphasis ) );
	}
}
