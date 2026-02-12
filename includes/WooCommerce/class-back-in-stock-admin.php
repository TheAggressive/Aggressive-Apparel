<?php
/**
 * Back in Stock Admin Class
 *
 * Adds a "Stock Subscribers" page under the WooCommerce admin menu
 * using WP_List_Table for viewing and managing subscriptions.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back in Stock Admin
 *
 * @since 1.18.0
 */
class Back_In_Stock_Admin {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_actions' ) );
	}

	/**
	 * Add submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Stock Subscribers', 'aggressive-apparel' ),
			__( 'Stock Subscribers', 'aggressive-apparel' ),
			'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			'aa-stock-subscribers',
			array( $this, 'render_admin_page' ),
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		// Load the list table class inline (keeps everything in one file).
		$table = $this->create_list_table();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stock Notification Subscribers', 'aggressive-apparel' ); ?></h1>

			<?php
			// Show unsubscribed notice after deletion.
			if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$count = absint( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: number of items deleted. */
							_n( '%d subscriber deleted.', '%d subscribers deleted.', $count, 'aggressive-apparel' ),
							$count
						)
					)
				);
			}
			?>

			<form method="get">
				<input type="hidden" name="page" value="aa-stock-subscribers" />
				<?php
				$table->search_box( __( 'Search', 'aggressive-apparel' ), 'search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle bulk delete actions.
	 *
	 * @return void
	 */
	public function handle_bulk_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'aa-stock-subscribers' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'delete' !== $_GET['action'] ) {
			return;
		}

		if ( ! isset( $_GET['subscriber'] ) || ! is_array( $_GET['subscriber'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-subscribers' );

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();
		$ids   = array_map( 'absint', $_GET['subscriber'] );

		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				...$ids
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'aa-stock-subscribers',
					'deleted' => count( $ids ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create the list table instance.
	 *
	 * @return \WP_List_Table
	 */
	private function create_list_table(): \WP_List_Table {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		return new class() extends \WP_List_Table {

			/**
			 * Constructor.
			 */
			public function __construct() {
				parent::__construct(
					array(
						'singular' => 'subscriber',
						'plural'   => 'subscribers',
						'ajax'     => false,
					)
				);
			}

			/**
			 * Get columns.
			 *
			 * @return array
			 */
			public function get_columns(): array {
				return array(
					'cb'          => '<input type="checkbox" />',
					'email'       => __( 'Email', 'aggressive-apparel' ),
					'product'     => __( 'Product', 'aggressive-apparel' ),
					'status'      => __( 'Status', 'aggressive-apparel' ),
					'created_at'  => __( 'Subscribed', 'aggressive-apparel' ),
					'notified_at' => __( 'Notified', 'aggressive-apparel' ),
				);
			}

			/**
			 * Get sortable columns.
			 *
			 * @return array
			 */
			public function get_sortable_columns(): array {
				return array(
					'email'      => array( 'email', false ),
					'status'     => array( 'status', false ),
					'created_at' => array( 'created_at', true ),
				);
			}

			/**
			 * Get bulk actions.
			 *
			 * @return array
			 */
			public function get_bulk_actions(): array {
				return array(
					'delete' => __( 'Delete', 'aggressive-apparel' ),
				);
			}

			/**
			 * Prepare items for display.
			 *
			 * @return void
			 */
			public function prepare_items(): void {
				global $wpdb;
				$table = Back_In_Stock_Installer::get_table_name();

				$per_page     = 20;
				$current_page = $this->get_pagenum();
				$offset       = ( $current_page - 1 ) * $per_page;

				$where = '1=1';
				$args  = array();

				// Status filter.
				$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( in_array( $status, array( 'active', 'notified', 'unsubscribed' ), true ) ) {
					$where .= ' AND status = %s';
					$args[] = $status;
				}

				// Search filter.
				$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $search ) {
					$where .= ' AND email LIKE %s';
					$args[] = '%' . $wpdb->esc_like( $search ) . '%';
				}

				// Ordering.
				$orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_GET['orderby'] ) . ' ASC' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$orderby = $orderby ? explode( ' ', $orderby )[0] : 'created_at';
				$allowed = array( 'email', 'status', 'created_at' );
				$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
				$order   = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				// Total items.
				if ( $args ) {
					$total_items = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$table} WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders in dynamic $where.
							...$args
						)
					);
				} else {
					$total_items = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
						"SELECT COUNT(*) FROM {$table} WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					);
				}

				// Fetch rows.
				$query_args  = array_merge( $args, array( $per_page, $offset ) );
				$query       = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->items = $wpdb->get_results( $wpdb->prepare( $query, ...$query_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.

				$this->set_pagination_args(
					array(
						'total_items' => $total_items,
						'per_page'    => $per_page,
						'total_pages' => (int) ceil( $total_items / $per_page ),
					)
				);

				$this->_column_headers = array(
					$this->get_columns(),
					array(),
					$this->get_sortable_columns(),
				);
			}

			/**
			 * Render checkbox column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_cb( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				return sprintf( '<input type="checkbox" name="subscriber[]" value="%d" />', absint( $item->id ) );
			}

			/**
			 * Render email column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_email( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				return esc_html( $item->email );
			}

			/**
			 * Render product column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_product( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				if ( ! function_exists( 'wc_get_product' ) ) {
					return '#' . absint( $item->product_id );
				}
				$product = wc_get_product( $item->product_id );
				if ( ! $product ) {
					return '#' . absint( $item->product_id );
				}
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $item->product_id ) ?? '' ),
					esc_html( $product->get_name() )
				);
			}

			/**
			 * Render status column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_status( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				$labels = array(
					'active'       => __( 'Active', 'aggressive-apparel' ),
					'notified'     => __( 'Notified', 'aggressive-apparel' ),
					'unsubscribed' => __( 'Unsubscribed', 'aggressive-apparel' ),
				);
				$label  = $labels[ $item->status ] ?? $item->status;
				return sprintf( '<mark class="aa-bis-status aa-bis-status--%s">%s</mark>', esc_attr( $item->status ), esc_html( $label ) );
			}

			/**
			 * Render created_at column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_created_at( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				return esc_html( $item->created_at );
			}

			/**
			 * Render notified_at column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_notified_at( $item ): string {
				// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
				/** @var \stdClass $item */
				return $item->notified_at ? esc_html( $item->notified_at ) : '—';
			}

			/**
			 * Extra navigation for status filter.
			 *
			 * @param string $which Top or bottom.
			 * @return void
			 */
			protected function extra_tablenav( $which ): void {
				if ( 'top' !== $which ) {
					return;
				}
				$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<div class="alignleft actions">
					<select name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'aggressive-apparel' ); ?></option>
						<option value="active" <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active', 'aggressive-apparel' ); ?></option>
						<option value="notified" <?php selected( $current, 'notified' ); ?>><?php esc_html_e( 'Notified', 'aggressive-apparel' ); ?></option>
						<option value="unsubscribed" <?php selected( $current, 'unsubscribed' ); ?>><?php esc_html_e( 'Unsubscribed', 'aggressive-apparel' ); ?></option>
					</select>
					<?php submit_button( __( 'Filter', 'aggressive-apparel' ), '', 'filter_action', false ); ?>
				</div>
				<?php
			}

			/**
			 * Message when no items are found.
			 *
			 * @return void
			 */
			public function no_items(): void {
				esc_html_e( 'No subscribers found.', 'aggressive-apparel' );
			}
		};
	}
}
