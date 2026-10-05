<?php
/**
 * Color attribute admin column registration tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Color_Admin_UI;
use Aggressive_Apparel\WooCommerce\Color_Pattern_Admin;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Color_Admin_UI
 */
class TestColorAdminUi extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_GET['taxonomy'], $_SERVER['REQUEST_URI'] );
		remove_all_filters( 'wp_redirect' );
		remove_all_actions( 'load-edit-tags.php' );
		remove_all_filters( 'manage_edit-pa_color_columns' );
		set_current_screen( 'front' );
		parent::tearDown();
	}

	/**
	 * Run the load hook as term.php does, on a given taxonomy screen.
	 *
	 * @param string $taxonomy Screen taxonomy.
	 * @return Color_Admin_UI
	 */
	private function load_term_screen( string $taxonomy ): Color_Admin_UI {
		$ui = new Color_Admin_UI( new Color_Pattern_Admin() );
		$ui->register_color_taxonomy_hooks();

		remove_all_filters( 'manage_edit-pa_color_columns' );
		set_current_screen( 'edit-' . $taxonomy );
		get_current_screen()->taxonomy = $taxonomy;

		do_action( 'load-edit-tags.php' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $ui;
	}

	/**
	 * The editor's "Back to" link is built from the request URL, so the load
	 * hook must never redirect. Reproduces the term.php request that broke:
	 * an admin, a taxonomy query arg, and a percent-encoded referer.
	 *
	 * @return void
	 */
	public function test_load_hook_never_redirects(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['taxonomy']       = 'pa_color';
		$_SERVER['REQUEST_URI'] = '/wp-admin/term.php?taxonomy=pa_color&tag_ID=1&wp_http_referer=%2Fwp-admin%2Fedit-tags.php%3Ftaxonomy%3Dpa_color';

		// Throw instead of letting wp_safe_redirect() reach its exit.
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( 'Redirected to ' . $location );
			}
		);

		$this->load_term_screen( 'pa_color' );
		$this->load_term_screen( 'product_cat' );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @return void
	 */
	public function test_column_registers_only_on_color_screen(): void {
		$ui = $this->load_term_screen( 'product_cat' );
		$this->assertFalse( has_filter( 'manage_edit-pa_color_columns', array( $ui, 'add_color_column' ) ) );

		$ui = $this->load_term_screen( 'pa_color' );
		$this->assertSame( 10, has_filter( 'manage_edit-pa_color_columns', array( $ui, 'add_color_column' ) ) );
	}
}
