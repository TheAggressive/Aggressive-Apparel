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
 * @covers \Aggressive_Apparel\Core\Cache_Health
 */
class Cache_Health_Test extends WP_UnitTestCase {

	private const TEST_ID = 'aggressive_apparel_object_cache';

	/**
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
	 * init() wires the Site Health filter.
	 */
	public function test_init_registers_site_status_filter(): void {
		$this->health->init();

		$this->assertNotFalse(
			has_filter( 'site_status_tests', array( $this->health, 'add_tests' ) ),
			'init() should register the site_status_tests filter.'
		);
	}

	/**
	 * add_tests() adds a direct test under the expected key with a callable.
	 */
	public function test_add_tests_registers_direct_test(): void {
		$tests = $this->health->add_tests( array( 'direct' => array(), 'async' => array() ) );

		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
		$this->assertSame(
			array( $this->health, 'test_object_cache' ),
			$tests['direct'][ self::TEST_ID ]['test']
		);
	}

	/**
	 * add_tests() preserves other registered tests.
	 */
	public function test_add_tests_preserves_existing_tests(): void {
		$existing = array(
			'direct' => array( 'wordpress_version' => array( 'label' => 'WP', 'test' => '__return_true' ) ),
			'async'  => array( 'background_updates' => array() ),
		);

		$tests = $this->health->add_tests( $existing );

		$this->assertArrayHasKey( 'wordpress_version', $tests['direct'] );
		$this->assertArrayHasKey( 'background_updates', $tests['async'] );
		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
	}

	/**
	 * add_tests() tolerates a payload with no 'direct' bucket.
	 */
	public function test_add_tests_tolerates_missing_direct_bucket(): void {
		$tests = $this->health->add_tests( array() );

		$this->assertIsArray( $tests['direct'] );
		$this->assertArrayHasKey( self::TEST_ID, $tests['direct'] );
	}

	/**
	 * Without a persistent object cache the test reports critical + remediation.
	 */
	public function test_reports_critical_without_object_cache(): void {
		$this->force_object_cache( false );

		$result = $this->health->test_object_cache();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( self::TEST_ID, $result['test'] );
		$this->assertSame( 'red', $result['badge']['color'] );
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
				$this->assertArrayHasKey( $key, $result, "Missing '{$key}' when ext cache = " . var_export( $using, true ) );
			}
			$this->assertArrayHasKey( 'label', $result['badge'] );
			$this->assertArrayHasKey( 'color', $result['badge'] );
		}
	}
}
