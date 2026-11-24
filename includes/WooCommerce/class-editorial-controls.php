<?php
/**
 * Editorial Controls
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
 * The merchant's say over what the homepage shows, kept where the content
 * already lives: no settings screen of its own.
 *
 * - Media Library: each image's "Homepage use" (Not reviewed, Approved,
 *   Excluded) and priority, a list filter, and bulk Approve/Exclude.
 *   Only approved photos fill editorial slots; nothing publishes itself.
 * - Site Editor: the cover's campaign photo is the "Homepage campaign
 *   image" template part (Editorial_Imagery::CAMPAIGN_PART), a core Cover
 *   block with its own focal point and alt text.
 * - Product edit screen: "Homepage cover" picks the product the cover
 *   names, and whose photo it shows when there is no campaign photo.
 * - Product categories: "Show in Shop by line" picks the index's lines,
 *   shown in WooCommerce's own drag-and-drop category order.
 */
final class Editorial_Controls {

	/** Attachment meta: how an image may be used on the homepage. */
	public const USE_META = '_aa_editorial_use';

	/** Attachment meta: priority among approved photos (higher first). */
	public const PRIORITY_META = '_aa_editorial_priority';

	/** Product meta: this product is the cover's product. */
	public const COVER_PRODUCT_META = '_aa_homepage_cover';

	/** Term meta: this category is a line in Shop by line. */
	public const LINE_META = '_aa_shop_by_line';

	/** Allowed "Homepage use" values; '' is not reviewed. */
	private const USES = array( '', 'approved', 'excluded' );

	/**
	 * Register admin hooks.
	 */
	public function init(): void {
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_fields' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'media_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_media_query' ) );
		add_filter( 'bulk_actions-upload', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_cover_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_cover' ) );
		add_action( 'product_cat_add_form_fields', array( $this, 'add_line_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_line_field' ) );
		add_action( 'created_product_cat', array( $this, 'save_line_field' ) );
		add_action( 'edited_product_cat', array( $this, 'save_line_field' ) );
	}

	/**
	 * An image's homepage use ('' when not reviewed).
	 *
	 * @param int $image Attachment ID.
	 */
	public static function use_of( int $image ): string {
		$use = (string) get_post_meta( $image, self::USE_META, true );

		return in_array( $use, self::USES, true ) ? $use : '';
	}

	/**
	 * An approved photo's priority (0 when unset).
	 *
	 * @param int $image Attachment ID.
	 */
	public static function priority_of( int $image ): int {
		return (int) get_post_meta( $image, self::PRIORITY_META, true );
	}

	/**
	 * Set an image's homepage use.
	 *
	 * @param int    $image  Attachment ID.
	 * @param string $choice One of USES.
	 */
	public static function set_use( int $image, string $choice ): void {
		if ( ! in_array( $choice, self::USES, true ) ) {
			return;
		}
		if ( '' === $choice ) {
			delete_post_meta( $image, self::USE_META );
		} else {
			update_post_meta( $image, self::USE_META, $choice );
		}
	}

	/**
	 * Media modal and attachment screen fields.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @param \WP_Post             $post   Attachment.
	 * @return array<string, mixed>
	 */
	public function attachment_fields( array $fields, \WP_Post $post ): array {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		$use     = self::use_of( $post->ID );
		$labels  = array(
			''         => __( 'Not reviewed', 'aggressive-apparel' ),
			'approved' => __( 'Approved for editorial sections', 'aggressive-apparel' ),
			'excluded' => __( 'Excluded', 'aggressive-apparel' ),
		);
		$name    = 'attachments[' . $post->ID . '][aa_editorial_use]';
		$options = '';
		foreach ( $labels as $value => $label ) {
			$options .= '<option value="' . esc_attr( $value ) . '"' . selected( $use, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$kind = Editorial_Imagery::kind( $post->ID );

		$fields['aa_editorial_use']      = array(
			'label' => __( 'Homepage use', 'aggressive-apparel' ),
			'input' => 'html',
			'html'  => '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( 'aa-editorial-use-' . $post->ID ) . '">' . $options . '</select>',
			'helps' => 'photo' === $kind
				? __( 'Looks like a photograph. Only approved photos appear on the homepage, always linked to the product they belong to.', 'aggressive-apparel' )
				: __( 'Looks like a product cut-out or packshot: these belong on product cards rather than editorial sections.', 'aggressive-apparel' ),
		);
		$fields['aa_editorial_priority'] = array(
			'label' => __( 'Homepage priority', 'aggressive-apparel' ),
			'input' => 'html',
			'html'  => '<input type="number" step="1" min="0" max="99" name="' . esc_attr( 'attachments[' . $post->ID . '][aa_editorial_priority]' ) . '" value="' . esc_attr( (string) self::priority_of( $post->ID ) ) . '">',
			'helps' => __( 'Higher shows first among approved photos.', 'aggressive-apparel' ),
		);

		return $fields;
	}

	/**
	 * Save the attachment fields.
	 *
	 * @param array<string, mixed> $post       Attachment data.
	 * @param array<string, mixed> $attachment Submitted fields.
	 * @return array<string, mixed>
	 */
	public function save_attachment_fields( array $post, array $attachment ): array {
		$id = (int) ( $post['ID'] ?? 0 );
		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			return $post;
		}
		if ( isset( $attachment['aa_editorial_use'] ) ) {
			self::set_use( $id, sanitize_key( (string) $attachment['aa_editorial_use'] ) );
		}
		if ( isset( $attachment['aa_editorial_priority'] ) ) {
			update_post_meta( $id, self::PRIORITY_META, max( 0, min( 99, (int) $attachment['aa_editorial_priority'] ) ) );
		}

		return $post;
	}

	/**
	 * Media list filter by homepage use.
	 *
	 * @param string $post_type Screen post type.
	 */
	public function media_filter( string $post_type ): void {
		if ( 'attachment' !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A read-only list filter.
		$current = isset( $_GET['aa_editorial_use'] ) ? sanitize_key( wp_unslash( $_GET['aa_editorial_use'] ) ) : '';
		$choices = array(
			''             => __( 'All homepage uses', 'aggressive-apparel' ),
			'not-reviewed' => __( 'Not reviewed', 'aggressive-apparel' ),
			'approved'     => __( 'Approved', 'aggressive-apparel' ),
			'excluded'     => __( 'Excluded', 'aggressive-apparel' ),
		);
		echo '<label class="screen-reader-text" for="aa-editorial-use-filter">' . esc_html__( 'Filter by homepage use', 'aggressive-apparel' ) . '</label>';
		echo '<select name="aa_editorial_use" id="aa-editorial-use-filter">';
		foreach ( $choices as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Apply the media list filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter_media_query( \WP_Query $query ): void {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A read-only list filter.
		$use = isset( $_GET['aa_editorial_use'] ) ? sanitize_key( wp_unslash( $_GET['aa_editorial_use'] ) ) : '';
		if ( '' === $use || ! is_admin() || 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}
		$query->set(
			'meta_query',
			'not-reviewed' === $use
				? array(
					array(
						'key'     => self::USE_META,
						'compare' => 'NOT EXISTS',
					),
				)
				: array(
					array(
						'key'   => self::USE_META,
						'value' => $use,
					),
				)
		);
	}

	/**
	 * Bulk actions in the media list.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		$actions['aa_editorial_approve'] = __( 'Approve for homepage', 'aggressive-apparel' );
		$actions['aa_editorial_exclude'] = __( 'Exclude from homepage', 'aggressive-apparel' );

		return $actions;
	}

	/**
	 * Run a bulk action.
	 *
	 * @param string     $redirect Redirect URL.
	 * @param string     $action   Action.
	 * @param array<int> $ids      Attachment IDs.
	 */
	public function handle_bulk_action( string $redirect, string $action, array $ids ): string {
		$use = array(
			'aa_editorial_approve' => 'approved',
			'aa_editorial_exclude' => 'excluded',
		)[ $action ] ?? null;
		if ( null === $use ) {
			return $redirect;
		}
		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', (int) $id ) && wp_attachment_is_image( (int) $id ) ) {
				self::set_use( (int) $id, $use );
			}
		}

		return $redirect;
	}

	/**
	 * Product edit screen: "Homepage cover".
	 */
	public function product_cover_field(): void {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => self::COVER_PRODUCT_META,
				'label'       => __( 'Homepage cover', 'aggressive-apparel' ),
				'description' => __( 'Name this product under the homepage campaign photo (choose the garment the photo shows). Without a campaign photo, its own image fills the cover. One product at a time: choosing this one replaces the last.', 'aggressive-apparel' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save "Homepage cover"; exclusive across products.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save_product_cover( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product form's nonce before this hook.
		$on = isset( $_POST[ self::COVER_PRODUCT_META ] );
		if ( $on ) {
			foreach ( self::cover_products() as $other ) {
				if ( $other !== $product->get_id() ) {
					delete_post_meta( $other, self::COVER_PRODUCT_META );
				}
			}
			$product->update_meta_data( self::COVER_PRODUCT_META, 'yes' );
		} else {
			$product->delete_meta_data( self::COVER_PRODUCT_META );
		}
	}

	/**
	 * Products marked as the cover's, most recently changed first.
	 *
	 * @return list<int>
	 */
	public static function cover_products(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- At most a handful of products.
				'meta_key'       => self::COVER_PRODUCT_META,
			)
		);

		return array_values( array_map( static fn( $post ): int => $post instanceof \WP_Post ? $post->ID : (int) $post, $query->posts ) );
	}

	/**
	 * Category screens: "Show in Shop by line".
	 */
	public function add_line_field(): void {
		echo '<div class="form-field"><input type="hidden" name="aa_shop_by_line_field" value="1"><label><input type="checkbox" name="aa_shop_by_line" value="yes"> ' . esc_html__( 'Show in Shop by line', 'aggressive-apparel' ) . '</label><p>' . esc_html__( 'Lists this category on the homepage index, in the category order set here by dragging.', 'aggressive-apparel' ) . '</p></div>';
	}

	/**
	 * Category edit screen field.
	 *
	 * @param \WP_Term $term Category.
	 */
	public function edit_line_field( \WP_Term $term ): void {
		$on = 'yes' === get_term_meta( $term->term_id, self::LINE_META, true );
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Homepage', 'aggressive-apparel' ) . '</th><td><input type="hidden" name="aa_shop_by_line_field" value="1"><label><input type="checkbox" name="aa_shop_by_line" value="yes"' . checked( $on, true, false ) . '> ' . esc_html__( 'Show in Shop by line', 'aggressive-apparel' ) . '</label><p class="description">' . esc_html__( 'Lists this category on the homepage index, in the category order set by dragging categories in the list.', 'aggressive-apparel' ) . '</p></td></tr>';
	}

	/**
	 * Save "Show in Shop by line".
	 *
	 * @param int $term_id Category ID.
	 */
	public function save_line_field( int $term_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Core verified the term form's nonce before this hook.
		// Quick edit posts no field marker: leave the setting alone there.
		if ( ! current_user_can( 'manage_product_terms' ) || ! isset( $_POST['aa_shop_by_line_field'] ) ) {
			return;
		}
		if ( isset( $_POST['aa_shop_by_line'] ) ) {
			update_term_meta( $term_id, self::LINE_META, 'yes' );
		} else {
			delete_term_meta( $term_id, self::LINE_META );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
