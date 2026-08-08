<?php
/**
 * Automatic product-badge rule settings and admin UI.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Badge_Rules_Admin {
	public const RULES_OPTION = 'aggressive_apparel_product_badge_rules';

	/**
	 * Get sanitized automatic-badge rules merged with defaults.
	 *
	 * @return array<string, bool|int>
	 */
	public static function get_badge_rules(): array {
		$defaults = array(
			'sale_enabled'       => true,
			'new_enabled'        => true,
			'low_stock_enabled'  => true,
			'bestseller_enabled' => true,
			'new_days'           => 14,
			'low_stock'          => 5,
			'bestseller_sales'   => 50,
		);
		$stored   = get_option( self::RULES_OPTION, array() );

		return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/** Render automatic-system-badge controls above the badge editor. */
	public function render_rules_panel(): void {
		$rules = self::get_badge_rules();
		// This flag is only presentation feedback after save_badge_rules() has
		// already verified the nonce and redirected back to this screen.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rules_updated = isset( $_GET['rules-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rules-updated'] ) );
		$toggle_label  = $rules_updated ? __( 'Close', 'aggressive-apparel' ) : __( 'Configure', 'aggressive-apparel' );
		$status_chips  = array(
			array(
				'label'   => __( 'Sale', 'aggressive-apparel' ),
				'enabled' => (bool) $rules['sale_enabled'],
			),
			array(
				'label'   => __( 'New', 'aggressive-apparel' ),
				'enabled' => (bool) $rules['new_enabled'],
			),
			array(
				'label'   => __( 'Low stock', 'aggressive-apparel' ),
				'enabled' => (bool) $rules['low_stock_enabled'],
			),
			array(
				'label'   => __( 'Bestseller', 'aggressive-apparel' ),
				'enabled' => (bool) $rules['bestseller_enabled'],
			),
		);
		$active_count  = count(
			array_filter(
				$status_chips,
				static fn( array $chip ): bool => $chip['enabled']
			)
		);
		?>
		<div class="aa-badge-rules">
			<div class="aa-badge-rules__heading">
				<div class="aa-badge-rules__intro">
					<div class="aa-badge-rules__title-row">
						<h2><?php esc_html_e( 'Automatic rules', 'aggressive-apparel' ); ?></h2>
						<span class="aa-badge-rules__count">
							<?php
							printf(
								/* translators: %d: number of enabled automatic badge rules. */
								esc_html( _n( '%d active', '%d active', $active_count, 'aggressive-apparel' ) ),
								(int) $active_count
							);
							?>
						</span>
					</div>
					<p><?php esc_html_e( 'System badges apply when product conditions are met. Style them in the list below.', 'aggressive-apparel' ); ?></p>
					<ul class="aa-badge-rules__status" aria-label="<?php esc_attr_e( 'Rule status', 'aggressive-apparel' ); ?>">
						<?php foreach ( $status_chips as $chip ) : ?>
							<li class="aa-badge-rules__chip aa-badge-rules__chip--<?php echo $chip['enabled'] ? 'on' : 'off'; ?>">
								<span class="aa-badge-rules__chip-dot" aria-hidden="true"></span>
								<?php echo esc_html( $chip['label'] ); ?>
								<span class="aa-badge-sr-only">
									<?php
									echo $chip['enabled']
										? esc_html__( 'enabled', 'aggressive-apparel' )
										: esc_html__( 'disabled', 'aggressive-apparel' );
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<button type="button" class="aa-badge-rules__toggle" data-badge-rules-toggle data-open-label="<?php esc_attr_e( 'Configure', 'aggressive-apparel' ); ?>" data-close-label="<?php esc_attr_e( 'Close', 'aggressive-apparel' ); ?>" aria-expanded="<?php echo $rules_updated ? 'true' : 'false'; ?>"><?php echo esc_html( $toggle_label ); ?></button>
			</div>
			<form class="aa-badge-rules__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-badge-rules-form <?php echo $rules_updated ? '' : 'hidden'; ?>>
				<input type="hidden" name="action" value="aggressive_apparel_save_badge_rules" />
				<?php wp_nonce_field( 'aggressive_apparel_badge_rules', 'aa_badge_rules_nonce' ); ?>
				<div class="aa-badge-rules__grid">
					<?php self::render_rule_toggle( 'sale_enabled', __( 'Sale', 'aggressive-apparel' ), (bool) $rules['sale_enabled'], self::get_store_copy_url(), __( 'Edit badge wording', 'aggressive-apparel' ) ); ?>
					<?php self::render_rule_number( 'new_days', 'new_enabled', __( 'New product', 'aggressive-apparel' ), (int) $rules['new_days'], 1, 365, __( 'days after publishing', 'aggressive-apparel' ), (bool) $rules['new_enabled'] ); ?>
					<?php self::render_rule_number( 'low_stock', 'low_stock_enabled', __( 'Low stock', 'aggressive-apparel' ), (int) $rules['low_stock'], 1, 1000, __( 'items remaining', 'aggressive-apparel' ), (bool) $rules['low_stock_enabled'] ); ?>
					<?php self::render_rule_number( 'bestseller_sales', 'bestseller_enabled', __( 'Bestseller', 'aggressive-apparel' ), (int) $rules['bestseller_sales'], 1, 1000000, __( 'lifetime sales', 'aggressive-apparel' ), (bool) $rules['bestseller_enabled'] ); ?>
				</div>
				<div class="aa-badge-rules__actions">
					<?php
					submit_button(
						__( 'Save Rules', 'aggressive-apparel' ),
						'primary aa-badge-rules__save',
						'aa_badge_rules_submit',
						false
					);
					?>
				</div>
			</form>
		</div>
		<?php
	}

	/** Save automatic badge rule settings. */
	public function save_badge_rules(): void {
		check_admin_referer( 'aggressive_apparel_badge_rules', 'aa_badge_rules_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage badge rules.', 'aggressive-apparel' ) );
		}

		$rules = array(
			'sale_enabled'       => isset( $_POST['sale_enabled'] ),
			'new_enabled'        => isset( $_POST['new_enabled'] ),
			'low_stock_enabled'  => isset( $_POST['low_stock_enabled'] ),
			'bestseller_enabled' => isset( $_POST['bestseller_enabled'] ),
			'new_days'           => self::posted_rule_number( 'new_days', 14, 1, 365 ),
			'low_stock'          => self::posted_rule_number( 'low_stock', 5, 1, 1000 ),
			'bestseller_sales'   => self::posted_rule_number( 'bestseller_sales', 50, 1, 1000000 ),
		);
		update_option( self::RULES_OPTION, $rules );

		$url = add_query_arg(
			array(
				'taxonomy'      => self::TAXONOMY,
				'post_type'     => 'product',
				'rules-updated' => '1',
			),
			admin_url( 'edit-tags.php' ),
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render a system-rule enable toggle.
	 *
	 * @param string $name       Checkbox name.
	 * @param string $label      Visible label.
	 * @param bool   $enabled    Whether the rule is enabled.
	 * @param string $link_url   Optional related-settings URL.
	 * @param string $link_label Optional link text; required alongside a URL.
	 */
	private static function render_rule_toggle( string $name, string $label, bool $enabled, string $link_url = '', string $link_label = '' ): void {
		echo '<div class="aa-badge-rule">';

		self::render_rule_checkbox( $name, $label, $enabled );

		if ( '' !== $link_url && '' !== $link_label ) {
			printf(
				'<a class="aa-badge-rule__link" href="%1$s">%2$s</a>',
				esc_url( $link_url ),
				esc_html( $link_label ),
			);
		}

		echo '</div>';
	}

	/**
	 * Render an enable toggle with a numeric threshold.
	 *
	 * @param string $name         Number input name.
	 * @param string $enabled_name Checkbox name.
	 * @param string $label        Visible label.
	 * @param int    $value        Current threshold.
	 * @param int    $min          Minimum threshold.
	 * @param int    $max          Maximum threshold.
	 * @param string $suffix       Threshold unit label.
	 * @param bool   $enabled      Whether the rule is enabled.
	 */
	private static function render_rule_number( string $name, string $enabled_name, string $label, int $value, int $min, int $max, string $suffix, bool $enabled ): void {
		echo '<div class="aa-badge-rule">';

		self::render_rule_checkbox( $enabled_name, $label, $enabled );

		// The unit text labels the number input rather than the checkbox, so
		// the threshold has an accessible name of its own ("days after
		// publishing") instead of borrowing the rule's.
		printf(
			'<label class="aa-badge-rule__threshold"><input type="number" name="%1$s" value="%2$d" min="%3$d" max="%4$d" /> %5$s</label>',
			esc_attr( $name ),
			absint( $value ),
			absint( $min ),
			absint( $max ),
			esc_html( $suffix ),
		);

		echo '</div>';
	}

	/**
	 * Render the enable checkbox shared by every rule card.
	 *
	 * Each card is a `<div>` holding one `<label>` per control. Making the card
	 * itself the label would give every rule a different click target as soon as
	 * one of them gained a second control.
	 *
	 * @param string $name    Checkbox name.
	 * @param string $label   Visible label.
	 * @param bool   $enabled Whether the rule is enabled.
	 */
	private static function render_rule_checkbox( string $name, string $label, bool $enabled ): void {
		printf(
			'<label class="aa-badge-rule__toggle"><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $name ),
			checked( $enabled, true, false ),
			esc_html( $label ),
		);
	}

	/**
	 * Deep link to the Store Copy tab that words the automatic badges.
	 *
	 * @return string
	 */
	private static function get_store_copy_url(): string {
		return add_query_arg(
			array(
				'page' => Feature_Settings::PAGE_SLUG,
				'tab'  => 'copy',
			),
			admin_url( 'themes.php' ),
		);
	}

	/**
	 * Read and clamp a numeric rule posted after nonce verification.
	 *
	 * @param string $key      POST field key.
	 * @param int    $fallback Missing-value fallback.
	 * @param int    $min      Minimum accepted value.
	 * @param int    $max      Maximum accepted value.
	 * @return int
	 */
	private static function posted_rule_number( string $key, int $fallback, int $min, int $max ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- save_badge_rules() verifies before calling this helper.
		$value = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : $fallback;
		return max( $min, min( $value, $max ) );
	}
}
