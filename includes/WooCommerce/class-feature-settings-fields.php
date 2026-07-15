<?php
/**
 * Feature Settings Fields Class
 *
 * Renders the individual form controls for the Store Enhancements page.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature Settings Fields
 *
 * View layer: every `render_*` method emits a single settings field. Methods
 * are wired up as `add_settings_field` callbacks by Feature_Settings_Page.
 *
 * @since 1.18.0
 */
class Feature_Settings_Fields {

	/**
	 * Render a single toggle checkbox field.
	 *
	 * @param array $args Field arguments containing key and description.
	 * @return void
	 */
	public function render_toggle_field( array $args ): void {
		$key         = $args['key'];
		$enabled     = Feature_Settings::is_enabled( $key );
		$control_id  = isset( $args['label_for'] ) ? (string) $args['label_for'] : 'aa-feature-' . $key;
		$description = (string) ( $args['description'] ?? '' );
		$desc_id     = $control_id . '-desc';

		echo '<span class="aa-features-toggle">';

		printf(
			'<input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s%5$s />',
			esc_attr( $control_id ),
			esc_attr( Feature_Settings::OPTION_KEY ),
			esc_attr( $key ),
			checked( $enabled, true, false ),
			'' !== $description ? ' aria-describedby="' . esc_attr( $desc_id ) . '"' : ''
		);

		if ( '' !== $description ) {
			printf(
				' <span id="%1$s" class="description">%2$s</span>',
				esc_attr( $desc_id ),
				esc_html( $description )
			);
		}

		echo '</span>';
	}

	/**
	 * Render a Store Copy text field.
	 *
	 * @param array $args Field arguments containing option, default, and description.
	 * @return void
	 */
	public function render_store_copy_text_field( array $args ): void {
		$option_name = (string) $args['option'];
		$default     = (string) $args['default'];
		$value       = Feature_Settings::get_store_copy_text( $option_name );
		$desc_id     = $option_name . '-desc';

		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" placeholder="%3$s" class="regular-text" maxlength="60" aria-describedby="%4$s" />',
			esc_attr( $option_name ),
			esc_attr( $value ),
			esc_attr( $default ),
			esc_attr( $desc_id ),
		);

		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $desc_id ),
			esc_html( $args['description'] )
		);
	}

	/**
	 * Render the primary image exit duration slider field.
	 *
	 * @return void
	 */
	public function render_hover_image_exit_duration_field(): void {
		$value   = (int) get_option( Feature_Settings::HOVER_IMAGE_EXIT_DURATION_OPTION, 350 );
		$option  = Feature_Settings::HOVER_IMAGE_EXIT_DURATION_OPTION;
		$desc_id = $option . '-desc';

		printf(
			'<div style="display:flex;align-items:center;gap:10px;">'
			. '<input type="range" name="%1$s" id="%1$s" min="50" max="1500" step="50" value="%2$d"'
			. ' oninput="document.getElementById(\'%1$s_display\').textContent=this.value+\'ms\'"'
			. ' aria-describedby="%3$s" style="width:220px;">'
			. '<span id="%1$s_display" style="min-width:4em;font-weight:600;">%2$dms</span>'
			. '</div>',
			esc_attr( $option ),
			absint( $value ),
			esc_attr( $desc_id ),
		);
		$this->render_field_description(
			$option,
			__( 'How long the original image takes to exit when hovering. 50ms = near instant, 350ms = default, 1500ms = very slow fade.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the primary image exit animation select field.
	 *
	 * @return void
	 */
	public function render_hover_image_exit_animation_field(): void {
		$value   = (string) get_option( Feature_Settings::HOVER_IMAGE_EXIT_ANIMATION_OPTION, 'fade' );
		$options = array(
			'fade'          => __( 'Fade Out (default)', 'aggressive-apparel' ),
			'slide-right'   => __( 'Slide Out Right', 'aggressive-apparel' ),
			'slide-left'    => __( 'Slide Out Left', 'aggressive-apparel' ),
			'slide-up'      => __( 'Slide Out Up', 'aggressive-apparel' ),
			'slide-down'    => __( 'Slide Out Down', 'aggressive-apparel' ),
			'zoom-in'       => __( 'Zoom Out (grows)', 'aggressive-apparel' ),
			'zoom-out'      => __( 'Zoom In (shrinks)', 'aggressive-apparel' ),
			'flip-h'        => __( 'Flip Out Horizontal', 'aggressive-apparel' ),
			'flip-v'        => __( 'Flip Out Vertical', 'aggressive-apparel' ),
			'wipe-right'    => __( 'Wipe Out Right', 'aggressive-apparel' ),
			'wipe-left'     => __( 'Wipe Out Left', 'aggressive-apparel' ),
			'wipe-up'       => __( 'Wipe Out Up', 'aggressive-apparel' ),
			'blur-reveal'   => __( 'Blur Out', 'aggressive-apparel' ),
			'diagonal-wipe' => __( 'Diagonal Wipe Out', 'aggressive-apparel' ),
			'rotate-fade'   => __( 'Rotate & Fade Out', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::HOVER_IMAGE_EXIT_ANIMATION_OPTION, $options, $value );
		$this->render_field_description(
			Feature_Settings::HOVER_IMAGE_EXIT_ANIMATION_OPTION,
			__( 'How the original image exits when the secondary image appears. Pair with the entrance animation below for complementary or contrasting effects.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the hover image animation select field.
	 *
	 * @return void
	 */
	public function render_hover_image_animation_field(): void {
		$value   = (string) get_option( Feature_Settings::HOVER_IMAGE_ANIMATION_OPTION, 'fade' );
		$options = array(
			'fade'          => __( 'Fade', 'aggressive-apparel' ),
			'slide-right'   => __( 'Slide from Right', 'aggressive-apparel' ),
			'slide-left'    => __( 'Slide from Left', 'aggressive-apparel' ),
			'slide-up'      => __( 'Slide from Bottom', 'aggressive-apparel' ),
			'slide-down'    => __( 'Slide from Top', 'aggressive-apparel' ),
			'zoom-in'       => __( 'Zoom In', 'aggressive-apparel' ),
			'zoom-out'      => __( 'Zoom Out', 'aggressive-apparel' ),
			'flip-h'        => __( 'Flip Horizontal', 'aggressive-apparel' ),
			'flip-v'        => __( 'Flip Vertical', 'aggressive-apparel' ),
			'wipe-right'    => __( 'Wipe Left to Right', 'aggressive-apparel' ),
			'wipe-left'     => __( 'Wipe Right to Left', 'aggressive-apparel' ),
			'wipe-up'       => __( 'Wipe Bottom to Top', 'aggressive-apparel' ),
			'blur-reveal'   => __( 'Blur Reveal', 'aggressive-apparel' ),
			'diagonal-wipe' => __( 'Diagonal Wipe', 'aggressive-apparel' ),
			'rotate-fade'   => __( 'Rotate & Fade', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::HOVER_IMAGE_ANIMATION_OPTION, $options, $value );
		$this->render_field_description(
			Feature_Settings::HOVER_IMAGE_ANIMATION_OPTION,
			__( 'Transition used when the secondary image appears on hover. Only applies to products that have at least one gallery image.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the filter layout select field.
	 *
	 * @return void
	 */
	public function render_filter_layout_field(): void {
		$layout  = Feature_Settings::get_filter_layout();
		$options = array(
			'drawer'     => __( 'Drawer (slide-out panel)', 'aggressive-apparel' ),
			'sidebar'    => __( 'Sidebar (persistent column)', 'aggressive-apparel' ),
			'horizontal' => __( 'Horizontal Bar (dropdown filters)', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::FILTER_LAYOUT_OPTION, $options, $layout );
		$this->render_field_description(
			Feature_Settings::FILTER_LAYOUT_OPTION,
			__( 'Choose how filters are displayed on shop pages. Sidebar and Horizontal Bar fall back to Drawer on mobile.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the wishlist button placement select field.
	 *
	 * @return void
	 */
	public function render_wishlist_button_placement_field(): void {
		$placement = (string) get_option( Feature_Settings::WISHLIST_BUTTON_PLACEMENT_OPTION, 'auto' );
		$options   = array(
			'auto'  => __( 'Automatic (single product page)', 'aggressive-apparel' ),
			'block' => __( 'Manual placement (use Wishlist Button block)', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::WISHLIST_BUTTON_PLACEMENT_OPTION, $options, $placement );
		$this->render_field_description(
			Feature_Settings::WISHLIST_BUTTON_PLACEMENT_OPTION,
			__( 'Controls the single product page heart. Shop cards can also get a heart from Quick View → “Wishlist on Product Images” when both features are on.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the Quick View card trigger style field.
	 *
	 * @return void
	 */
	public function render_quick_view_trigger_style_field(): void {
		$style   = Feature_Settings::get_quick_view_trigger_style();
		$options = array(
			'corner'     => __( 'Corner chip (recommended for transparent product shots)', 'aggressive-apparel' ),
			'bottom-bar' => __( 'Bottom action bar', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION, $options, $style );
		$this->render_field_description(
			Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION,
			__( 'Corner and bottom-bar keep transparent PNGs readable. On touch devices, controls stay as small chips — the product image still opens the product page.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the Quick View card corner position field.
	 *
	 * @return void
	 */
	public function render_quick_view_trigger_position_field(): void {
		$position = Feature_Settings::get_quick_view_trigger_position();
		$options  = array(
			'top-right'    => __( 'Top right', 'aggressive-apparel' ),
			'top-left'     => __( 'Top left', 'aggressive-apparel' ),
			'bottom-right' => __( 'Bottom right', 'aggressive-apparel' ),
			'bottom-left'  => __( 'Bottom left', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::QUICK_VIEW_TRIGGER_POSITION_OPTION, $options, $position );
		$this->render_field_description(
			Feature_Settings::QUICK_VIEW_TRIGGER_POSITION_OPTION,
			__( 'Used by Corner chip (and Wishlist in the stack). Bottom action bar always sits along the bottom edge. Center style places Quick View in the middle and still parks Wishlist in this corner.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render whether Wishlist joins the Quick View media stack.
	 *
	 * @return void
	 */
	public function render_quick_view_media_wishlist_field(): void {
		$value   = (string) get_option( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION, 'with_wishlist' );
		$options = array(
			'with_wishlist'   => __( 'Include Wishlist heart in the image action stack', 'aggressive-apparel' ),
			'quick_view_only' => __( 'Quick View only (keep Wishlist via block / title row)', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION, $options, $value );
		$this->render_field_description(
			Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION,
			__( 'When included, listing cards hide the Wishlist Button block on the image stack so you do not get two hearts. Single product placement still follows Wishlist Button Placement on the Customer Engagement tab.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the load more mode select field.
	 *
	 * @return void
	 */
	public function render_load_more_mode_field(): void {
		$mode    = (string) get_option( Feature_Settings::LOAD_MORE_MODE_OPTION, 'load_more' );
		$options = array(
			'load_more'       => __( 'Load More Button', 'aggressive-apparel' ),
			'infinite_scroll' => __( 'Infinite Scroll', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::LOAD_MORE_MODE_OPTION, $options, $mode );
		$this->render_field_description(
			Feature_Settings::LOAD_MORE_MODE_OPTION,
			__( 'Load More shows a button; Infinite Scroll loads automatically as users scroll down.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the source mix as slider cards (one per source, weight 0 = off).
	 *
	 * @return void
	 */
	public function render_social_proof_sources_field(): void {
		$current_sources = Feature_Settings::get_social_proof_sources();
		$definitions     = Feature_Settings::get_social_proof_source_definitions();

		echo '<div class="aa-sp-sources">';

		foreach ( $definitions as $key => $meta ) {
			$weight   = isset( $current_sources[ $key ] ) ? (int) $current_sources[ $key ] : 0;
			$input_id = 'aa-sp-source-' . $key;

			echo '<div class="aa-sp-source' . ( 0 === $weight ? ' is-off' : '' ) . '">';
			echo '<div class="aa-sp-source__head">';
			echo '<label class="aa-sp-source__label" for="' . esc_attr( $input_id ) . '">' . esc_html( $meta['label'] ) . '</label>';
			printf(
				'<input type="range" id="%s" class="aa-sp-source__slider" min="0" max="10" step="1" name="%s[%s]" value="%d" aria-describedby="%s-desc" />',
				esc_attr( $input_id ),
				esc_attr( Feature_Settings::SOCIAL_PROOF_SOURCES_OPTION ),
				esc_attr( $key ),
				absint( $weight ),
				esc_attr( $input_id ),
			);
			printf(
				'<output class="aa-sp-source__value" for="%s" data-off-label="%s">%s</output>',
				esc_attr( $input_id ),
				esc_attr__( 'Off', 'aggressive-apparel' ),
				0 === $weight ? esc_html__( 'Off', 'aggressive-apparel' ) : absint( $weight ),
			);
			echo '</div>';
			echo '<p id="' . esc_attr( $input_id ) . '-desc" class="aa-sp-source__description description">' . esc_html( $meta['description'] ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Weight 0 disables a source. Higher weights appear more often in the random rotation. Engagement uses catalog sales totals (requires the minimum sales threshold below). Set Engagement to 0 until you have steady sales. Set Purchases to 0 on day one if you prefer only trust + engagement + announcements.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the trust messages textarea.
	 *
	 * @return void
	 */
	public function render_social_proof_trust_messages_field(): void {
		$value   = Feature_Settings::get_social_proof_trust_messages();
		$desc_id = Feature_Settings::SOCIAL_PROOF_TRUST_MESSAGES_OPTION . '-desc';

		printf(
			'<textarea id="%1$s" name="%1$s" rows="8" cols="60" class="large-text code" style="font-family: ui-sans-serif, system-ui, sans-serif;" aria-describedby="%2$s">%3$s</textarea>',
			esc_attr( Feature_Settings::SOCIAL_PROOF_TRUST_MESSAGES_OPTION ),
			esc_attr( $desc_id ),
			esc_textarea( $value ),
		);
		echo '<p id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'One message per line. Optional icon prefix — put PREFIX| before the visible text; PREFIX may be (a) a theme SVG slug such as check, heart, cart, info, warning, … or (b) a secure https URL to a small PNG/SVG/WebP/GIF badge. Prefix none| to force plain text without an icon even when another line uses one. Lines starting with # and blank lines are ignored.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the announcements textarea.
	 *
	 * @return void
	 */
	public function render_social_proof_announcements_field(): void {
		$value   = Feature_Settings::get_social_proof_announcements();
		$desc_id = Feature_Settings::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION . '-desc';

		printf(
			'<textarea id="%1$s" name="%1$s" rows="5" cols="60" class="large-text code" style="font-family: ui-sans-serif, system-ui, sans-serif;" placeholder="%2$s" aria-describedby="%3$s">%4$s</textarea>',
			esc_attr( Feature_Settings::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION ),
			esc_attr__( "gift|Spring drop launches Friday\nhttps://cdn.example.com/sale-dot.png | Free shipping today only", 'aggressive-apparel' ),
			esc_attr( $desc_id ),
			esc_textarea( $value ),
		);
		echo '<p id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'Short-term promos and seasonal copy — same PREFIX|MESSAGE icon rules as Trust Messages. Prefix none| to force text-only. Set the Announcements weight above 0 to include them in the rotation.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render optional purchase-notification thumbnail badge picker.
	 *
	 * @return void
	 */
	public function render_social_proof_purchase_badge_icon_field(): void {
		$value   = Feature_Settings::resolve_social_proof_purchase_badge_icon_slug();
		$option  = Feature_Settings::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION;
		$desc_id = $option . '-desc';

		printf(
			'<select id="%1$s" name="%1$s" aria-describedby="%2$s">',
			esc_attr( $option ),
			esc_attr( $desc_id ),
		);
		printf(
			'<option value="" %s>%s</option>',
			selected( $value, '', false ),
			esc_html__( 'None', 'aggressive-apparel' ),
		);

		$icons = Icons::list();

		sort( $icons );

		foreach ( $icons as $slug ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $value, $slug, false ),
				esc_html( $slug ),
			);
		}

		echo '</select>';

		echo '<p id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'Tiny SVG pinned to the thumbnail corner on purchase / engagement / demo notifications. Choosing “None” removes it permanently after save. Icons reference & customization sits directly below.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Explain social proof PREFIX lines, thumbnail badges, and icon extension points.
	 *
	 * @return void
	 */
	public function render_social_proof_icon_help_field(): void {
		$icon_slugs = Icons::list();
		sort( $icon_slugs );
		$list = implode( ', ', $icon_slugs );

		echo '<ul class="description" style="list-style:disc;margin-left:1.25em;max-width:42rem;">';
		echo '<li>' . esc_html__( 'Trust Messages and Custom Announcements: each line uses PREFIX|message. PREFIX is either a slug from the expandable list below (same slugs appear in the badge dropdown) or a full https URL to your own PNG/SVG/WebP badge. Prefix none| to force plain text on that row.', 'aggressive-apparel' ) . '</li>';
		echo '<li>' . esc_html__( 'Slides that include a WooCommerce thumbnail only show icons as the thumbnail-corner badge—not the PREFIX column—to avoid crowding.', 'aggressive-apparel' ) . '</li>';
		echo '</ul>';

		echo '<details style="max-width:42rem;margin-top:0.75em;">';
		echo '<summary style="cursor:pointer;">' . esc_html__( 'Built-in icon slugs (copy into PREFIX|)', 'aggressive-apparel' ) . '</summary>';
		echo '<p class="description" style="margin:0.75em 0 0;"><code style="white-space:normal;word-break:break-word;">' . esc_html( $list ) . '</code></p>';
		echo '</details>';

		echo '<p class="description">';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: Inline code snippet with PHP filter name. */
				__( 'Developers — register additional icons via the %s filter (child theme recommended). Use a string for a single path (viewBox 0 0 24 24), or an array with viewBox, paths, and optional circles, polygons, and rects.', 'aggressive-apparel' ),
				'<code>aggressive_apparel_icon_definitions</code>'
			),
		);
		echo '</p>';
	}

	/**
	 * Render Engagement minimum lifetime sales threshold.
	 *
	 * @return void
	 */
	public function render_social_proof_engagement_min_sales_field(): void {
		$value   = Feature_Settings::get_social_proof_engagement_min_sales();
		$option  = Feature_Settings::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION;
		$desc_id = $option . '-desc';

		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="1" max="999999" step="1" style="width: 8em;" aria-describedby="%3$s" />',
			esc_attr( $option ),
			absint( $value ),
			esc_attr( $desc_id ),
		);

		echo '<p id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'Only products whose WooCommerce lifetime total sales reach this number are eligible for Engagement toasts together with thumbnail + optional badge. Typical starting values: 2–5 for new shops, higher when you want only strong sellers promoted.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the purchase display mode select.
	 *
	 * @return void
	 */
	public function render_social_proof_display_mode_field(): void {
		$mode    = (string) get_option( Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION, 'anonymous' );
		$options = array(
			'anonymous'  => __( 'Anonymous — "Someone in [Location] purchased X" (recommended)', 'aggressive-apparel' ),
			'initial'    => __( 'Initial only — "S. in [Location] purchased X"', 'aggressive-apparel' ),
			'first_name' => __( 'First name — "Sarah from [Location] purchased X" (requires checkout consent)', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION, $options, $mode );
		$this->render_field_description(
			Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION,
			__( 'Affects only the Real Purchases source. Anonymous is the safest choice in most jurisdictions; First Name should be paired with explicit checkout consent.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the location granularity select.
	 *
	 * @return void
	 */
	public function render_social_proof_location_granularity_field(): void {
		$value   = (string) get_option( Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, 'city' );
		$options = array(
			'city'    => __( 'City — "Portland"', 'aggressive-apparel' ),
			'state'   => __( 'State / Region — "Oregon"', 'aggressive-apparel' ),
			'country' => __( 'Country — "United States"', 'aggressive-apparel' ),
			'hidden'  => __( 'Hide location entirely', 'aggressive-apparel' ),
		);

		$this->render_select( Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, $options, $value );
		$this->render_field_description(
			Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION,
			__( 'Lower granularity = more anonymity. State / Region is a good compromise for small markets where city + product can identify a customer.', 'aggressive-apparel' )
		);
	}

	/**
	 * Render the minimum order age input.
	 *
	 * @return void
	 */
	public function render_social_proof_min_order_age_field(): void {
		$value   = (int) get_option( Feature_Settings::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION, 5 );
		$option  = Feature_Settings::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION;
		$desc_id = $option . '-desc';

		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="0" max="1440" step="1" style="width: 6em;" aria-describedby="%3$s" />',
			esc_attr( $option ),
			absint( $value ),
			esc_attr( $desc_id ),
		);
		echo '<p id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'Orders younger than this number of minutes are excluded from the rotation. Default 5. Recommended 5–10 to prevent unique product + city + exact-time combinations from identifying individual customers.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the demo preview checkbox.
	 *
	 * @return void
	 */
	public function render_social_proof_demo_field(): void {
		$enabled = (bool) get_option( Feature_Settings::SOCIAL_PROOF_DEMO_OPTION, false );
		$option  = Feature_Settings::SOCIAL_PROOF_DEMO_OPTION;
		$desc_id = $option . '-desc';

		echo '<span class="aa-features-toggle">';
		printf(
			'<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s aria-describedby="%3$s" />',
			esc_attr( $option ),
			checked( $enabled, true, false ),
			esc_attr( $desc_id ),
		);
		echo ' <span id="' . esc_attr( $desc_id ) . '" class="description">' . esc_html__( 'Show a sample notification first in the rotation so you can preview the design. Visible only to logged-in users with the “Edit Theme Options” capability — customers never see the preview, even when this is on. An indicator appears in your admin bar while it is active.', 'aggressive-apparel' ) . '</span>';
		echo '</span>';
	}

	/**
	 * Render a single-select dropdown bound to an option.
	 *
	 * @param string                $option_name Option key used as the field name and id.
	 * @param array<string, string> $options     Map of value => label.
	 * @param string                $selected    Currently selected value.
	 * @return void
	 */
	private function render_select( string $option_name, array $options, string $selected ): void {
		$desc_id = $option_name . '-desc';

		printf(
			'<select id="%1$s" name="%1$s" aria-describedby="%2$s">',
			esc_attr( $option_name ),
			esc_attr( $desc_id )
		);
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
	}

	/**
	 * Print a description paragraph wired to a control via aria-describedby.
	 *
	 * Call after render_select() so the option id + "-desc" matches.
	 *
	 * @param string $option_name Option key used as the control id.
	 * @param string $text        Description text.
	 * @return void
	 */
	private function render_field_description( string $option_name, string $text ): void {
		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $option_name . '-desc' ),
			esc_html( $text )
		);
	}
}
