<?php
/**
 * Site Search
 *
 * Thin coordinator for the full-screen search modal (aggressive-apparel/search
 * block). Delegates to focused services for the REST API, result building, index
 * maintenance, visibility policy, and modal UI.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Site search bootstrap and public facade.
 */
class Search {

	/**
	 * Interactivity store namespace (shared by trigger + modal).
	 *
	 * @var string
	 */
	public const STORE = Search_Modal::STORE;

	/**
	 * Search index and maintenance service.
	 *
	 * @var Search_Index
	 */
	private Search_Index $index;

	/**
	 * REST autocomplete handler.
	 *
	 * @var Search_Rest
	 */
	private Search_Rest $rest;

	/**
	 * Modal shell and asset wiring.
	 *
	 * @var Search_Modal
	 */
	private Search_Modal $modal;

	/**
	 * Construct the search service.
	 *
	 * @param Search_Index|null $index Optional index override for tests.
	 */
	public function __construct( ?Search_Index $index = null ) {
		$this->index = $index ?? new Search_Index();
		$results     = new Search_Results( $this->index );
		$this->rest  = new Search_Rest( $this->index, $results );
		$this->modal = new Search_Modal();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->index->init();
		add_action( 'rest_api_init', array( $this->rest, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this->modal, 'register_assets' ), 5 );
		add_action( 'aggressive_apparel_search_block_rendered', array( $this->modal, 'mark_block_rendered' ) );
		add_action( 'wp_footer', array( $this->modal, 'render_shell' ) );
	}

	/**
	 * Handle a search request.
	 *
	 * Public facade for integration tests.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->rest->handle( $request );
	}
}
