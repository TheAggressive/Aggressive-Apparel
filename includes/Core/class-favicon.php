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
	 * Tab icon sizes, matching the ones wp_site_icon() prints.
	 *
	 * @var array<int, int>
	 */
	private const SIZES = array( 32, 192 );

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Dark icons print before wp_site_icon() (priority 99); the toggle
		// script needs the light icons it prints, so it runs after.
		add_action( 'wp_head', array( $this, 'output_head_tags' ), 1 );
		add_action( 'wp_head', array( $this, 'output_override_script' ), 100 );
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
	 * Output the dark icons, in the sizes WordPress uses for the Site Icon.
	 *
	 * WordPress prints the light icons through wp_site_icon() (32px and 192px
	 * tab icons, the apple-touch-icon and the tile image) at wp_head priority
	 * 99, and those are left untouched. The dark pair prints here first, with
	 * media="(prefers-color-scheme: dark)", so browsers that honour media on
	 * icons follow the OS, and Safari (which ignores it and takes the last
	 * icon) gets the light one.
	 *
	 * The tags are static because Chromium's favicon pre-scanner reads them
	 * before JavaScript runs. With no Site Icon set, the dark icon stands in
	 * for every mode.
	 *
	 * @return void
	 */
	public function output_head_tags(): void {
		$dark_id = $this->get_saved_id();
		if ( ! $dark_id ) {
			return;
		}

		$follows_os = has_site_icon();

		foreach ( self::SIZES as $size ) {
			$url = wp_get_attachment_image_url( $dark_id, array( $size, $size ) );
			if ( ! $url ) {
				continue;
			}

			printf(
				"<link rel=\"icon\" href=\"%s\" sizes=\"%dx%d\"%s data-aa-dark-icon>\n",
				esc_url( $url ),
				(int) $size,
				(int) $size,
				$follows_os ? ' media="(prefers-color-scheme: dark)"' : ''
			);
		}
	}

	/**
	 * Follow the theme's manual dark-mode toggle.
	 *
	 * Runs after wp_site_icon() so the light icons exist. OS preference stays
	 * on the media attribute; a stored manual choice overrides it by enabling
	 * or disabling the dark icons and pointing the light icons at their dark
	 * counterpart of the same size (for Safari, which only reads the last one).
	 *
	 * @return void
	 */
	public function output_override_script(): void {
		if ( ! $this->get_saved_id() || ! has_site_icon() ) {
			return;
		}

		$read_fn = Color_Scheme::js_read_stored_scheme_function();

		wp_print_inline_script_tag(
			<<<JS
(function(){
	var dark=[].slice.call(document.querySelectorAll('link[rel="icon"][data-aa-dark-icon]'));
	var light=[].slice.call(document.querySelectorAll('link[rel="icon"]:not([data-aa-dark-icon])'));
	var lightHref=light.map(function(l){return l.href;});
	var darkHref={};
	dark.forEach(function(l){darkHref[l.getAttribute('sizes')]=l.href;});

	{$read_fn}

	function applyOverride(isDark){
		dark.forEach(function(l){l.media=isDark?'all':'not all';});
		light.forEach(function(l,i){l.href=(isDark&&darkHref[l.getAttribute('sizes')])||lightHref[i];});
	}

	function clearOverride(){
		dark.forEach(function(l){l.media='(prefers-color-scheme: dark)';});
		light.forEach(function(l,i){l.href=lightHref[i];});
	}

	// Prefer data-theme from Color_Scheme_Bootstrap; storage only gates
	// whether a manual override is active.
	if(aaReadStoredColorScheme()){
		applyOverride(document.documentElement.getAttribute('data-theme')==='dark');
	}

	document.addEventListener('darkModeToggle',function(e){
		applyOverride(e.detail.isDark);
	});

	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',function(){
		if(!aaReadStoredColorScheme())clearOverride();
	});
})();
JS
			,
			array(
				'id' => 'aggressive-apparel-favicon-color-scheme',
			)
		);
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
