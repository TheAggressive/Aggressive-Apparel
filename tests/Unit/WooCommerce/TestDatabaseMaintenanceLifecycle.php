<?php
/**
 * Database maintenance lifecycle tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Service_Container;
use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Installer;
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
	 * Back-in-stock schema checks should be admin-only.
	 */
	public function test_back_in_stock_installer_uses_admin_lifecycle(): void {
		$installer = new Back_In_Stock_Installer();
		$installer->init();

		$this->assertFalse( has_action( 'init', array( $installer, 'maybe_install' ) ) );
		$this->assertSame( 5, has_action( 'admin_init', array( $installer, 'maybe_install' ) ) );

		remove_action( 'admin_init', array( $installer, 'maybe_install' ), 5 );
	}

	/**
	 * A pending schema migration must pause the frontend feature safely.
	 */
	public function test_back_in_stock_feature_fails_closed_until_schema_is_current(): void {
		delete_option( 'aggressive_apparel_bis_db_version' );
		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'back_in_stock' => true,
			)
		);

		$container    = new Service_Container();
		$enhancements = new Enhancements( $container );
		$enhancements->init();
		$installer     = $container->get( Back_In_Stock_Installer::class );
		$back_in_stock = $container->get( Back_In_Stock::class );

		$this->assertSame( 5, has_action( 'admin_init', array( $installer, 'maybe_install' ) ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( $back_in_stock, 'enqueue_assets' ) ) );

		remove_action( 'admin_init', array( $installer, 'maybe_install' ), 5 );
		delete_option( Feature_Settings::OPTION_KEY );
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
