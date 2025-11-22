<?php
/**
 * Color Admin UI Class
 *
 * Handles admin interface for color attributes
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Admin UI Class
 *
 * Handles admin interface elements for color attributes
 *
 * @since 1.0.0
 */
class Color_Admin_UI {

	/**
	 * Color attribute name
	 */
	private const ATTRIBUTE_NAME = 'pa_color';

	/**
	 * Register admin hooks
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'add_color_term_meta' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker_scripts' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'register_color_taxonomy_hooks' ), 99 );
	}

	/**
	 * Add color term meta fields
	 *
	 * @return void
	 */
	public function add_color_term_meta(): void {
		$attribute_name = self::ATTRIBUTE_NAME;

		add_action( $attribute_name . '_add_form_fields', array( $this, 'add_color_field' ), 10, 0 );
		add_action( $attribute_name . '_edit_form_fields', array( $this, 'edit_color_field' ), 10, 1 );
		add_action( 'created_' . $attribute_name, array( $this, 'save_color_field' ), 10, 1 );
		add_action( 'edited_' . $attribute_name, array( $this, 'save_color_field' ), 10, 1 );
	}

	/**
	 * Add color field to add term form
	 *
	 * @return void
	 */
	public function add_color_field(): void {
		?>
		<div class="form-field">
			<label for="color_value"><?php esc_html_e( 'Color', 'aggressive-apparel' ); ?></label>
			<input type="text" name="color_value" id="color_value" class="color-picker" value="#000000" />
			<p><?php esc_html_e( 'Choose a color for this product attribute.', 'aggressive-apparel' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add color field to edit term form
	 *
	 * @param \WP_Term $term Term object.
	 * @return void
	 */
	public function edit_color_field( \WP_Term $term ): void {
		$color_value = get_term_meta( $term->term_id, 'color_value', true );

		// Fallback to hex for backward compatibility.
		if ( empty( $color_value ) ) {
			$hex_value   = get_term_meta( $term->term_id, 'color_hex', true );
			$color_value = $hex_value ? $hex_value : '#000000';
		}
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="color_value"><?php esc_html_e( 'Color', 'aggressive-apparel' ); ?></label>
			</th>
			<td>
				<input type="text" name="color_value" id="color_value" class="color-picker" value="<?php echo esc_attr( $color_value ); ?>" />
				<p class="description"><?php esc_html_e( 'Choose a color for this product attribute.', 'aggressive-apparel' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save color field value
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_color_field( int $term_id ): void {
		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $term_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Default to hex format for user-friendly color picker.
		$color_format = 'hex';
		$color_value  = isset( $_POST['color_value'] ) ? sanitize_hex_color( wp_unslash( $_POST['color_value'] ) ) : '';

		// Validate color value.
		if ( ! empty( $color_value ) && $this->validate_color_value( $color_value, $color_format ) ) {
			update_term_meta( $term_id, 'color_value', $color_value );
			update_term_meta( $term_id, 'color_format', $color_format );

			// Keep backward compatibility with hex field.
			update_term_meta( $term_id, 'color_hex', $color_value );
		} else {
			// Clear color data if invalid.
			delete_term_meta( $term_id, 'color_value' );
			delete_term_meta( $term_id, 'color_format' );
			delete_term_meta( $term_id, 'color_hex' );
		}
	}

	/**
	 * Validate color value based on format
	 *
	 * @param string $color_value Color value to validate.
	 * @param string $color_format Color format ('hex' or 'oklch').
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_color_value( string $color_value, string $color_format ): bool {
		switch ( $color_format ) {
			case 'hex':
				return (bool) preg_match( '/^#[a-fA-F0-9]{6}$/', $color_value );
			case 'oklch':
				// OKLCH format: oklch(L% C H) or oklch(L% C H / A).
				return (bool) preg_match(
					'/^oklch\(\s*\d*\.?\d+%?\s+\d*\.?\d+\s+\d*\.?\d+(?:\s*\/\s*\d*\.?\d+)?\s*\)$/i',
					$color_value
				);
			default:
				return false;
		}
	}

	/**
	 * Register taxonomy column hooks
	 *
	 * @return void
	 */
	public function register_color_taxonomy_hooks(): void {
		$attribute_name = self::ATTRIBUTE_NAME;

		// Register hooks when loading the edit-tags page.
		add_action(
			'load-edit-tags.php',
			function () use ( $attribute_name ) {
				// Security: Verify we're in admin context and user has permissions.
				if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
					return;
				}

				// Security: Require and verify nonce for ALL admin UI display logic.
				$nonce_action = 'color_admin_ui_display_' . get_current_user_id();
				if ( ! isset( $_GET['_color_ui_nonce'] ) ||
					! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_color_ui_nonce'] ) ), $nonce_action ) ) {
					// Missing or invalid nonce - deny access.
					return;
				}

				$current_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

				// Additional security: Verify taxonomy parameter is safe.
				if ( empty( $current_taxonomy ) || ! taxonomy_exists( $current_taxonomy ) ) {
					return;
				}

				if ( $current_taxonomy === $attribute_name ) {
					add_filter( 'manage_edit-' . $attribute_name . '_columns', array( $this, 'add_color_column' ), 10, 1 );
					add_action( 'manage_' . $attribute_name . '_custom_column', array( $this, 'populate_color_column' ), 10, 3 );
				}
			}
		);

		// Also register for known taxonomies if they exist.
		if ( taxonomy_exists( $attribute_name ) ) {
			add_filter( 'manage_edit-' . $attribute_name . '_columns', array( $this, 'add_color_column' ), 10, 1 );
			add_action( 'manage_' . $attribute_name . '_custom_column', array( $this, 'populate_color_column' ), 10, 3 );
		}
	}

	/**
	 * Add color column to the taxonomy table
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_color_column( array $columns ): array {
		// Insert color column after the name column.
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'name' === $key ) {
				$new_columns['color'] = __( 'Color', 'aggressive-apparel' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate the color column with color swatches
	 *
	 * @param string $content    Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id    Term ID.
	 * @return void
	 */
	public function populate_color_column( string $content, string $column_name, int $term_id ): void {
		if ( 'color' !== $column_name ) {
			return;
		}

		$color_value = get_term_meta( $term_id, 'color_value', true );

		// Fallback to hex for backward compatibility.
		if ( empty( $color_value ) ) {
			$hex_value   = get_term_meta( $term_id, 'color_hex', true );
			$color_value = $hex_value ? $hex_value : '#000000';
		}

		// Ensure we have a valid hex color.
		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $color_value ) ) {
			$color_value = '#000000'; // Default to black if invalid.
		}

		// Output color swatch with inline styling.
		printf(
			'<div class="color-swatch" style="background-color: %s; width: 20px; height: 20px; border: 1px solid #ccc; border-radius: 50%%; display: inline-block; margin: 0 auto;" title="%s"></div>',
			esc_attr( $color_value ),
			esc_attr( $color_value )
		);
	}

	/**
	 * Enqueue color picker scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_color_picker_scripts( string $hook ): void {
		$attribute_name = self::ATTRIBUTE_NAME;

		// Only load on term edit/add pages for the color taxonomy.
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		// Security check: verify user capabilities.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Verify nonce for security when processing GET parameters.
		$nonce_verified = false;
		if ( isset( $_REQUEST['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'taxonomy' ) ) {
			$nonce_verified = true;
		}

		// If no valid nonce, still allow if user has proper capabilities (fallback for WordPress admin system).
		if ( ! $nonce_verified && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Sanitize and validate taxonomy parameter.
		$current_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( $attribute_name !== $current_taxonomy ) {
			return;
		}

		// Enqueue WordPress color picker.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Initialize color picker.
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(document).ready(function($){
				$(".color-picker").wpColorPicker({
					change: function(event, ui) {
						// Update the input value when color changes
						$(this).val(ui.color.toString());
					}
				});

				// Handle color swatch interactions
				$(document).on("click", ".color-swatch-interactive", function() {
					var $swatch = $(this);
					var $radio = $swatch.prev("input[type=radio]");
					if ($radio.length) {
						$radio.prop("checked", true).trigger("change");
					}
				});

				$(document).on("keydown", ".color-swatch-interactive", function(e) {
					if (e.key === "Enter" || e.key === " ") {
						e.preventDefault();
						$(this).trigger("click");
					}
				});
			});'
		);
	}
}
