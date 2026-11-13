<?php
/**
 * Tests for the Cache_Health Site Health test.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Cache_Health;
use WP_UnitTestCase;

/**
 * Tests for the Cache_Health class.
 *
 * @covers \Aggressive_Apparel\Core\Cache_Health
 */
class Cache_Health_Test extends WP_UnitTestCase {

	private const TEST_ID = 'aggressive_apparel_object_cache';

	/**
	 * Cache_Health instance under test.
	 *
	 * @var Cache_Health
	 */
	private Cache_Health $health;

	/**
	 * Previous external-object-cache flag, restored in tearDown.
	 *
	 * @var bool|null
	 */
	private $previous_ext_cache = null;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->health             = new Cache_Health();
		$this->previous_ext_cache = wp_using_ext_object_cache();
	}

	/**
	 * Restore the object-cache flag mutated by force_object_cache().
	 */
	public function tearDown(): void {
		wp_using_ext_object_cache( (bool) $this->previous_ext_cache );
		parent::tearDown();
	}

	/**
	 * Force wp_using_ext_object_cache() to a fixed value for the assertion.
	 *
	 * @param bool $using Whether an external object cache is active.
	 */
	private function force_object_cache( bool $using ): void {
		wp_using_ext_object_cache( $using );
	}

	/**
	 * The init() method wires the Site Health filter.
	 */
	public function test_init_registers_site_status_filter(): void {
		$this->health->init();

		$this->assertNotFalse(
			has_filter( 'site_status_tests', array( $this->health, 'add_tests' ) ),
			'init() should register the site_status_tests filter.'
		);
	}

	/**
	 * The add_tests() method adds a direct test under the expected key with a callable.
	 */
	public function test_add_tests_registers_direct_test(): void {
		$tests = $this->health->add_tests(
			array(
				'direct' => array(),
				'async'  => array(),
			) 
		);

		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
		$this->assertSame(
			array( $this->health, 'test_object_cache' ),
			$tests['direct'][ self::TEST_ID ]['test']
		);
	}

	/**
	 * The add_tests() method preserves other registered tests.
	 */
	public function test_add_tests_preserves_existing_tests(): void {
		$existing = array(
			'direct' => array(
				'wordpress_version' => array(
					'label' => 'WP',
					'test'  => '__return_true',
				),
			),
			'async'  => array( 'background_updates' => array() ),
		);

		$tests = $this->health->add_tests( $existing );

		$this->assertArrayHasKey( 'wordpress_version', $tests['direct'] );
		$this->assertArrayHasKey( 'background_updates', $tests['async'] );
		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
	}

	/**
	 * The add_tests() method tolerates a payload with no 'direct' bucket.
	 */
	public function test_add_tests_tolerates_missing_direct_bucket(): void {
		$tests = $this->health->add_tests( array() );

		$this->assertIsArray( $tests['direct'] );
		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
	}

	/**
	 * Without a persistent object cache the test recommends one, with remediation.
	 */
	public function test_recommends_object_cache_without_one(): void {
		$this->force_object_cache( false );

		$result = $this->health->test_object_cache();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( self::TEST_ID, $result['test'] );
		$this->assertSame( 'orange', $result['badge']['color'] );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertStringContainsString( 'How to fix', $result['description'] );
		$this->assertStringContainsString( 'Redis', $result['description'] );
	}

	/**
	 * With a persistent object cache the test reports good and drops the actions link.
	 */
	public function test_reports_good_with_object_cache(): void {
		$this->force_object_cache( true );

		$result = $this->health->test_object_cache();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( self::TEST_ID, $result['test'] );
		$this->assertSame( 'blue', $result['badge']['color'] );
		$this->assertArrayNotHasKey( 'actions', $result );
	}

	/**
	 * Both branches expose the shape Site Health renders.
	 */
	public function test_result_has_required_site_health_keys(): void {
		foreach ( array( true, false ) as $using ) {
			$this->force_object_cache( $using );
			$result = $this->health->test_object_cache();

			foreach ( array( 'label', 'status', 'badge', 'description', 'test' ) as $key ) {
				$this->assertArrayHasKey( $key, $result, "Missing '{$key}' when ext cache = " . ( $using ? 'true' : 'false' ) );
			}
			$this->assertArrayHasKey( 'label', $result['badge'] );
			$this->assertArrayHasKey( 'color', $result['badge'] );
		}
	}
}
