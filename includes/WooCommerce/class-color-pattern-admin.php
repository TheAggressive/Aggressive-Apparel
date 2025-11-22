<?php
/**
 * Color Pattern Admin Class
 *
 * Handles admin interface for color pattern uploads and management
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
 * Color Pattern Admin Class
 *
 * Manages pattern image uploads, previews, and updates for color swatches
 *
 * @since 1.0.0
 */
class Color_Pattern_Admin {

	/**
	 * Color attribute name
	 */
	private const ATTRIBUTE_NAME = 'pa_color';

	/**
	 * Allowed image MIME types for patterns
	 */
	private const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Maximum file size for pattern images (2MB)
	 */
	private const MAX_FILE_SIZE = 2097152;

	/**
	 * Register admin hooks
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pattern_scripts' ), 10, 1 );
		add_action( 'wp_ajax_upload_color_pattern', array( $this, 'handle_pattern_upload' ) );
		add_action( 'wp_ajax_delete_color_pattern', array( $this, 'handle_pattern_delete' ) );
	}

	/**
	 * Enqueue pattern upload scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_pattern_scripts( string $hook ): void {
		// Only load on term edit/add pages for the color taxonomy.
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		// Check if we're on the color taxonomy page.
		$current_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( self::ATTRIBUTE_NAME !== $current_taxonomy ) {
			return;
		}

		// Verify user has permission to manage terms.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Verify nonce if present (for security).
		if ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'add-tag' ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'edit-tag' ) ) {
			return;
		}

		// Enqueue media uploader.
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );

		// Enqueue custom pattern script.
		wp_enqueue_script(
			'aggressive-apparel-pattern-admin',
			get_template_directory_uri() . '/build/scripts/admin/color-pattern-admin.js',
			array( 'media-upload', 'thickbox' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Localize script with data.
		wp_localize_script(
			'aggressive-apparel-pattern-admin',
			'aggressiveApparelPattern',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'color_pattern_admin' ),
				'strings'      => array(
					'uploadPattern'   => __( 'Upload Pattern', 'aggressive-apparel' ),
					'changePattern'   => __( 'Change Pattern', 'aggressive-apparel' ),
					'removePattern'   => __( 'Remove Pattern', 'aggressive-apparel' ),
					'selectImage'     => __( 'Select Pattern Image', 'aggressive-apparel' ),
					'useThisImage'    => __( 'Use This Image', 'aggressive-apparel' ),
					'uploadError'     => __( 'Upload failed. Please try again.', 'aggressive-apparel' ),
					'invalidFileType' => __( 'Invalid file type. Please use JPG, PNG, GIF, or WebP.', 'aggressive-apparel' ),
					'fileTooLarge'    => __( 'File too large. Maximum size is 2MB.', 'aggressive-apparel' ),
				),
				'allowedTypes' => self::ALLOWED_MIME_TYPES,
				'maxFileSize'  => self::MAX_FILE_SIZE,
			)
		);

		// Enqueue styles.
		\Aggressive_Apparel\Assets\Asset_Loader::enqueue_style(
			'aggressive-apparel-pattern-admin',
			'build/styles/admin/color-pattern-admin'
		);
	}

	/**
	 * Handle pattern image upload via AJAX
	 *
	 * @return void
	 */
	public function handle_pattern_upload(): void {
		// Verify nonce and capabilities.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'color_pattern_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'aggressive-apparel' ) );
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aggressive-apparel' ) );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid term ID.', 'aggressive-apparel' ) ) );
		}

		// Handle attachment selection from media library.
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'No attachment selected.', 'aggressive-apparel' ) ) );
		}

		// Validate that the attachment exists and is an image.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'aggressive-apparel' ) ) );
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Selected attachment must be an image.', 'aggressive-apparel' ) ) );
		}

		// Update term meta.
		update_term_meta( $term_id, 'color_pattern_id', $attachment_id );
		update_term_meta( $term_id, 'color_type', 'pattern' );

		// Get attachment data for response.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		$thumbnail_url  = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		// Fallback to full image if thumbnail generation failed.
		if ( ! $thumbnail_url ) {
			$thumbnail_url = $attachment_url;
		}

		wp_send_json_success(
			array(
				'attachment_id'  => $attachment_id,
				'attachment_url' => $attachment_url,
				'thumbnail_url'  => $thumbnail_url,
			)
		);
	}

	/**
	 * Handle pattern image deletion via AJAX
	 *
	 * @return void
	 */
	public function handle_pattern_delete(): void {
		// Verify nonce and capabilities.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'color_pattern_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'aggressive-apparel' ) );
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aggressive-apparel' ) );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid term ID.', 'aggressive-apparel' ) ) );
		}

		// Get current pattern ID.
		$pattern_id = get_term_meta( $term_id, 'color_pattern_id', true );
		if ( ! $pattern_id ) {
			wp_send_json_error( array( 'message' => __( 'No pattern found.', 'aggressive-apparel' ) ) );
		}

		// Remove term meta associations (keep the image in media library).
		delete_term_meta( $term_id, 'color_pattern_id' );
		delete_term_meta( $term_id, 'color_type' );

		wp_send_json_success(
			array(
				'message' => __( 'Pattern removed successfully.', 'aggressive-apparel' ),
			)
		);
	}

	/**
	 * Render pattern upload interface for admin forms
	 *
	 * @param int $term_id Term ID (0 for new terms).
	 * @return void
	 */
	public function render_pattern_upload_ui( int $term_id = 0 ): void {
		$pattern_id  = $term_id ? get_term_meta( $term_id, 'color_pattern_id', true ) : '';
		$has_pattern = ! empty( $pattern_id );

		// Get thumbnail URL, with fallback to full image if thumbnail doesn't exist.
		$thumbnail_url = '';
		if ( $has_pattern ) {
			$thumbnail_url = wp_get_attachment_image_url( $pattern_id, 'thumbnail' );
			if ( ! $thumbnail_url ) {
				// Fallback to full image if thumbnail generation failed.
				$thumbnail_url = wp_get_attachment_url( $pattern_id );
			}
		}

		?>
		<div class="aggressive-apparel-color-pattern-admin" data-term-id="<?php echo esc_attr( (string) $term_id ); ?>">
			<div class="aggressive-apparel-color-pattern-admin__preview">
				<?php if ( $has_pattern && $thumbnail_url ) : ?>
					<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php esc_attr_e( 'Pattern preview', 'aggressive-apparel' ); ?>" class="aggressive-apparel-color-pattern-admin__thumbnail" />
					<div class="aggressive-apparel-color-pattern-admin__actions">
						<button type="button" class="button aggressive-apparel-color-pattern-admin__change-button">
							<?php esc_html_e( 'Change Pattern', 'aggressive-apparel' ); ?>
						</button>
						<button type="button" class="button aggressive-apparel-color-pattern-admin__remove-button">
							<?php esc_html_e( 'Remove Pattern', 'aggressive-apparel' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="aggressive-apparel-color-pattern-admin__no-pattern">
						<p><?php esc_html_e( 'No pattern uploaded.', 'aggressive-apparel' ); ?></p>
						<button type="button" class="button aggressive-apparel-color-pattern-admin__upload-button">
							<?php esc_html_e( 'Upload Pattern', 'aggressive-apparel' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
			<input type="hidden" name="color_pattern_id" value="<?php echo esc_attr( $pattern_id ); ?>" />
			<p class="aggressive-apparel-color-pattern-admin__description">
				<?php esc_html_e( 'Upload a pattern image to use instead of a solid color. Recommended size: 100x100px or larger square images.', 'aggressive-apparel' ); ?>
			</p>
		</div>
		<?php
	}
}
