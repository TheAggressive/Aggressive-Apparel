<?php
/**
 * Printful Sync Guard
 *
 * Keeps the store's product edits when Printful syncs. The Printful plugin
 * doesn't write products itself: Printful's servers send them through the
 * WooCommerce REST API, which replaces whatever a request names. This steps in
 * just before such a request saves.
 *
 * Each field is judged by what Printful last wrote to it (SYNCED):
 *
 * - The product still has that value: nobody edited it, so Printful's update
 *   goes through.
 * - It has anything else: the store edited it, so the store's value stays, and
 *   the product editor says what Printful wanted instead (KEPT). Setting the
 *   field to Printful's value hands it back to the sync.
 * - Nothing recorded yet (synced before this existed): it can't be told apart
 *   from an edit, so the product's value stays.
 *
 * Attributes merge rather than replace: ones that define variations are the
 * sync's (changing them would orphan the variations), and any other the product
 * has stays. New ones that would supply a spec are dropped, since a product's
 * attribute would otherwise outrank the size guide's value.
 *
 * Requests from anyone else are untouched, and so is everything a product keeps
 * outside these fields (size guide choice, spec settings, other meta).
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds store edits against Printful syncs.
 *
 * @phpstan-type Kept array<int, array<string, string>>
 */
final class Printful_Sync_Guard {

	/**
	 * Product meta: field => hash of what Printful last wrote.
	 *
	 * @var string
	 */
	public const SYNCED = '_aa_printful_synced';

	/**
	 * Parent product meta: product or variation ID => field => the value
	 * Printful wanted on its last sync, where the store's was kept.
	 *
	 * @var string
	 */
	public const KEPT = '_aa_printful_kept';

	/**
	 * What Printful sends as its user agent (Printful_Request_Log::PF_USER_AGENT).
	 *
	 * @var string
	 */
	private const USER_AGENT = 'Printful';

	/**
	 * What Printful's WooCommerce API key is described as
	 * (Printful_Admin_Status::API_KEY_SEARCH_STRING).
	 *
	 * @var string
	 */
	private const KEY_DESCRIPTION = 'Printful';

	/**
	 * Fields a product keeps.
	 *
	 * @var list<string>
	 */
	private const PRODUCT_FIELDS = array( 'name', 'slug', 'description', 'short_description', 'status', 'regular_price', 'sale_price', 'category_ids', 'tag_ids', 'image_id', 'gallery_image_ids' );

	/**
	 * Fields a variation keeps. Its SKU and attributes are how Printful
	 * fulfils it, so those stay with the sync.
	 *
	 * @var list<string>
	 */
	private const VARIATION_FIELDS = array( 'description', 'status', 'regular_price', 'sale_price', 'image_id' );

	/**
	 * Fields kept in this request, by product ID, until the save finishes.
	 *
	 * @var Kept
	 */
	private static array $kept = array();

	/**
	 * Register hooks.
	 */
	public function init(): void {
		foreach ( array( 'product', 'product_variation' ) as $type ) {
			add_filter( "woocommerce_rest_pre_insert_{$type}_object", array( self::class, 'hold_edits' ), 20, 3 );
			add_action( "woocommerce_rest_insert_{$type}_object", array( self::class, 'record_sync' ), 20, 3 );
		}
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
	}

	/**
	 * Put back whatever the store edited, before a sync saves over it.
	 *
	 * @param mixed            $product  Product about to save.
	 * @param \WP_REST_Request $request  Request.
	 * @param bool             $creating Whether it is new.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	public static function hold_edits( $product, $request, $creating ) {
		if ( $creating || ! $product instanceof \WC_Product || ! self::is_printful( $request ) ) {
			return $product;
		}
		$current = wc_get_product( $product->get_id() );
		if ( ! $current instanceof \WC_Product ) {
			return $product;
		}

		$synced = self::synced( $product->get_id() );
		$kept   = array();
		foreach ( self::fields( $product ) as $field ) {
			$mine   = self::read( $current, $field );
			$theirs = self::read( $product, $field );
			if ( self::hash( $mine ) === self::hash( $theirs ) || self::hash( $mine ) === ( $synced[ $field ] ?? null ) ) {
				continue;
			}
			self::write( $product, $field, $mine );
			$kept[ $field ] = self::describe( $theirs );
		}

		if ( ! $product->is_type( 'variation' ) && isset( $request['attributes'] ) ) {
			$product->set_attributes( self::merged_attributes( $product->get_attributes(), $current->get_attributes(), $kept ) );
		}

		self::$kept[ $product->get_id() ] = $kept;

		return $product;
	}

	/**
	 * After a sync saves, note what it wrote and what it was refused.
	 *
	 * @param mixed            $product  Saved product.
	 * @param \WP_REST_Request $request  Request.
	 * @param bool             $creating Whether it is new.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function record_sync( $product, $request, $creating ): void {
		unset( $creating );
		if ( ! $product instanceof \WC_Product || ! self::is_printful( $request ) ) {
			return;
		}
		$id    = $product->get_id();
		$saved = wc_get_product( $id );
		if ( ! $saved instanceof \WC_Product ) {
			return;
		}

		$kept   = self::$kept[ $id ] ?? array();
		$synced = self::synced( $id );
		foreach ( self::fields( $saved ) as $field ) {
			if ( ! isset( $kept[ $field ] ) ) {
				$synced[ $field ] = self::hash( self::read( $saved, $field ) );
			}
		}
		update_post_meta( $id, self::SYNCED, $synced );
		unset( self::$kept[ $id ] );

		$parent = $saved->get_parent_id() > 0 ? $saved->get_parent_id() : $id;
		$notes  = get_post_meta( $parent, self::KEPT, true );
		$notes  = is_array( $notes ) ? $notes : array();
		unset( $notes[ $id ] );
		if ( array() !== $kept ) {
			$notes[ $id ] = $kept;
		}
		if ( array() === $notes ) {
			delete_post_meta( $parent, self::KEPT );
		} else {
			update_post_meta( $parent, self::KEPT, $notes );
		}
	}

	/**
	 * On a product's edit screen, say what the last sync was refused.
	 */
	public static function render_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post   = get_post();
		if ( null === $screen || 'post' !== $screen->base || 'product' !== $screen->post_type || ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$notes = get_post_meta( $post->ID, self::KEPT, true );
		if ( ! is_array( $notes ) || array() === $notes ) {
			return;
		}

		$lines = '';
		foreach ( $notes as $id => $fields ) {
			foreach ( is_array( $fields ) ? $fields : array() as $field => $value ) {
				$label = self::label( (string) $field );
				if ( (int) $id !== $post->ID ) {
					/* translators: 1: variation ID, 2: field name. */
					$label = sprintf( __( 'Variation #%1$d: %2$s', 'aggressive-apparel' ), (int) $id, $label );
				}
				$lines .= '<li>' . esc_html( '' !== (string) $value ? $label . ': ' . $value : $label ) . '</li>';
			}
		}

		printf(
			'<div class="notice notice-info"><p>%1$s</p><ul>%2$s</ul><p>%3$s</p></div>',
			esc_html__( 'Printful’s last sync wanted to change these, and your versions were kept:', 'aggressive-apparel' ),
			$lines, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html__( 'To take Printful’s version, set the field to that value and save. Later syncs then update it again.', 'aggressive-apparel' )
		);
	}

	/**
	 * Whether a request comes from Printful: by its user agent, or by the
	 * WooCommerce API key it was given, in case the agent ever changes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	private static function is_printful( $request ): bool {
		/**
		 * Filters whether Printful syncs keep the store's product edits.
		 *
		 * @param bool $keep Keep them.
		 */
		if ( ! apply_filters( 'aggressive_apparel_printful_keep_store_edits', true ) ) {
			return false;
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- authenticated REST writes are never page cached.
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( str_contains( $agent, self::USER_AGENT ) ) {
			return true;
		}

		$key = self::consumer_key( $request );
		if ( '' === $key ) {
			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- only on an authenticated REST product write; no API for this.
		$description = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s", wc_api_hash( $key ) ) );

		return is_string( $description ) && str_contains( $description, self::KEY_DESCRIPTION );
	}

	/**
	 * The WooCommerce consumer key a request authenticated with ('' for none).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	private static function consumer_key( $request ): string {
		$candidates = array(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- only reads the key's name, to look up its description; WooCommerce does the authenticating.
			isset( $_SERVER['PHP_AUTH_USER'] ) ? wp_unslash( (string) $_SERVER['PHP_AUTH_USER'] ) : '',
			$request->get_param( 'consumer_key' ),
			$request->get_param( 'oauth_consumer_key' ),
		);
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && str_starts_with( $candidate, 'ck_' ) ) {
				return sanitize_text_field( $candidate );
			}
		}

		return '';
	}

	/**
	 * A sync's attributes merged with the product's.
	 *
	 * @param array $incoming The sync's attributes.
	 * @param array $current  The product's attributes.
	 * @param array $kept     Filled with 'attributes' when any were held back.
	 * @phpstan-param array<mixed> $incoming
	 * @phpstan-param array<mixed> $current
	 * @phpstan-param array<string, string> $kept
	 * @return array<mixed>
	 */
	private static function merged_attributes( array $incoming, array $current, array &$kept ): array {
		$merged  = array();
		$refused = false;
		foreach ( $incoming as $slug => $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute || $attribute->get_variation() ) {
				$merged[ $slug ] = $attribute;
				continue;
			}
			$mine = $current[ $slug ] ?? null;
			if ( $mine instanceof \WC_Product_Attribute && ! $mine->get_variation() ) {
				$merged[ $slug ] = $mine;
				$refused         = $refused || $mine->get_options() !== $attribute->get_options();
			} elseif ( Product_Specs_Attributes::supplies_spec( $attribute ) ) {
				$refused = true;
			} else {
				$merged[ $slug ] = $attribute;
			}
		}
		foreach ( $current as $slug => $attribute ) {
			if ( ! isset( $merged[ $slug ] ) && $attribute instanceof \WC_Product_Attribute && ! $attribute->get_variation() ) {
				$merged[ $slug ] = $attribute;
				$refused         = true;
			}
		}
		if ( $refused ) {
			$kept['attributes'] = '';
		}

		return $merged;
	}

	/**
	 * The fields a product keeps.
	 *
	 * @param \WC_Product $product Product.
	 * @return list<string>
	 */
	private static function fields( \WC_Product $product ): array {
		return $product->is_type( 'variation' ) ? self::VARIATION_FIELDS : self::PRODUCT_FIELDS;
	}

	/**
	 * A field's value, unfiltered.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $field   Field.
	 * @return mixed
	 */
	private static function read( \WC_Product $product, string $field ) {
		return match ( $field ) {
			'name'              => $product->get_name( 'edit' ),
			'slug'              => $product->get_slug( 'edit' ),
			'description'       => $product->get_description( 'edit' ),
			'short_description' => $product->get_short_description( 'edit' ),
			'status'            => $product->get_status( 'edit' ),
			'regular_price'     => $product->get_regular_price( 'edit' ),
			'sale_price'        => $product->get_sale_price( 'edit' ),
			'category_ids'      => $product->get_category_ids( 'edit' ),
			'tag_ids'           => $product->get_tag_ids( 'edit' ),
			'image_id'          => $product->get_image_id( 'edit' ),
			'gallery_image_ids' => $product->get_gallery_image_ids( 'edit' ),
			default             => '',
		};
	}

	/**
	 * Set a field back.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $field   Field.
	 * @param mixed       $value   Value from read().
	 */
	private static function write( \WC_Product $product, string $field, $value ): void {
		$text = is_scalar( $value ) ? (string) $value : '';
		$ids  = is_array( $value ) ? array_map( 'absint', $value ) : array();
		match ( $field ) {
			'name'              => $product->set_name( $text ),
			'slug'              => $product->set_slug( $text ),
			'description'       => $product->set_description( $text ),
			'short_description' => $product->set_short_description( $text ),
			'status'            => $product->set_status( $text ),
			'regular_price'     => $product->set_regular_price( $text ),
			'sale_price'        => $product->set_sale_price( $text ),
			'category_ids'      => $product->set_category_ids( $ids ),
			'tag_ids'           => $product->set_tag_ids( $ids ),
			'image_id'          => $product->set_image_id( $text ),
			'gallery_image_ids' => $product->set_gallery_image_ids( $ids ),
			default             => null,
		};
	}

	/**
	 * A value's fingerprint: IDs in any order and stray spaces don't matter.
	 *
	 * @param mixed $value Value from read().
	 */
	private static function hash( $value ): string {
		if ( is_array( $value ) ) {
			$value = array_map( 'intval', $value );
			sort( $value );

			return md5( (string) wp_json_encode( $value ) );
		}

		return md5( is_scalar( $value ) ? trim( (string) $value ) : '' );
	}

	/**
	 * A refused value in words, short enough for a notice ('' for a list of IDs).
	 *
	 * @param mixed $value Value from read().
	 */
	private static function describe( $value ): string {
		return is_scalar( $value ) ? wp_html_excerpt( (string) $value, 120, '…' ) : '';
	}

	/**
	 * What Printful last wrote to a product.
	 *
	 * @param int $id Product ID.
	 * @return array<string, string>
	 */
	private static function synced( int $id ): array {
		$synced = get_post_meta( $id, self::SYNCED, true );

		return is_array( $synced ) ? array_filter( array_map( static fn( $hash ): string => is_string( $hash ) ? $hash : '', $synced ) ) : array();
	}

	/**
	 * A field's name for the notice.
	 *
	 * @param string $field Field.
	 */
	private static function label( string $field ): string {
		return match ( $field ) {
			'name'              => __( 'Title', 'aggressive-apparel' ),
			'slug'              => __( 'Slug', 'aggressive-apparel' ),
			'description'       => __( 'Description', 'aggressive-apparel' ),
			'short_description' => __( 'Short description', 'aggressive-apparel' ),
			'status'            => __( 'Status', 'aggressive-apparel' ),
			'regular_price'     => __( 'Regular price', 'aggressive-apparel' ),
			'sale_price'        => __( 'Sale price', 'aggressive-apparel' ),
			'category_ids'      => __( 'Categories', 'aggressive-apparel' ),
			'tag_ids'           => __( 'Tags', 'aggressive-apparel' ),
			'image_id'          => __( 'Main image', 'aggressive-apparel' ),
			'gallery_image_ids' => __( 'Gallery', 'aggressive-apparel' ),
			'attributes'        => __( 'Attributes', 'aggressive-apparel' ),
			default             => $field,
		};
	}
}
