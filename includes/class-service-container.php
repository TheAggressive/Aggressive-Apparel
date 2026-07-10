<?php
/**
 * Service Container Class
 *
 * Manages dependency injection and service registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Container for dependency management
 *
 * All services are singletons: the factory is invoked once and the instance
 * is cached for every subsequent get() call.
 *
 * @since 1.0.0
 */
class Service_Container {

	/**
	 * Registered service factories
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Cached service instances (all services are singletons)
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a service factory
	 *
	 * @param string   $key     Service identifier.
	 * @param callable $factory Factory function that returns the service instance.
	 * @return void
	 */
	public function register( string $key, callable $factory ): void {
		$this->services[ $key ] = $factory;
	}

	/**
	 * Get a service instance
	 *
	 * The factory is invoked on first access; subsequent calls return the cached instance.
	 *
	 * @param string $key Service identifier.
	 * @return mixed Service instance.
	 * @throws \InvalidArgumentException If service not registered.
	 */
	public function get( string $key ): mixed {
		if ( isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: %s: service identifier. */
						__( "Service '%s' not registered", 'aggressive-apparel' ),
						$key
					)
				)
			);
		}

		$instance                = $this->services[ $key ]( $this );
		$this->instances[ $key ] = $instance;
		return $instance;
	}
}
