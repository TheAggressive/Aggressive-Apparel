<?php
/**
 * Site-wide legal identity settings (Settings → Terms).
 *
 * Provides a dedicated Terms of Service settings screen styled like core
 * Settings → Privacy, plus a legal / organization name for copyright.
 *
 * @package Aggressive_Apparel
 * @since 1.3.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

/**
 * Legal identity options for copyright and related legal surfaces.
 */
class Legal_Identity {

	/**
	 * Option key for the legal / organization name.
	 */
	public const LEGAL_NAME_OPTION = 'aggressive_apparel_legal_name';

	/**
	 * Option key for the Terms of Service page ID.
	 */
	public const TERMS_PAGE_OPTION = 'aggressive_apparel_terms_page_id';

	/**
	 * Core option for the Privacy Policy page (Settings → Privacy).
	 */
	public const PRIVACY_PAGE_OPTION = 'wp_page_for_privacy_policy';

	/**
	 * Settings page slug under Settings.
	 */
	public const PAGE_SLUG = 'aggressive-apparel-terms';

	/**
	 * Option group for register_setting (REST schema; forms are custom).
	 */
	public const OPTION_GROUP = 'aggressive_apparel_terms';

	/**
	 * Hook settings registration and admin menu.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add Settings → Terms (mirrors Settings → Privacy).
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook = add_options_page(
			__( 'Terms', 'aggressive-apparel' ),
			__( 'Terms', 'aggressive-apparel' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( is_string( $hook ) ) {
			add_action( "load-{$hook}", array( $this, 'load_page' ) );
		}
	}

	/**
	 * Register options for sanitization + REST (screen uses custom forms).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::LEGAL_NAME_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
					),
				),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::TERMS_PAGE_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'integer',
					),
				),
			)
		);
	}

	/**
	 * Prepare the Terms screen (body class, help, form actions).
	 *
	 * @return void
	 */
	public function load_page(): void {
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		$screen = get_current_screen();
		if ( $screen ) {
			$screen->add_help_tab(
				array(
					'id'      => 'overview',
					'title'   => __( 'Overview', 'aggressive-apparel' ),
					'content' =>
						'<p>' . esc_html__(
							'The Terms screen lets you create a new Terms of Service page or choose one you already have.',
							'aggressive-apparel'
						) . '</p>' .
						'<p>' . esc_html__(
							'Once set, the Copyright block can link to it in the footer alongside your Privacy Policy.',
							'aggressive-apparel'
						) . '</p>',
				)
			);

			$screen->set_help_sidebar(
				'<p><strong>' . esc_html__( 'For more information:', 'aggressive-apparel' ) . '</strong></p>' .
				'<p><a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' .
				esc_html__( 'Privacy Settings', 'aggressive-apparel' ) .
				'</a></p>'
			);
		}

		$this->handle_actions();
	}

	/**
	 * Add Privacy-style body class so core admin CSS applies.
	 *
	 * @param string $body_class Existing body classes.
	 * @return string
	 */
	public function admin_body_class( string $body_class ): string {
		return $body_class . ' privacy-settings ';
	}

	/**
	 * Handle create / select / legal-name form posts.
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( $action );

		if ( 'set-terms-page' === $action ) {
			$terms_page_id = isset( $_POST['page_for_terms'] ) ? absint( $_POST['page_for_terms'] ) : 0;
			update_option( self::TERMS_PAGE_OPTION, $terms_page_id );

			$message = __( 'Terms of Service page updated successfully.', 'aggressive-apparel' );

			if (
				$terms_page_id
				&& 'publish' === get_post_status( $terms_page_id )
				&& current_user_can( 'edit_theme_options' )
				&& current_theme_supports( 'menus' )
			) {
				$message = sprintf(
					/* translators: %s: URL to Customizer → Menus. */
					__( 'Terms of Service page setting updated successfully. Remember to <a href="%s">update your menus</a>!', 'aggressive-apparel' ),
					esc_url( add_query_arg( 'autofocus[panel]', 'nav_menus', admin_url( 'customize.php' ) ) )
				);
			}

			add_settings_error( self::TERMS_PAGE_OPTION, 'terms_page_updated', $message, 'success' );
			return;
		}

		if ( 'create-terms-page' === $action ) {
			$terms_page_id = wp_insert_post(
				array(
					'post_title'   => __( 'Terms of Service', 'aggressive-apparel' ),
					'post_status'  => 'draft',
					'post_type'    => 'page',
					'post_content' => self::get_default_terms_content(),
				),
				true
			);

			if ( is_wp_error( $terms_page_id ) ) {
				add_settings_error(
					self::TERMS_PAGE_OPTION,
					'terms_page_create_failed',
					__( 'Unable to create a Terms of Service page.', 'aggressive-apparel' ),
					'error'
				);
				return;
			}

			update_option( self::TERMS_PAGE_OPTION, (int) $terms_page_id );
			wp_safe_redirect( admin_url( 'post.php?post=' . (int) $terms_page_id . '&action=edit' ) );
			exit;
		}

		if ( 'set-legal-name' === $action ) {
			$legal_name = isset( $_POST[ self::LEGAL_NAME_OPTION ] )
				? sanitize_text_field( wp_unslash( $_POST[ self::LEGAL_NAME_OPTION ] ) )
				: '';
			update_option( self::LEGAL_NAME_OPTION, $legal_name );

			add_settings_error(
				self::LEGAL_NAME_OPTION,
				'legal_name_updated',
				__( 'Legal / organization name updated successfully.', 'aggressive-apparel' ),
				'success'
			);
		}
	}

	/**
	 * Starter content for a newly created Terms page.
	 *
	 * @return string
	 */
	private static function get_default_terms_content(): string {
		$intro = __(
			'This is a starter Terms of Service page. Replace this text with your store’s terms covering purchases, returns, shipping, and acceptable use.',
			'aggressive-apparel'
		);

		return '<!-- wp:paragraph -->
<p>' . esc_html( $intro ) . '</p>
<!-- /wp:paragraph -->';
	}

	/**
	 * Whether a page ID is usable for a public legal link.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	public static function is_usable_page( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}

		$post = get_post( $page_id );
		return $post instanceof \WP_Post && 'trash' !== $post->post_status;
	}

	/**
	 * Permalink for a legal page, or empty when unset / trashed.
	 *
	 * Unlike core get_privacy_policy_url(), draft pages still resolve so the
	 * Copyright block can show the link while the page is being written.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function get_page_url( int $page_id ): string {
		if ( ! self::is_usable_page( $page_id ) ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Render the Settings → Terms screen (Privacy-style layout).
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aggressive-apparel' ) );
		}

		$terms_page_exists = false;
		$terms_page_id     = self::get_terms_page_id();

		if ( $terms_page_id > 0 ) {
			$terms_page = get_post( $terms_page_id );

			if ( ! $terms_page instanceof \WP_Post ) {
				add_settings_error(
					self::TERMS_PAGE_OPTION,
					'terms_page_missing',
					__( 'The currently selected Terms of Service page does not exist. Please create or select a new page.', 'aggressive-apparel' ),
					'error'
				);
			} elseif ( 'trash' === $terms_page->post_status ) {
				add_settings_error(
					self::TERMS_PAGE_OPTION,
					'terms_page_trashed',
					sprintf(
						/* translators: %s: URL to Pages Trash. */
						__( 'The currently selected Terms of Service page is in the Trash. Please create or select a new page or <a href="%s">restore the current page</a>.', 'aggressive-apparel' ),
						esc_url( admin_url( 'edit.php?post_status=trash&post_type=page' ) )
					),
					'error'
				);
			} else {
				$terms_page_exists = true;
			}
		}

		$privacy_page_id = self::get_privacy_page_id();
		if ( $privacy_page_id > 0 ) {
			$privacy_status = get_post_status( $privacy_page_id );
			if ( 'draft' === $privacy_status || 'pending' === $privacy_status || 'future' === $privacy_status ) {
				$edit_privacy = add_query_arg(
					array(
						'post'   => $privacy_page_id,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				);
				add_settings_error(
					self::PRIVACY_PAGE_OPTION,
					'privacy_page_unpublished',
					sprintf(
						/* translators: %s: URL to edit the Privacy Policy page. */
						__( 'Your Privacy Policy page is not published yet. <a href="%s">Publish it</a> so visitors can open the Privacy link in the footer.', 'aggressive-apparel' ),
						esc_url( $edit_privacy )
					),
					'warning'
				);
			} elseif ( ! self::is_usable_page( $privacy_page_id ) ) {
				add_settings_error(
					self::PRIVACY_PAGE_OPTION,
					'privacy_page_missing',
					sprintf(
						/* translators: %s: URL to Settings → Privacy. */
						__( 'No usable Privacy Policy page is set. Choose one under <a href="%s">Settings → Privacy</a>.', 'aggressive-apparel' ),
						esc_url( admin_url( 'options-privacy.php' ) )
					),
					'warning'
				);
			}
		} else {
			add_settings_error(
				self::PRIVACY_PAGE_OPTION,
				'privacy_page_unset',
				sprintf(
					/* translators: %s: URL to Settings → Privacy. */
					__( 'No Privacy Policy page is set. Choose one under <a href="%s">Settings → Privacy</a> for the Copyright block Privacy link.', 'aggressive-apparel' ),
					esc_url( admin_url( 'options-privacy.php' ) )
				),
				'warning'
			);
		}

		$has_pages = (bool) get_posts(
			array(
				'post_type'              => 'page',
				'posts_per_page'         => 1,
				'post_status'            => array( 'publish', 'draft' ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);

		settings_errors();
		?>
		<div class="privacy-settings-header">
			<div class="privacy-settings-title-section">
				<h1><?php echo esc_html__( 'Terms', 'aggressive-apparel' ); ?></h1>
			</div>

			<nav class="privacy-settings-tabs-wrapper hide-if-no-js" aria-label="<?php esc_attr_e( 'Secondary menu', 'aggressive-apparel' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ); ?>" class="privacy-settings-tab active" aria-current="true">
					<?php echo esc_html_x( 'Settings', 'Terms Settings', 'aggressive-apparel' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>" class="privacy-settings-tab">
					<?php echo esc_html__( 'Privacy', 'aggressive-apparel' ); ?>
				</a>
			</nav>
		</div>

		<hr class="wp-header-end">

		<?php
		wp_admin_notice(
			__( 'The Terms Settings require JavaScript.', 'aggressive-apparel' ),
			array(
				'type'               => 'error',
				'additional_classes' => array( 'hide-if-js' ),
			)
		);
		?>

		<div class="privacy-settings-body hide-if-no-js">
			<h2><?php echo esc_html__( 'Terms of Service Settings', 'aggressive-apparel' ); ?></h2>
			<p>
				<?php echo esc_html__( 'As a website owner, you may need to publish Terms of Service covering purchases, returns, and site use.', 'aggressive-apparel' ); ?>
				<?php echo esc_html__( 'If you already have a Terms page, please select it below. If not, please create one.', 'aggressive-apparel' ); ?>
			</p>
			<p>
				<?php echo esc_html__( 'After your Terms of Service page is set, you should edit it.', 'aggressive-apparel' ); ?>
				<?php
				printf(
					wp_kses(
						/* translators: %s: URL to Settings → Privacy. */
						__( 'Your <a href="%s">Privacy Policy</a> is managed separately under Settings → Privacy.', 'aggressive-apparel' ),
						array(
							'a' => array(
								'href' => true,
							),
						)
					),
					esc_url( admin_url( 'options-privacy.php' ) )
				);
				?>
			</p>
			<p>
				<?php
				if ( $terms_page_exists ) {
					$edit_href = add_query_arg(
						array(
							'post'   => $terms_page_id,
							'action' => 'edit',
						),
						admin_url( 'post.php' )
					);
					$view_href = get_permalink( $terms_page_id );
					?>
					<strong>
					<?php
					if ( 'publish' === get_post_status( $terms_page_id ) ) {
						printf(
							wp_kses(
								/* translators: 1: URL to edit Terms page, 2: URL to view Terms page. */
								__( '<a href="%1$s">Edit</a> or <a href="%2$s">view</a> your Terms of Service page content.', 'aggressive-apparel' ),
								array(
									'a' => array(
										'href' => true,
									),
								)
							),
							esc_url( $edit_href ),
							esc_url( (string) $view_href )
						);
					} else {
						printf(
							wp_kses(
								/* translators: 1: URL to edit Terms page, 2: URL to preview Terms page. */
								__( '<a href="%1$s">Edit</a> or <a href="%2$s">preview</a> your Terms of Service page content.', 'aggressive-apparel' ),
								array(
									'a' => array(
										'href' => true,
									),
								)
							),
							esc_url( $edit_href ),
							esc_url( (string) $view_href )
						);
					}
					?>
					</strong>
					<?php
				}
				?>
			</p>
			<hr>
			<table class="form-table tools-privacy-policy-page" role="presentation">
				<tr>
					<th scope="row">
						<label for="create-terms-page">
							<?php
							if ( $has_pages ) {
								echo esc_html__( 'Create a new Terms of Service page', 'aggressive-apparel' );
							} else {
								echo esc_html__( 'There are no pages.', 'aggressive-apparel' );
							}
							?>
						</label>
					</th>
					<td>
						<form method="post">
							<input type="hidden" name="action" value="create-terms-page" />
							<?php
							wp_nonce_field( 'create-terms-page' );
							submit_button(
								__( 'Create', 'aggressive-apparel' ),
								'secondary',
								'submit',
								false,
								array( 'id' => 'create-terms-page' )
							);
							?>
						</form>
					</td>
				</tr>
				<?php if ( $has_pages ) : ?>
				<tr>
					<th scope="row">
						<label for="page_for_terms">
							<?php
							if ( $terms_page_exists ) {
								echo esc_html__( 'Change your Terms of Service page', 'aggressive-apparel' );
							} else {
								echo esc_html__( 'Select a Terms of Service page', 'aggressive-apparel' );
							}
							?>
						</label>
					</th>
					<td>
						<form method="post">
							<input type="hidden" name="action" value="set-terms-page" />
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'page_for_terms',
									'id'                => 'page_for_terms',
									'show_option_none'  => esc_html__( '— Select —', 'aggressive-apparel' ),
									'option_none_value' => '0',
									'selected'          => absint( $terms_page_id ),
									'post_status'       => array( 'draft', 'publish' ),
								)
							);
							wp_nonce_field( 'set-terms-page' );
							submit_button(
								__( 'Use This Page', 'aggressive-apparel' ),
								'primary',
								'submit',
								false,
								array( 'id' => 'set-terms-page' )
							);
							?>
						</form>
					</td>
				</tr>
				<?php endif; ?>
			</table>

			<hr class="hr-separator">

			<h2><?php echo esc_html__( 'Legal identity', 'aggressive-apparel' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Optional legal / organization name used by the Copyright block when Owner source is set to Legal name. Leave blank to fall back to the Site Title.', 'aggressive-apparel' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( self::LEGAL_NAME_OPTION ); ?>">
							<?php echo esc_html__( 'Legal / organization name', 'aggressive-apparel' ); ?>
						</label>
					</th>
					<td>
						<form method="post">
							<input type="hidden" name="action" value="set-legal-name" />
							<?php wp_nonce_field( 'set-legal-name' ); ?>
							<input
								type="text"
								class="regular-text"
								id="<?php echo esc_attr( self::LEGAL_NAME_OPTION ); ?>"
								name="<?php echo esc_attr( self::LEGAL_NAME_OPTION ); ?>"
								value="<?php echo esc_attr( self::get_legal_name() ); ?>"
							/>
							<?php
							submit_button(
								__( 'Save', 'aggressive-apparel' ),
								'secondary',
								'submit',
								false
							);
							?>
						</form>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Get the configured legal / organization name.
	 *
	 * @return string
	 */
	public static function get_legal_name(): string {
		return sanitize_text_field( (string) get_option( self::LEGAL_NAME_OPTION, '' ) );
	}

	/**
	 * Get the Privacy Policy page ID.
	 *
	 * @return int
	 */
	public static function get_privacy_page_id(): int {
		return absint( get_option( self::PRIVACY_PAGE_OPTION, 0 ) );
	}

	/**
	 * Get the Privacy Policy URL, or empty when unset.
	 *
	 * Resolves draft pages too (core get_privacy_policy_url() only returns
	 * published pages, which hid the Copyright Privacy link during setup).
	 *
	 * @return string
	 */
	public static function get_privacy_url(): string {
		return self::get_page_url( self::get_privacy_page_id() );
	}

	/**
	 * Get the Terms of Service page ID.
	 *
	 * @return int
	 */
	public static function get_terms_page_id(): int {
		return absint( get_option( self::TERMS_PAGE_OPTION, 0 ) );
	}

	/**
	 * Get the Terms of Service URL, or empty when unset.
	 *
	 * @return string
	 */
	public static function get_terms_url(): string {
		return self::get_page_url( self::get_terms_page_id() );
	}
}
