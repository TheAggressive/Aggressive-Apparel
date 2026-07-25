<?php
/**
 * Custom badge taxonomy — labels + system badge defaults.
 *
 * Extracted from Custom_Badge_Taxonomy to keep each file under the length cap.
 * Composed via `use`; all callers are unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Badge_System_Defaults {
	/**
	 * Get taxonomy labels.
	 *
	 * @return array<string, string>
	 */
	private static function get_labels(): array {
		return array(
			'name'          => __( 'Product Badges', 'aggressive-apparel' ),
			'singular_name' => __( 'Badge', 'aggressive-apparel' ),
			'menu_name'     => __( 'Badges', 'aggressive-apparel' ),
			'add_new_item'  => __( 'Add New Badge', 'aggressive-apparel' ),
			'edit_item'     => __( 'Edit Badge', 'aggressive-apparel' ),
			'new_item_name' => __( 'New Badge Name', 'aggressive-apparel' ),
			'search_items'  => __( 'Search Badges', 'aggressive-apparel' ),
			'not_found'     => __( 'No badges found.', 'aggressive-apparel' ),
			'all_items'     => __( 'All Badges', 'aggressive-apparel' ),
			'back_to_items' => __( 'Back to Badges', 'aggressive-apparel' ),
		);
	}

	/**
	 * Seed system badge terms if not already done.
	 *
	 * Creates the 4 automatic badge types (sale, new, low_stock, bestseller)
	 * as taxonomy terms with default visual properties matching the previously
	 * hardcoded styles. Safe to call multiple times — uses a version option guard.
	 *
	 * @return void
	 */
	public static function maybe_seed_system_badges(): void {
		if ( get_option( self::SEED_VERSION_OPTION ) === self::SEED_VERSION ) {
			return;
		}

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		$system_badges  = self::get_system_badge_defaults();
		$existing_types = array();
		$existing_terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_array( $existing_terms ) ) {
			foreach ( $existing_terms as $existing_term ) {
				$type = get_term_meta( $existing_term->term_id, self::META_BADGE_TYPE, true );
				if ( is_string( $type ) && '' !== $type ) {
					$existing_types[ $type ] = true;
				}
			}
		}

		foreach ( $system_badges as $badge_type => $config ) {
			if ( isset( $existing_types[ $badge_type ] ) ) {
				continue;
			}

			$result = wp_insert_term( $config['name'], self::TAXONOMY );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$term_id = $result['term_id'];

			update_term_meta( $term_id, self::META_BADGE_TYPE, $badge_type );

			foreach ( $config['meta'] as $key => $value ) {
				update_term_meta( $term_id, $key, $value );
			}
		}

		update_option( self::SEED_VERSION_OPTION, self::SEED_VERSION );
	}

	/**
	 * Default configurations for the 4 system badges.
	 *
	 * Colors match the hex fallback values from the previously hardcoded CSS.
	 *
	 * @return array<string, array{name: string, meta: array<string, string|int>}>
	 */
	private static function get_system_badge_defaults(): array {
		$shared_meta = array(
			self::META_POSITION     => 'top-left',
			self::META_RADIUS_TL    => 4,
			self::META_RADIUS_TR    => 4,
			self::META_RADIUS_BR    => 4,
			self::META_RADIUS_BL    => 4,
			self::META_PADDING_X    => 8,
			self::META_PADDING_Y    => 3,
			self::META_BORDER_WIDTH => 0,
			self::META_BORDER_STYLE => 'none',
			self::META_BORDER_COLOR => '',
			self::META_ICON         => '',
			self::META_LIBRARY_ICON => '',
			self::META_SVG_ICON     => '',
			self::META_ICON_COLOR   => '',
			self::META_ICON_SIZE    => 0,
			self::META_ICON_GAP     => 0,
		);

		return array(
			'sale'       => array(
				'name' => __( 'Sale', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#dc2626',
						self::META_TEXT_COLOR => '#ffffff',
						self::META_PRIORITY   => 1,
					),
				),
			),
			'new'        => array(
				'name' => __( 'New', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#000000',
						self::META_TEXT_COLOR => '#ffffff',
						self::META_PRIORITY   => 2,
					),
				),
			),
			'low_stock'  => array(
				'name' => __( 'Low Stock', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR   => '#f59e0b',
						self::META_TEXT_COLOR => '#000000',
						self::META_PRIORITY   => 3,
					),
				),
			),
			'bestseller' => array(
				'name' => __( 'Bestseller', 'aggressive-apparel' ),
				'meta' => array_merge(
					$shared_meta,
					array(
						self::META_BG_COLOR     => '#ffffff',
						self::META_TEXT_COLOR   => '#000000',
						self::META_PRIORITY     => 4,
						self::META_BORDER_WIDTH => 1,
						self::META_BORDER_STYLE => 'solid',
						self::META_BORDER_COLOR => '#000000',
					),
				),
			),
		);
	}

	/**
	 * Get visual data for all system badge terms, keyed by badge_type.
	 *
	 * Performs a single WP_Term_Query per request and caches the result.
	 * Returns an associative array: 'sale' => [...data...], 'new' => [...], etc.
	 * If a system badge term has been deleted, its key is absent.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_system_badges(): array {
		if ( null !== self::$system_badges_cache ) {
			return self::$system_badges_cache;
		}

		self::$system_badges_cache = array();

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			),
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::$system_badges_cache;
		}

		foreach ( $terms as $term ) {
			$data = self::get_badge_data( $term->term_id );
			if ( ! in_array( $data['badge_type'], array( 'sale', 'new', 'low_stock', 'bestseller' ), true ) ) {
				continue;
			}

			$data['name'] = $term->name;

			self::$system_badges_cache[ $data['badge_type'] ] = $data;
		}

		return self::$system_badges_cache;
	}
}
