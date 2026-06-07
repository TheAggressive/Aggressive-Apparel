<?php
/**
 * Predictive Search Block — Server Render.
 *
 * Outputs a standalone search form with a live-results dropdown driven by
 * the WooCommerce Store API. Uses Interactivity API for all reactive behaviour.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wp_interactivity_state' ) ) {
	wp_interactivity_state(
		'aggressive-apparel/predictive-search',
		array(
			'restBase'  => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
			'searchUrl' => home_url( '/' ),
		)
	);
}

$placeholder  = isset( $attributes['placeholder'] ) ? sanitize_text_field( (string) $attributes['placeholder'] ) : __( 'Search products…', 'aggressive-apparel' );
$button_label = isset( $attributes['buttonLabel'] ) ? sanitize_text_field( (string) $attributes['buttonLabel'] ) : __( 'Search', 'aggressive-apparel' );
$instance_id  = wp_unique_id( 'ps-' );
$results_id   = esc_attr( $instance_id ) . '-results';

$context = array(
	'instanceId'   => $instance_id,
	'query'        => '',
	'products'     => array(),
	'categories'   => array(),
	'isOpen'       => false,
	'isLoading'    => false,
	'focusedIndex' => -1,
	'totalResults' => 0,
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                      => 'aa-predictive-search',
		'data-wp-interactive'        => 'aggressive-apparel/predictive-search',
		'data-wp-context'            => (string) wp_json_encode( $context ),
		'data-wp-on-document--click' => 'actions.handleClickOutside',
	)
);
?>
<div 
<?php
echo wp_kses(
	$wrapper_attrs,
	array(
		'class'                      => array(),
		'id'                         => array(),
		'style'                      => array(),
		'data-wp-interactive'        => array(),
		'data-wp-context'            => array(),
		'data-wp-on-document--click' => array(),
	)
);
?>
>
	<form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
		<input type="hidden" name="post_type" value="product" />
		<div class="aa-predictive-search__input-wrap">
			<input
				type="search"
				name="s"
				class="aa-predictive-search__input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				autocomplete="off"
				role="combobox"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $results_id ); ?>"
				aria-autocomplete="list"
				aria-activedescendant=""
				data-wp-bind--aria-expanded="state.ariaExpanded"
				data-wp-bind--aria-activedescendant="state.activeDescendant"
				data-wp-on--input="actions.handleInput"
				data-wp-on--focus="actions.handleFocus"
				data-wp-on--keydown="actions.handleKeydown"
			/>
			<button type="submit" class="aa-predictive-search__submit" aria-label="<?php echo esc_attr( $button_label ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="11" cy="11" r="8"/>
					<path d="m21 21-4.35-4.35"/>
				</svg>
			</button>
		</div>
	</form>

	<div
		id="<?php echo esc_attr( $results_id ); ?>"
		class="aa-predictive-search__results"
		role="listbox"
		aria-label="<?php esc_attr_e( 'Search suggestions', 'aggressive-apparel' ); ?>"
		data-wp-watch="callbacks.syncResultsVisibility"
		style="display:none"
		hidden
	>
		<div class="aa-predictive-search__loading" data-wp-bind--hidden="state.isNotLoading" aria-hidden="true" style="display:none" hidden>
			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
			<div class="aa-predictive-search__skeleton-row">
				<div class="aa-predictive-search__skeleton-image"></div>
				<div class="aa-predictive-search__skeleton-text">
					<div class="aa-predictive-search__skeleton-line" style="width:<?php echo esc_attr( ( 60 + $i * 10 ) . '%' ); ?>"></div>
					<div class="aa-predictive-search__skeleton-line" style="width:35%"></div>
				</div>
			</div>
			<?php endfor; ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Searching…', 'aggressive-apparel' ); ?></span>
		</div>

		<div class="aa-predictive-search__products" data-wp-bind--hidden="state.hasNoProducts" hidden>
			<h3 class="aa-predictive-search__heading"><?php esc_html_e( 'Products', 'aggressive-apparel' ); ?></h3>
			<ul class="aa-predictive-search__product-list" role="group" aria-label="<?php esc_attr_e( 'Products', 'aggressive-apparel' ); ?>">
				<template data-wp-each="context.products">
					<li class="aa-predictive-search__product-item" role="option" data-wp-watch="callbacks.syncOptionAttrs">
						<a class="aa-predictive-search__product-link" data-wp-bind--href="context.item.permalink" tabindex="-1">
							<img
								class="aa-predictive-search__product-image no-lazy"
								src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
								data-wp-watch="callbacks.syncResultImage"
								alt=""
								width="56"
								height="56"
							/>
							<div class="aa-predictive-search__product-info">
								<span class="aa-predictive-search__product-name" data-wp-watch="callbacks.highlightName"></span>
								<span class="aa-predictive-search__product-price">
									<span class="screen-reader-text" data-wp-bind--hidden="!context.item.onSale"><?php esc_html_e( 'Regular price:', 'aggressive-apparel' ); ?> </span>
									<span class="aa-predictive-search__regular-price" data-wp-text="context.item.regularPrice"></span>
									<span class="screen-reader-text" data-wp-bind--hidden="!context.item.onSale"><?php esc_html_e( 'Sale price:', 'aggressive-apparel' ); ?> </span>
									<span data-wp-text="context.item.price"></span>
								</span>
							</div>
						</a>
					</li>
				</template>
			</ul>
		</div>

		<div class="aa-predictive-search__categories" data-wp-bind--hidden="state.hasNoCategories" hidden>
			<h3 class="aa-predictive-search__heading"><?php esc_html_e( 'Categories', 'aggressive-apparel' ); ?></h3>
			<ul class="aa-predictive-search__category-list" role="group" aria-label="<?php esc_attr_e( 'Categories', 'aggressive-apparel' ); ?>">
				<template data-wp-each="context.categories">
					<li class="aa-predictive-search__category-item" role="option" data-wp-watch="callbacks.syncOptionAttrs">
						<a class="aa-predictive-search__category-link" data-wp-bind--href="context.item.permalink" data-wp-text="context.item.name" tabindex="-1"></a>
					</li>
				</template>
			</ul>
		</div>

		<div class="aa-predictive-search__empty" data-wp-bind--hidden="state.hasResults" hidden>
			<p><?php esc_html_e( 'No products found.', 'aggressive-apparel' ); ?></p>
		</div>

		<div class="aa-predictive-search__footer" data-wp-bind--hidden="state.hasNoProducts" hidden>
			<a class="aa-predictive-search__view-all" data-wp-bind--href="state.viewAllUrl">
				<?php esc_html_e( 'View all results', 'aggressive-apparel' ); ?> →
			</a>
		</div>

		<div class="screen-reader-text" aria-live="polite" data-wp-text="state.resultAnnouncement"></div>
	</div>
</div>
