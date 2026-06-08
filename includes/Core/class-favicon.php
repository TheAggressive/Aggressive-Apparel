<?php
/**
 * Favicon Class
 *
 * Manages light/dark mode favicons. Adds a "Dark Mode Site Icon" field to
 * Settings → General and swaps the favicon in real time on the front end,
 * honouring both the OS preference and the theme's manual dark-mode toggle.
 *
 * @package Aggressive_Apparel
 * @since 1.88.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Favicon
 *
 * @since 1.88.0
 */
class Favicon {

	/**
	 * WordPress option key for the dark favicon attachment ID.
	 */
	private const OPTION_KEY = 'aggressive_apparel_dark_favicon_id';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Front-end only: intercept WP's favicon output and replace with our own.
		// Priority 1 ensures our script runs before wp_site_icon() at priority 99,
		// so the browser receives the correct href on first parse — no swap, no flash.
		add_filter( 'site_icon_meta_tags', array( $this, 'remove_icon_link_tags' ) );
		add_action( 'wp_head', array( $this, 'output_head_tags' ), 1 );
	}

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

	/**
	 * Register the dark favicon setting and add it to Settings → General.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'general',
			self::OPTION_KEY,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		add_settings_section(
			'aggressive_apparel_site_icons',
			__( 'Dark Mode Site Icon', 'aggressive-apparel' ),
			array( $this, 'render_section_description' ),
			'general'
		);

		add_settings_field(
			self::OPTION_KEY,
			__( 'Dark Mode Icon', 'aggressive-apparel' ),
			array( $this, 'render_field' ),
			'general',
			'aggressive_apparel_site_icons'
		);
	}

	/**
	 * Enqueue the WP media uploader on Settings → General only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'options-general.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	/**
	 * Render the section intro text.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Set a separate icon to display when visitors are using dark mode. The Site Icon above is used for light mode.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the dark favicon upload field.
	 *
	 * @return void
	 */
	public function render_field(): void {
		$attachment_id = $this->get_saved_id();
		$image_url     = '';
		$has_image     = false;

		if ( $attachment_id ) {
			$image_data = wp_get_attachment_image_src( $attachment_id, array( 64, 64 ) );
			if ( $image_data ) {
				$image_url = $image_data[0];
				$has_image = true;
			}
		}
		?>
		<div id="aa-dark-favicon-container" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
			<div
				id="aa-dark-favicon-preview"
				style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;overflow:hidden;flex-shrink:0;"
			>
				<?php if ( $has_image ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" width="64" height="64" style="object-fit:contain;" alt="">
				<?php else : ?>
					<span style="color:#8c8f94;font-size:11px;text-align:center;padding:4px;line-height:1.3;">
						<?php esc_html_e( 'No icon', 'aggressive-apparel' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<input
				type="hidden"
				id="<?php echo esc_attr( self::OPTION_KEY ); ?>"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
			/>

			<div style="display:flex;flex-direction:column;gap:0.4rem;">
				<button type="button" class="button" id="aa-dark-favicon-upload">
					<?php echo $has_image ? esc_html__( 'Change dark mode icon', 'aggressive-apparel' ) : esc_html__( 'Select dark mode icon', 'aggressive-apparel' ); ?>
				</button>
				<a
					href="#"
					id="aa-dark-favicon-remove"
					style="font-size:13px;<?php echo $has_image ? '' : 'display:none;'; ?>"
				>
					<?php esc_html_e( 'Remove dark mode icon', 'aggressive-apparel' ); ?>
				</a>
			</div>
		</div>
		<p class="description" style="margin-top:0.5rem;">
			<?php esc_html_e( 'Recommended: 512×512 PNG or SVG. Shown when visitors use dark mode via OS preference or the theme toggle.', 'aggressive-apparel' ); ?>
		</p>
		<script>
		( function () {
			var frame      = null;
			var uploadBtn  = document.getElementById( 'aa-dark-favicon-upload' );
			var removeLink = document.getElementById( 'aa-dark-favicon-remove' );
			var preview    = document.getElementById( 'aa-dark-favicon-preview' );
			var input      = document.getElementById( '<?php echo esc_js( self::OPTION_KEY ); ?>' );

			uploadBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title:    '<?php echo esc_js( __( 'Select Dark Mode Site Icon', 'aggressive-apparel' ) ); ?>',
					button:   { text: '<?php echo esc_js( __( 'Use as dark mode icon', 'aggressive-apparel' ) ); ?>' },
					multiple: false,
					library:  { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					input.value    = attachment.id;
					preview.innerHTML = '<img src="' + attachment.url + '" width="64" height="64" style="object-fit:contain;" alt="">';
					uploadBtn.textContent = '<?php echo esc_js( __( 'Change dark mode icon', 'aggressive-apparel' ) ); ?>';
					removeLink.style.display = 'inline';
				} );
				frame.open();
			} );

			removeLink.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				input.value = '0';
				preview.innerHTML = '<span style="color:#8c8f94;font-size:11px;text-align:center;padding:4px;line-height:1.3;"><?php echo esc_js( __( 'No icon', 'aggressive-apparel' ) ); ?></span>';
				uploadBtn.textContent = '<?php echo esc_js( __( 'Select dark mode icon', 'aggressive-apparel' ) ); ?>';
				removeLink.style.display = 'none';
			} );
		}() );
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Front end
	// -------------------------------------------------------------------------

	/**
	 * Remove <link rel="icon"> tags from wp_site_icon()'s output when a dark
	 * favicon is configured. The apple-touch-icon and msapplication meta tags
	 * are left intact — only the browser-tab favicon needs dark-mode handling.
	 *
	 * @param string[] $meta_tags Tags produced by wp_site_icon().
	 * @return string[] Filtered tag list.
	 */
	public function remove_icon_link_tags( array $meta_tags ): array {
		if ( ! $this->get_saved_id() ) {
			return $meta_tags;
		}

		return array_values(
			array_filter(
				$meta_tags,
				static function ( string $tag ): bool {
					return strpos( $tag, 'rel="icon"' ) === false;
				}
			)
		);
	}

	/**
	 * Output static <link rel="icon"> tags and a manual-toggle script.
	 *
	 * WHY STATIC TAGS: Chromium's favicon pre-scanner reads <link rel="icon">
	 * directly from the HTML source before JavaScript executes. JS-created link
	 * elements are invisible to it, so it falls back to /favicon.ico (the WP
	 * logo). Static tags fix this completely.
	 *
	 * Two tags are output so OS preference is handled in pure HTML:
	 *   1. Dark link with media="(prefers-color-scheme: dark)" — Chrome/Firefox/Edge.
	 *   2. Light link with no media attribute — universal fallback; Safari (which
	 *      ignores media on icons) picks this one because it is listed last.
	 *
	 * JS handles only the manual localStorage override from the dark-mode toggle.
	 *
	 * Falls back silently when no dark favicon is saved, leaving wp_site_icon()
	 * to output the standard tags unmodified (the filter is a no-op in that case).
	 *
	 * @return void
	 */
	public function output_head_tags(): void {
		$dark_id = $this->get_saved_id();
		if ( ! $dark_id ) {
			return;
		}

		$dark_url  = wp_get_attachment_url( $dark_id );
		$light_url = has_site_icon() ? get_site_icon_url( 32 ) : '';

		if ( ! $dark_url ) {
			return;
		}

		if ( $light_url ) {
			// Dark first, light last — Safari uses the last <link rel="icon">.
			printf( "<link rel=\"icon\" href=\"%s\" media=\"(prefers-color-scheme: dark)\">\n", esc_url( $dark_url ) );
			printf( "<link rel=\"icon\" id=\"aa-favicon\" href=\"%s\">\n", esc_url( $light_url ) );
		} else {
			// No light favicon configured — show the dark icon for all modes.
			printf( "<link rel=\"icon\" id=\"aa-favicon\" href=\"%s\">\n", esc_url( $dark_url ) );
		}

		// JS is only needed when there are two icons to swap between.
		if ( ! $light_url ) {
			return;
		}
		?>
		<script>
		( function () {
			var darkUrl   = <?php echo wp_json_encode( $dark_url ); ?>;
			var lightUrl  = <?php echo wp_json_encode( $light_url ); ?>;
			var mainLink  = document.getElementById( 'aa-favicon' );
			var mediaLink = document.querySelector( 'link[rel="icon"][media]' );

			// Override both links to a single URL, bypassing the media attribute.
			function applyOverride( isDark ) {
				if ( mainLink ) mainLink.href = isDark ? darkUrl : lightUrl;
				// 'not all' disables the media link; 'all' forces it on.
				if ( mediaLink ) mediaLink.media = isDark ? 'all' : 'not all';
			}

			// Restore pure media-query behaviour (no manual preference active).
			function clearOverride() {
				if ( mainLink ) mainLink.href = lightUrl;
				if ( mediaLink ) mediaLink.media = '(prefers-color-scheme: dark)';
			}

			// Apply any manual preference stored from a previous visit.
			var stored = '';
			try { stored = localStorage.getItem( 'aggressive-apparel-dark-mode' ) || ''; } catch ( e ) {}
			if ( stored ) applyOverride( stored === 'dark' );

			// Theme manual toggle (dispatched by dark-mode-toggle/view.ts).
			document.addEventListener( 'darkModeToggle', function ( e ) {
				applyOverride( e.detail.isDark );
			} );

			// When OS preference changes and there is no manual override, let the
			// media attribute take over again instead of keeping a stale href.
			window.matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', function () {
				try {
					if ( ! localStorage.getItem( 'aggressive-apparel-dark-mode' ) ) {
						clearOverride();
					}
				} catch ( err ) {}
			} );
		}() );
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the saved dark favicon attachment ID.
	 *
	 * @return int Attachment ID, or 0 if not set.
	 */
	private function get_saved_id(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}
}
