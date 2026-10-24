<?php
/**
 * Enhancements Coordinator Class
 *
 * Registers and initializes WooCommerce enhancement services based on
 * the feature flags managed by Feature_Settings.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Service_Container;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhancements Coordinator
 *
 * Acts as a single entry point that checks feature flags and only
 * resolves / initializes services the admin has enabled. Every feature
 * service is created through the shared Service_Container so instantiation
 * is consistent with the rest of the theme and individual services remain
 * overridable for tests.
 *
 * @since 1.17.0
 */
class Enhancements {

	/**
	 * Map of feature flag => ordered list of service classes to initialize.
	 *
	 * Each listed class exposes an `init()` method and has no constructor
	 * dependencies, so it can be resolved generically through the container.
	 * Features needing bespoke wiring (installers, admin-only services,
	 * cross-feature bridges) are handled in init_special_features().
	 *
	 * @var array<string, array<int, class-string>>
	 */
	private const FEATURE_SERVICES = array(
		'product_badges'             => array( Custom_Badge_Taxonomy::class, Product_Badges::class ),
		'price_display'              => array( Price_Display::class ),
		'advanced_sorting'           => array( Advanced_Sorting::class ),
		'swatch_tooltips'            => array( Swatch_Tooltips::class ),
		'product_filters'            => array( Product_Filters::class, Product_Facets_Controller::class ),
		'page_transitions'           => array( Page_Transitions::class ),
		'catalog_hover_image'        => array( Catalog_Hover_Image::class ),
		'load_more'                  => array( Load_More::class ),
		'size_guide'                 => array(
			Size_Guide_Post_Type::class,
			Size_Guide::class,
			Size_Guide_Admin_Fields::class,
			Size_Guide_Rest::class,
			Size_Guide_Label_Link::class,
			Size_Chart_Import::class,
			Product_Specs_Meta::class,
			Product_Specs_Admin::class,
			Product_Specs_Settings::class,
			Product_Specs_Table::class,
			Product_Specs_Grid::class,
			Product_Specs_Migration::class,
			Printful_Rest::class,
			Printful_Import::class,
		),
		'sticky_add_to_cart'         => array( Sticky_Add_To_Cart::class ),
		'mobile_bottom_nav'          => array( Mobile_Bottom_Nav::class ),
		'quick_view'                 => array( Quick_View::class ),
		'wishlist'                   => array( Wishlist::class ),
		'social_proof'               => array( Social_Proof::class ),
		'frequently_bought_together' => array( Frequently_Bought_Together::class ),
	);

	/**
	 * Service classes that need bespoke wiring beyond a simple init().
	 *
	 * @var array<int, class-string>
	 */
	private const SPECIAL_SERVICES = array(
		Sale_Category::class,
		Back_In_Stock_Migration::class,
		Back_In_Stock::class,
		Back_In_Stock_Links::class,
		Back_In_Stock_Privacy::class,
	);

	/**
	 * Shared service container used to resolve every enhancement service.
	 *
	 * @var Service_Container
	 */
	private Service_Container $container;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Shared DI container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize all enabled enhancements.
	 *
	 * Called from Bootstrap::init_woocommerce_components().
	 *
	 * @return void
	 */
	public function init(): void {
		// Register shared utility modules that multiple features depend on.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_shared_modules' ) );

		$this->register_feature_services();
		$this->init_flagged_features();
		$this->init_special_features();
	}

	/**
	 * Register every enhancement service class with the container.
	 *
	 * Classes are keyed by their fully-qualified name, so the container
	 * acts as a lazy, single-instance factory for each one.
	 *
	 * @return void
	 */
	private function register_feature_services(): void {
		$classes = array_merge( ...array_values( self::FEATURE_SERVICES ) );
		$classes = array_merge( $classes, self::SPECIAL_SERVICES );

		foreach ( array_unique( $classes ) as $class ) {
			$this->container->register( $class, static fn() => new $class() );
		}
	}

	/**
	 * Resolve and initialize the services for each enabled feature flag.
	 *
	 * @return void
	 */
	private function init_flagged_features(): void {
		foreach ( self::FEATURE_SERVICES as $feature => $classes ) {
			if ( ! Feature_Settings::is_enabled( $feature ) ) {
				continue;
			}

			foreach ( $classes as $class ) {
				$this->container->get( $class )->init();
			}
		}
	}

	/**
	 * Wire up features that need more than a uniform init() call.
	 *
	 * @return void
	 */
	private function init_special_features(): void {
		// Native, system-managed Sales product category. This is catalogue
		// infrastructure rather than an optional visual enhancement.
		$this->container->get( Sale_Category::class )->init();

		// Seed system badges once the taxonomy exists.
		if ( Feature_Settings::is_enabled( 'product_badges' ) ) {
			add_action( 'admin_init', array( Custom_Badge_Taxonomy::class, 'maybe_seed_system_badges' ), 20 );
		}

		// Product Tabs admin: always available since it backs a Gutenberg block.
		// The block's render.php handles frontend rendering; admin hooks must
		// still register so per-product tab config and the settings page work.
		$this->container->register( Product_Tabs::class, static fn() => new Product_Tabs() );
		$this->container->get( Product_Tabs::class )->init_admin_only();

		// Back in Stock: WooCommerce owns the waitlist (Back_In_Stock_Native);
		// the theme adds the block form, scanner-safe links and an exporter.
		// The old table's move runs whatever the toggle says — its
		// subscribers outlive the setting.
		$this->container->get( Back_In_Stock_Migration::class )->init();
		if ( Feature_Settings::is_enabled( 'back_in_stock' ) ) {
			$this->container->get( Back_In_Stock::class )->init();
			if ( Back_In_Stock_Native::is_supported() ) {
				$this->container->get( Back_In_Stock_Links::class )->init();
				$this->container->get( Back_In_Stock_Privacy::class )->init();
			}
		}
	}

	/**
	 * Register shared script modules used by multiple enhancement features.
	 *
	 * Modules registered here are loaded on-demand when a feature that
	 * declares them as a dependency is enqueued.
	 *
	 * @return void
	 */
	public function register_shared_modules(): void {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		Asset_Loader::register_interactivity_module(
			'@aggressive-apparel/scroll-lock',
			'build/interactivity/scroll-lock',
			array(),
			false
		);

		Asset_Loader::register_interactivity_module(
			'@aggressive-apparel/helpers',
			'build/interactivity/helpers',
			array(),
			false
		);

		Asset_Loader::register_interactivity_module(
			'@aggressive-apparel/use-overlay',
			'build/interactivity/use-overlay',
			array( '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
			false
		);

		// Add-to-cart burst animation: always-on when WooCommerce is active.
		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/cart-burst',
			'build/interactivity/cart-burst',
			array(),
			false
		);
	}
}
