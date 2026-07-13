<?php
/**
 * Mobile Bottom Navigation Class
 *
 * Renders a fixed bottom navigation bar on mobile with Home, Search,
 * Cart (with count badge), and Account. Hides on scroll-down, shows
 * on scroll-up.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mobile Bottom Nav
 *
 * @since 1.18.0
 */
class Mobile_Bottom_Nav {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_head', array( $this, 'print_critical_css' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_bottom_nav' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Inline critical mobile bottom-nav spacing so body padding exists before footer markup.
	 *
	 * @return void
	 */
	public function print_critical_css(): void {
		if ( is_admin() ) {
			return;
		}

		echo aggressive_apparel_trusted_html( '<style id="aa-bottom-nav-critical">@media(width<1024px){:root{--aa-bottom-nav-height:calc(3.5rem + env(safe-area-inset-bottom,0px))}body{padding-bottom:var(--aa-bottom-nav-height)}}</style>' ) . "\n";
	}

	/**
	 * Enqueue styles and register Interactivity API script module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-mobile-bottom-nav',
			'build/styles/woocommerce/mobile-bottom-nav'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/bottom-nav',
			'build/interactivity/bottom-nav',
			array(
				'@aggressive-apparel/scroll-lock',
				'@aggressive-apparel/use-overlay',
			)
		);
	}

	/**
	 * Add body class for CSS targeting.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function add_body_class( array $classes ): array {
		if ( ! is_admin() ) {
			$classes[] = 'has-bottom-nav';
		}
		return $classes;
	}

	/**
	 * Render the bottom navigation bar.
	 *
	 * @return void
	 */
	public function render_bottom_nav(): void {
		if ( is_admin() ) {
			return;
		}

		$cart_count = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
			$cart_count = WC()->cart->get_cart_contents_count();
		}

		$cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '/cart/';
		$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '/my-account/';

		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/bottom-nav',
				array(
					'cartCount'        => $cart_count,
					'isHiddenByScroll' => false,
					'isSearchOpen'     => false,
					'cartApiUrl'       => esc_url_raw( rest_url( 'wc/store/v1/cart' ) ),
				),
			);
		}

		$icon_attrs = array(
			'width'  => 20,
			'height' => 20,
		);
		?>

		<nav
			class="aa-bottom-nav"
			data-wp-interactive="aggressive-apparel/bottom-nav"
			data-wp-init="callbacks.init"
			data-wp-class--is-hidden="state.isHiddenByScroll"
			aria-label="<?php esc_attr_e( 'Mobile navigation', 'aggressive-apparel' ); ?>"
		>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="aa-bottom-nav__item" aria-label="<?php esc_attr_e( 'Home', 'aggressive-apparel' ); ?>">
				<?php Icons::render( 'home', $icon_attrs ); ?>
				<span class="aa-bottom-nav__label"><?php esc_html_e( 'Home', 'aggressive-apparel' ); ?></span>
			</a>

			<button
				type="button"
				class="aa-bottom-nav__item"
				data-wp-on--click="actions.toggleSearch"
				aria-label="<?php esc_attr_e( 'Search', 'aggressive-apparel' ); ?>"
				aria-haspopup="dialog"
				aria-controls="aa-bottom-nav-search-panel"
				data-wp-bind--aria-expanded="state.isSearchOpen"
			>
				<?php Icons::render( 'search', $icon_attrs ); ?>
				<span class="aa-bottom-nav__label"><?php esc_html_e( 'Search', 'aggressive-apparel' ); ?></span>
			</button>

			<a href="<?php echo esc_url( $cart_url ); ?>" class="aa-bottom-nav__item aa-bottom-nav__item--cart" aria-label="<?php esc_attr_e( 'Cart', 'aggressive-apparel' ); ?>" data-wp-bind--aria-label="state.cartAriaLabel">
				<?php Icons::render( 'cart', $icon_attrs ); ?>
				<span
					class="aa-bottom-nav__badge"
					data-wp-text="state.cartCount"
					data-wp-bind--hidden="state.hasEmptyCart"
					<?php echo $cart_count > 0 ? '' : 'hidden'; ?>
				><?php echo esc_html( (string) $cart_count ); ?></span>
				<span class="aa-bottom-nav__label"><?php esc_html_e( 'Cart', 'aggressive-apparel' ); ?></span>
			</a>

			<a href="<?php echo esc_url( $account_url ); ?>" class="aa-bottom-nav__item" aria-label="<?php esc_attr_e( 'Account', 'aggressive-apparel' ); ?>">
				<?php Icons::render( 'user', $icon_attrs ); ?>
				<span class="aa-bottom-nav__label"><?php esc_html_e( 'Account', 'aggressive-apparel' ); ?></span>
			</a>

			<?php if ( Feature_Settings::is_enabled( 'wishlist' ) ) : ?>
				<a href="<?php echo esc_url( Wishlist::get_page_url() ); ?>" class="aa-bottom-nav__item" aria-label="<?php esc_attr_e( 'Wishlist', 'aggressive-apparel' ); ?>">
					<?php Icons::render( 'heart-outline', $icon_attrs ); ?>
					<span class="aa-bottom-nav__label"><?php esc_html_e( 'Wishlist', 'aggressive-apparel' ); ?></span>
				</a>
			<?php endif; ?>
		</nav>

		<div
			class="aggressive-apparel-overlay aggressive-apparel-overlay--top-panel aa-bottom-nav__search-overlay"
			data-wp-interactive="aggressive-apparel/bottom-nav"
			data-wp-class--is-open="state.isSearchOpen"
			hidden
		>
			<div class="aggressive-apparel-overlay__backdrop aa-bottom-nav__search-backdrop" data-wp-on--click="actions.closeSearch"></div>
			<div
				id="aa-bottom-nav-search-panel"
				class="aggressive-apparel-panel aggressive-apparel-panel--search-bar aa-bottom-nav__search-panel"
				role="dialog"
				aria-modal="true"
				aria-label="<?php esc_attr_e( 'Search products', 'aggressive-apparel' ); ?>"
				tabindex="-1"
			>
				<form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" class="aa-bottom-nav__search-form">
					<input
						type="search"
						name="s"
						class="aa-bottom-nav__search-input"
						placeholder="<?php esc_attr_e( 'Search products…', 'aggressive-apparel' ); ?>"
						aria-label="<?php esc_attr_e( 'Search products', 'aggressive-apparel' ); ?>"
						autocomplete="off"
					/>
					<input type="hidden" name="post_type" value="product" />
					<button type="button" class="aa-bottom-nav__search-close" data-wp-on--click="actions.closeSearch" aria-label="<?php esc_attr_e( 'Close search', 'aggressive-apparel' ); ?>">
						<?php
						Icons::render(
							'close',
							array(
								'width'  => 24,
								'height' => 24,
							)
						);
						?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}
