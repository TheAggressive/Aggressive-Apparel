<?php
/**
 * Service Container Class
 *
 * Manages dependency injection and service registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Container for dependency management
 *
 * @since 1.0.0
 */
class Service_Container {

	/**
	 * Registered services
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Shared service flags (whether instances should be cached)
	 *
	 * @var array<string, bool>
	 */
	private array $shared = array();

	/**
	 * Stored instances for shared services
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a service
	 *
	 * @param string   $key     Service identifier.
	 * @param callable $factory Factory function that returns the service.
	 * @param bool     $shared  Whether to share the instance.
	 * @return void
	 */
	public function register( string $key, callable $factory, bool $shared = false ): void {
		$this->services[ $key ] = $factory;
		if ( $shared ) {
			$this->shared[ $key ] = true;
		}
	}

	/**
	 * Get a service instance
	 *
	 * @param string $key Service identifier.
	 * @return mixed Service instance.
	 * @throws \InvalidArgumentException If service not registered.
	 */
	public function get( string $key ): mixed {
		// Return shared instance if available.
		if ( isset( $this->shared[ $key ] ) && isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \InvalidArgumentException( "Service '{$key}' not registered" );
		}

		$instance = $this->services[ $key ]( $this );

		// Cache shared instances.
		if ( isset( $this->shared[ $key ] ) ) {
			$this->instances[ $key ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if service is registered
	 *
	 * @param string $key Service identifier.
	 * @return bool True if registered.
	 */
	public function has( string $key ): bool {
		return isset( $this->services[ $key ] );
	}
}
