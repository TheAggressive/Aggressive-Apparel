<?php
/**
 * Audience Landing Pages
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Clean, indexable URLs for one Collection (audience) across the catalog:
 * `/shop/mens/` (every Men's product) and `/shop/mens/t-shirts/` (the T-Shirts
 * category, Men's only).
 *
 * They are not new terms. Each route is the shop or a category archive with
 * the audience applied exactly as `?collection=mens` is (Catalog_Url_Filters),
 * so a product keeps one category and its Collection terms, and the filters,
 * Load More and facets work unchanged. Only Collection terms with products get
 * a route, and a top-level product category with the same slug keeps its own
 * archive.
 *
 * Each page gets its own H1, title, canonical and breadcrumb (with Rank Math
 * also description, robots and a sitemap); an empty combination is noindex. A
 * single-audience `?collection=` URL and the Collection attribute archive
 * redirect here, so each selection has one address.
 */
final class Audience_Landing_Pages {

	/** Query var carrying the audience slug. */
	public const QUERY_VAR = 'aa_audience';

	/** The Collection attribute taxonomy. */
	public const TAXONOMY = 'pa_collection';

	/** URL parameter for the Collection filter (Catalog_Url_Filters). */
	private const PARAM = 'collection';

	/** Option holding a hash of the registered rules, to flush when they change. */
	private const RULES_OPTION = 'aggressive_apparel_audience_rules';

	/** Register hooks. */
	public function init(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		// After WooCommerce registers its taxonomies and permalinks.
		add_action( 'init', array( $this, 'register_rules' ), 20 );
		add_filter( 'redirect_canonical', array( $this, 'keep_landing_url' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ), 5 );
		add_filter( 'get_the_archive_title', array( $this, 'archive_title' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ) );
		add_filter( 'woocommerce_get_breadcrumb', array( $this, 'breadcrumbs' ), 20 );
		add_filter( 'rank_math/frontend/breadcrumb/items', array( $this, 'breadcrumbs' ) );
		add_filter( 'rank_math/frontend/title', array( $this, 'seo_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'seo_description' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );
		add_filter( 'rank_math/frontend/robots', array( $this, 'robots' ) );
		add_filter( 'rank_math/sitemap/providers', array( $this, 'sitemap_providers' ) );
	}

	/**
	 * Collection terms that get landing pages, keyed by slug.
	 *
	 * @return array<string, \WP_Term>
	 */
	public static function audiences(): array {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return array();
		}

		$terms     = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => true,
			)
		);
		$audiences = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$audiences[ $term->slug ] = $term;
			}
		}
		if ( array() === $audiences ) {
			return array();
		}

		// A top-level category with the same slug keeps its own archive.
		$taken = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'slug'       => array_keys( $audiences ),
				'parent'     => 0,
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		foreach ( is_array( $taken ) ? $taken : array() as $slug ) {
			unset( $audiences[ (string) $slug ] );
		}

		return $audiences;
	}

	/** The audience of the current request, or '' (also on a 404). */
	public static function current(): string {
		if ( is_404() ) {
			return '';
		}

		$audience = get_query_var( self::QUERY_VAR );

		return is_string( $audience ) && isset( self::audiences()[ $audience ] ) ? $audience : '';
	}

	/**
	 * A landing page URL.
	 *
	 * @param string        $audience Audience slug.
	 * @param \WP_Term|null $category Product category, or null for every product.
	 * @param int           $page     Page number.
	 */
	public static function url( string $audience, ?\WP_Term $category = null, int $page = 1 ): string {
		global $wp_rewrite;

		$path = self::base() . '/' . $audience . '/';
		if ( null !== $category ) {
			$path .= $category->slug . '/';
		}
		if ( $page > 1 ) {
			$path .= $wp_rewrite->pagination_base . '/' . $page . '/';
		}

		return home_url( user_trailingslashit( $path, 'category' ) );
	}

	/**
	 * Add the audience query var.
	 *
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public function query_vars( $vars ): array {
		$vars   = is_array( $vars ) ? $vars : array();
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Route `/{category base}/{audience}/` and `/{category base}/{audience}/{category}/`
	 * (with pagination), flushing once whenever the routes change.
	 */
	public function register_rules(): void {
		$rules = self::rules();
		foreach ( $rules as $regex => $query ) {
			add_rewrite_rule( $regex, $query, 'top' );
		}

		// WordPress rebuilds the cached rules, these included, when it next
		// parses a request (this one: `init` runs first).
		$hash = md5( (string) wp_json_encode( $rules ) );
		if ( get_option( self::RULES_OPTION ) !== $hash ) {
			delete_option( 'rewrite_rules' );
			update_option( self::RULES_OPTION, $hash, true );
		}
	}

	/**
	 * The rewrite rules, most specific first.
	 *
	 * @return array<string, string>
	 */
	public static function rules(): array {
		global $wp_rewrite;

		$audiences = array_keys( self::audiences() );
		if ( array() === $audiences ) {
			return array();
		}

		$base  = preg_quote( self::base(), '#' );
		$slugs = implode( '|', array_map( static fn( string $slug ): string => preg_quote( $slug, '#' ), $audiences ) );
		$page  = preg_quote( $wp_rewrite->pagination_base, '#' );
		$query = 'index.php?' . self::QUERY_VAR . '=$matches[1]';

		return array(
			"^{$base}/({$slugs})/{$page}/([0-9]{1,})/?$" => $query . '&post_type=product&paged=$matches[2]',
			"^{$base}/({$slugs})/?$"                     => $query . '&post_type=product',
			"^{$base}/({$slugs})/([^/]+)/{$page}/([0-9]{1,})/?$" => $query . '&product_cat=$matches[2]&paged=$matches[3]',
			"^{$base}/({$slugs})/([^/]+)/?$"             => $query . '&product_cat=$matches[2]',
		);
	}

	/**
	 * Keep a landing URL: core would send a category's landing page to the
	 * category's own link.
	 *
	 * @param string|false $redirect_url Where core would redirect.
	 * @return string|false
	 */
	public function keep_landing_url( $redirect_url ) {
		return '' !== self::current() ? false : $redirect_url;
	}

	/** Send other addresses for a landing page's selection to it. */
	public function redirect(): void {
		$target = self::redirect_target();
		if ( '' !== $target ) {
			wp_safe_redirect( $target, 301, 'Aggressive Apparel' );
			exit;
		}
	}

	/**
	 * Where the current request belongs, or '' when it already is there.
	 *
	 * - The shop or a category with `?collection=` naming one audience → its
	 *   landing page.
	 * - A landing page with `?collection=` → the landing page when that only
	 *   repeats its audience, else the plain archive with every pick.
	 * - The Collection attribute archive of an audience → its landing page.
	 * - A landing page reached through `?aa_audience=` → its path.
	 */
	public static function redirect_target(): string {
		$audiences = self::audiences();
		if ( array() === $audiences ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only catalog URL.
		$query  = wp_unslash( $_GET );
		$query  = is_array( $query ) ? $query : array();
		$picked = Catalog_Filter_Set::normalize_slugs( is_string( $query[ self::PARAM ] ?? null ) ? $query[ self::PARAM ] : '' );
		unset( $query[ self::PARAM ] );
		$current  = self::current();
		$category = self::queried_category();
		$page     = max( 1, (int) get_query_var( 'paged' ) );

		if ( '' !== $current ) {
			// Reached as `?aa_audience=` rather than through its path.
			$raw = isset( $query[ self::QUERY_VAR ] );
			unset( $query[ self::QUERY_VAR ] );
			if ( array() === $picked ) {
				return $raw ? self::with_query( self::url( $current, $category, $page ), $query ) : '';
			}
			$picked = array_values( array_unique( array_merge( array( $current ), $picked ) ) );
			if ( array( $current ) === $picked ) {
				return self::with_query( self::url( $current, $category, $page ), $query );
			}
			$archive = null !== $category ? get_term_link( $category ) : get_post_type_archive_link( 'product' );

			return is_string( $archive ) ? self::with_query( $archive, $query + array( self::PARAM => implode( ',', $picked ) ) ) : '';
		}

		if ( is_tax( self::TAXONOMY ) && array() === $picked ) {
			$term = get_queried_object();

			return $term instanceof \WP_Term && isset( $audiences[ $term->slug ] ) ? self::with_query( self::url( $term->slug, null, $page ), $query ) : '';
		}

		$on_archive = is_post_type_archive( 'product' ) || null !== $category;
		if ( $on_archive && 1 === count( $picked ) && isset( $audiences[ $picked[0] ] ) ) {
			return self::with_query( self::url( $picked[0], $category, $page ), $query );
		}

		return '';
	}

	/**
	 * The H1: "Men's T-Shirts", or "Men's" for every product.
	 *
	 * @param string $title Archive title.
	 */
	public function archive_title( $title ): string {
		$heading = self::heading();

		return '' !== $heading ? $heading : (string) $title;
	}

	/**
	 * The document title without an SEO plugin.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function document_title( $parts ): array {
		$parts   = is_array( $parts ) ? $parts : array();
		$heading = self::heading();
		if ( '' !== $heading ) {
			$parts['title'] = $heading;
		}

		return $parts;
	}

	/**
	 * Insert the audience after the shop: Home › Shop › Men's › T-Shirts.
	 *
	 * Serves WooCommerce's trail and Rank Math's (which feeds its schema); both
	 * are lists of [text, url, …].
	 *
	 * @param mixed $crumbs Breadcrumb trail.
	 * @return mixed
	 */
	public function breadcrumbs( $crumbs ) {
		$audience = self::current();
		if ( '' === $audience || ! is_array( $crumbs ) || array() === $crumbs ) {
			return $crumbs;
		}

		$label    = self::audiences()[ $audience ]->name;
		$category = self::queried_category();
		$shop     = (string) get_post_type_archive_link( 'product' );
		$crumbs   = array_values( $crumbs );

		$at = count( $crumbs ) - 1;
		foreach ( $crumbs as $i => $crumb ) {
			if ( is_array( $crumb ) && isset( $crumb[1] ) && is_string( $crumb[1] ) && '' !== $shop && untrailingslashit( $crumb[1] ) === untrailingslashit( $shop ) ) {
				$at = $i + 1;
				break;
			}
		}

		if ( null === $category ) {
			// The shop is the last crumb: link it, and end on the audience.
			$last = count( $crumbs ) - 1;
			if ( is_array( $crumbs[ $last ] ) && '' !== $shop ) {
				$crumbs[ $last ][1] = $shop;
			}
			$crumbs[] = array( $label, self::url( $audience ) );

			return $crumbs;
		}

		array_splice( $crumbs, min( $at, count( $crumbs ) - 1 ), 0, array( array( $label, self::url( $audience ) ) ) );

		return $crumbs;
	}

	/**
	 * Rank Math title: the audience ahead of the archive's own SEO title.
	 *
	 * @param string $title Title.
	 */
	public function seo_title( $title ): string {
		$audience = self::current();

		return '' !== $audience ? self::audiences()[ $audience ]->name . ' ' . $title : (string) $title;
	}

	/**
	 * Rank Math description: the page's heading ahead of the archive's own.
	 *
	 * @param string $description Description.
	 */
	public function seo_description( $description ): string {
		$heading     = self::heading();
		$description = trim( (string) $description );
		if ( '' === $heading ) {
			return $description;
		}

		return '' !== $description ? $heading . ' — ' . $description : $heading;
	}

	/**
	 * Rank Math canonical (and og:url): the landing page itself.
	 *
	 * @param string $canonical Canonical URL.
	 */
	public function canonical( $canonical ): string {
		$audience = self::current();
		if ( '' === $audience ) {
			return (string) $canonical;
		}

		return self::url( $audience, self::queried_category(), max( 1, (int) get_query_var( 'paged' ) ) );
	}

	/**
	 * Rank Math robots: an empty combination isn't worth indexing.
	 *
	 * @param array<string, string> $robots Robots directives.
	 * @return array<string, string>
	 */
	public function robots( $robots ): array {
		global $wp_query;

		$robots = is_array( $robots ) ? $robots : array();
		if ( '' !== self::current() && $wp_query instanceof \WP_Query && 0 === (int) $wp_query->found_posts ) {
			$robots['index'] = 'noindex';
		}

		return $robots;
	}

	/**
	 * Add the landing pages' sitemap to Rank Math's.
	 *
	 * @param array<int, object> $providers Sitemap providers.
	 * @return array<int, object>
	 */
	public function sitemap_providers( $providers ): array {
		$providers = is_array( $providers ) ? $providers : array();
		if ( interface_exists( '\RankMath\Sitemap\Providers\Provider' ) ) {
			$providers[] = new Audience_Sitemap_Provider();
		}

		return $providers;
	}

	/**
	 * Every landing page with products, newest change first per page.
	 *
	 * @return list<array{loc: string, mod: string}>
	 */
	public static function sitemap_links(): array {
		$links      = array();
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
		foreach ( array_keys( self::audiences() ) as $audience ) {
			$modified = self::last_modified( $audience, null );
			if ( '' === $modified ) {
				continue;
			}
			$links[] = array(
				'loc' => self::url( $audience ),
				'mod' => $modified,
			);

			foreach ( is_array( $categories ) ? $categories : array() as $category ) {
				if ( ! $category instanceof \WP_Term ) {
					continue;
				}
				$modified = self::last_modified( $audience, $category );
				if ( '' !== $modified ) {
					$links[] = array(
						'loc' => self::url( $audience, $category ),
						'mod' => $modified,
					);
				}
			}
		}

		return $links;
	}

	/**
	 * When the newest visible product of a landing page changed (GMT), or ''
	 * when it has none.
	 *
	 * @param string        $audience Audience slug.
	 * @param \WP_Term|null $category Product category.
	 */
	private static function last_modified( string $audience, ?\WP_Term $category ): string {
		$tax_query = array(
			array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $audience,
			),
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		);
		if ( null !== $category ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $category->term_id,
			);
		}

		$ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'tax_query'        => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed product taxonomies; runs only when the sitemap is rebuilt.
			)
		);
		$id  = (int) ( $ids[0] ?? 0 );

		return $id > 0 ? (string) get_post_modified_time( 'c', true, $id ) : '';
	}

	/** The current landing page's heading, or ''. */
	private static function heading(): string {
		$audience = self::current();
		if ( '' === $audience ) {
			return '';
		}

		$label    = self::audiences()[ $audience ]->name;
		$category = self::queried_category();

		return null !== $category ? $label . ' ' . $category->name : $label;
	}

	/** The product category being browsed, if any. */
	private static function queried_category(): ?\WP_Term {
		$term = is_tax( 'product_cat' ) ? get_queried_object() : null;

		return $term instanceof \WP_Term ? $term : null;
	}

	/** Pathname the landing pages live under, e.g. `/shop/`. */
	public static function base_path(): string {
		$path = wp_parse_url( home_url( '/' . self::base() . '/' ), PHP_URL_PATH );

		return is_string( $path ) ? $path : '/' . self::base() . '/';
	}

	/** WooCommerce's category base, e.g. `shop`. */
	private static function base(): string {
		$structure = function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure() : array();
		$base      = trim( (string) ( $structure['category_rewrite_slug'] ?? '' ), '/' );

		return '' !== $base ? $base : 'product-category';
	}

	/**
	 * A URL with the request's other query parameters.
	 *
	 * @param string               $url   URL.
	 * @param array<string, mixed> $query Query parameters.
	 */
	private static function with_query( string $url, array $query ): string {
		// The route's own variables are in the path.
		unset( $query['product_cat'], $query['post_type'], $query['paged'] );
		$query = array_filter( $query, static fn( $value ): bool => is_string( $value ) && '' !== $value );

		return array() !== $query ? add_query_arg( array_map( 'rawurlencode', $query ), $url ) : $url;
	}
}
