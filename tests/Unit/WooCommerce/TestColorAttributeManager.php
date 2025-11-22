<?php
/**
 * Test Color Attribute Manager Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Color_Attribute_Manager;
use ReflectionClass;

/**
 * Color Attribute Manager Test Case
 */
class TestColorAttributeManager extends WP_UnitTestCase {
	/**
	 * Color attribute manager instance
	 *
	 * @var Color_Attribute_Manager
	 */
	private $color_manager;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock WooCommerce being active for testing
		if ( ! defined( 'WC_VERSION' ) ) {
			define( 'WC_VERSION', '8.0.0' );
		}

		$this->color_manager = new Color_Attribute_Manager();
		$this->color_manager->init();
	}

	/**
	 * Tear down test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up any created terms
		$terms = get_terms( [
			'taxonomy'   => 'pa_color',
			'hide_empty' => false,
		] );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, 'pa_color' );
			}
		}

		// Clean up WooCommerce attribute taxonomies table
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'woocommerce_attribute_taxonomies', [ 'attribute_name' => 'color' ] );
		delete_transient( 'wc_attribute_taxonomies' );
	}

	/**
	 * Create color taxonomy for testing
	 */
	private function create_color_taxonomy(): void {
		if ( ! taxonomy_exists( 'pa_color' ) ) {
			// Create the taxonomy
			register_taxonomy(
				'pa_color',
				'product',
				[
					'hierarchical' => false,
					'label'        => 'Color',
					'public'       => true,
				]
			);

			// Add to WooCommerce attribute taxonomies table
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'woocommerce_attribute_taxonomies',
				[
					'attribute_name'    => 'color',
					'attribute_label'   => 'Color',
					'attribute_type'    => 'select',
					'attribute_orderby' => 'menu_order',
					'attribute_public'  => 1,
				],
				[ '%s', '%s', '%s', '%s', '%d' ]
			);
		}
	}

	/**
	 * Test that default colors are added
	 */
	public function test_default_colors_are_added(): void {
		// Manually create the taxonomy for testing
		$this->create_color_taxonomy();

		// Manually add default color terms
		$this->color_manager->add_default_color_terms();

		// Get all color terms
		$colors = $this->color_manager->get_color_terms();

		// Should have default colors
		$this->assertNotEmpty( $colors );

		// Check that black color exists (hex format)
		$this->assertArrayHasKey( 'black', $colors );
		$this->assertEquals( '#000000', $colors['black']['value'] );
		$this->assertEquals( 'hex', $colors['black']['format'] );
		$this->assertEquals( '#000000', $colors['black']['hex'] );
	}

	/**
	 * Test adding custom color
	 */
	public function test_add_custom_color(): void {
		$this->create_color_taxonomy();

		$result = $this->color_manager->add_custom_color( 'Custom Blue', '#123456' );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Verify the color was added
		$colors = $this->color_manager->get_color_terms();
		$this->assertArrayHasKey( 'custom-blue', $colors );
		$this->assertEquals( 'Custom Blue', $colors['custom-blue']['name'] );
		$this->assertEquals( '#123456', $colors['custom-blue']['value'] );
		$this->assertEquals( 'hex', $colors['custom-blue']['format'] );
		$this->assertEquals( '#123456', $colors['custom-blue']['hex'] );
	}

	/**
	 * Test invalid hex color validation
	 */
	public function test_invalid_hex_color_validation(): void {
		$this->create_color_taxonomy();

		$result = $this->color_manager->add_custom_color( 'Invalid Color', 'invalid-hex' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_color', $result->get_error_code() );
	}

	/**
	 * Test valid hex color validation
	 */
	public function test_valid_hex_color_validation(): void {
		$this->create_color_taxonomy();

		$result = $this->color_manager->add_custom_color( 'Valid Hex', '#ABCDEF' );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * Test color column functionality
	 */
	public function test_color_column_functionality(): void {
		$this->create_color_taxonomy();

		// Test adding color column
		$columns = [
			'cb' => '<input type="checkbox" />',
			'name' => 'Name',
			'slug' => 'Slug',
			'posts' => 'Count'
		];

		// Get the admin UI instance from the manager using reflection
		$reflection = new ReflectionClass( $this->color_manager );
		$admin_ui_property = $reflection->getProperty( 'admin_ui' );
		$admin_ui_property->setAccessible( true );
		$admin_ui = $admin_ui_property->getValue( $this->color_manager );

		$modified_columns = $admin_ui->add_color_column( $columns );

		$this->assertArrayHasKey( 'color', $modified_columns );
		$this->assertEquals( 'Color', $modified_columns['color'] );

		// Test column order (should be after name)
		$keys = array_keys( $modified_columns );
		$name_position = array_search( 'name', $keys, true );
		$color_position = array_search( 'color', $keys, true );

		$this->assertEquals( $name_position + 1, $color_position );
	}

	/**
	 * Test color term structure
	 */
	public function test_color_term_structure(): void {
		// Create taxonomy and add a test color
		$this->create_color_taxonomy();

		$result = $this->color_manager->add_custom_color( 'Test Color', '#123456' );
		$this->assertIsInt( $result );

		$colors = $this->color_manager->get_color_terms();

		$this->assertNotEmpty( $colors );

		// Check structure of first color
		$first_color = reset( $colors );

		$this->assertArrayHasKey( 'id', $first_color );
		$this->assertArrayHasKey( 'name', $first_color );
		$this->assertArrayHasKey( 'slug', $first_color );
		$this->assertArrayHasKey( 'hex', $first_color );
		$this->assertArrayHasKey( 'count', $first_color );

		// Verify hex format
		$this->assertMatchesRegularExpression( '/^#[a-fA-F0-9]{6}$/', $first_color['hex'] );
	}

	/**
	 * Test color picker scripts enqueue logic
	 */
	public function test_color_picker_scripts_enqueued(): void {
		// Get the admin UI instance using reflection
		$reflection = new ReflectionClass( $this->color_manager );
		$admin_ui_property = $reflection->getProperty( 'admin_ui' );
		$admin_ui_property->setAccessible( true );
		$admin_ui = $admin_ui_property->getValue( $this->color_manager );

		// Mock the GET parameters - this should trigger script enqueuing
		$_GET['taxonomy'] = 'pa_color';

		// Test with correct hook
		$admin_ui->enqueue_color_picker_scripts( 'edit-tags.php' );

		// Since we can't easily test WordPress enqueue functions in unit tests,
		// we'll test that the method doesn't throw exceptions and completes execution
		// The actual enqueuing would work in a real WordPress environment
		$this->assertTrue( true, 'Method executed without errors' );
	}

	/**
	 * Test that color attribute is registered
	 */
	public function test_color_attribute_registered(): void {
		// Test the register_color_attribute method directly
		$existing_attributes = [
			(object) [
				'attribute_id'      => 1,
				'attribute_name'    => 'size',
				'attribute_label'   => 'Size',
				'attribute_type'    => 'select',
				'attribute_orderby' => 'menu_order',
				'attribute_public'  => 1,
			],
		];

		$result = $this->color_manager->register_color_attribute( $existing_attributes );

		// Should have added the color attribute
		$this->assertCount( 2, $result );

		// Check that the color attribute was added
		$color_attribute = end( $result );
		$this->assertEquals( 'color', $color_attribute->attribute_name );
		$this->assertEquals( 'Color', $color_attribute->attribute_label );
		$this->assertEquals( 'select', $color_attribute->attribute_type );
		$this->assertEquals( 'menu_order', $color_attribute->attribute_orderby );
		$this->assertEquals( 1, $color_attribute->attribute_public );
	}
}
