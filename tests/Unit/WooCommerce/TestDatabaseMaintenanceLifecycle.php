<?php
/**
 * Database maintenance lifecycle tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Service_Container;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Migration;
use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use Aggressive_Apparel\WooCommerce\Enhancements;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Wishlist;
use WP_UnitTestCase;

/**
 * Ensures one-time data maintenance stays off public requests.
 */
class TestDatabaseMaintenanceLifecycle extends WP_UnitTestCase {

	/**
	 * The back-in-stock table move should be admin-only, and registered even
	 * with the theme feature off: the subscribers it rescues outlive the toggle.
	 */
	public function test_back_in_stock_migration_uses_admin_lifecycle(): void {
		delete_option( Feature_Settings::OPTION_KEY );

		$container    = new Service_Container();
		$enhancements = new Enhancements( $container );
		$enhancements->init();
		$migration = $container->get( Back_In_Stock_Migration::class );

		$this->assertFalse( has_action( 'init', array( $migration, 'maybe_run' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( $migration, 'maybe_run' ) ) );

		remove_action( 'admin_init', array( $migration, 'maybe_run' ) );
	}

	/**
	 * Wishlist provisioning should be admin-only.
	 */
	public function test_wishlist_page_provisioning_uses_admin_lifecycle(): void {
		$wishlist = new Wishlist();
		$wishlist->init();

		$this->assertFalse( has_action( 'init', array( $wishlist, 'maybe_create_page' ) ) );
		$this->assertSame( 20, has_action( 'admin_init', array( $wishlist, 'maybe_create_page' ) ) );

		remove_action( 'admin_init', array( $wishlist, 'maybe_create_page' ), 20 );
	}

	/**
	 * System badge seeding should be admin-only when the feature is enabled.
	 */
	public function test_system_badge_seeding_uses_admin_lifecycle(): void {
		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'product_badges' => true,
			)
		);

		$enhancements = new Enhancements( new Service_Container() );
		$enhancements->init();

		$this->assertFalse( has_action( 'init', array( Custom_Badge_Taxonomy::class, 'maybe_seed_system_badges' ) ) );
		$this->assertSame( 20, has_action( 'admin_init', array( Custom_Badge_Taxonomy::class, 'maybe_seed_system_badges' ) ) );

		remove_action( 'admin_init', array( Custom_Badge_Taxonomy::class, 'maybe_seed_system_badges' ), 20 );
		delete_option( Feature_Settings::OPTION_KEY );
	}
}
