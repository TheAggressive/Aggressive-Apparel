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
	/** Per-user transient prefix for completed bulk actions. */
	private const NOTICE_TRANSIENT_PREFIX = 'aa_bis_admin_notice_';

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
			'manage_woocommerce',
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage stock subscribers.', 'aggressive-apparel' ) );
		}

		// Load the list table class inline (keeps everything in one file).
		$table = $this->create_list_table();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stock Notification Subscribers', 'aggressive-apparel' ); ?></h1>

			<?php
			// Show the server-side completion notice once, without trusting URL state.
			$count = (int) get_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );
			if ( 0 < $count ) {
				delete_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );
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
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'aa-stock-subscribers' !== $page ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'delete' !== $action ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage stock subscribers.', 'aggressive-apparel' ) );
		}

		check_admin_referer( 'bulk-subscribers' );

		if ( ! isset( $_GET['subscriber'] ) || ! is_array( $_GET['subscriber'] ) ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();
		$ids   = array_slice(
			array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_GET['subscriber'] ) ) ) ) ),
			0,
			100
		);

		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorized custom-table mutation; no read cache may remain stale.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder count is bounded by the 100 normalized integer IDs above.
				...array_merge( array( $table ), $ids )
			)
		);
		set_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(), count( $ids ), MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'aa-stock-subscribers',
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

				// Status filter.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filtering; no state changes occur.
				$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
				$status = in_array( $status, array( 'active', 'notified', 'unsubscribed' ), true ) ? $status : '';

				// Search filter.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filtering; no state changes occur.
				$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				$search = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

				// Ordering.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allow-listed ordering; no state changes occur.
				$orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_GET['orderby'] ) . ' ASC' ) : '';
				$orderby = $orderby ? explode( ' ', $orderby )[0] : 'created_at';
				$allowed = array( 'email', 'status', 'created_at' );
				$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allow-listed ordering; no state changes occur.
				$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';

				if ( '' !== $status && '' !== $search ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Operational admin screen must show current custom-table state.
					$total_items = (int) $wpdb->get_var(
						$wpdb->prepare(
							'SELECT COUNT(*) FROM %i WHERE status = %s AND email LIKE %s',
							$table,
							$status,
							$search
						)
					);
				} elseif ( '' !== $status ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Operational admin screen must show current custom-table state.
					$total_items = (int) $wpdb->get_var(
						$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status )
					);
				} elseif ( '' !== $search ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Operational admin screen must show current custom-table state.
					$total_items = (int) $wpdb->get_var(
						$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE email LIKE %s', $table, $search )
					);
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Operational admin screen must show current custom-table state.
					$total_items = (int) $wpdb->get_var(
						$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
					);
				}

				if ( '' !== $status && '' !== $search ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded, paginated operational admin read.
					$this->items = 'ASC' === $order
						? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s AND email LIKE %s ORDER BY %i ASC LIMIT %d OFFSET %d', $table, $status, $search, $orderby, $per_page, $offset ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded operational read.
						: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s AND email LIKE %s ORDER BY %i DESC LIMIT %d OFFSET %d', $table, $status, $search, $orderby, $per_page, $offset ) );
				} elseif ( '' !== $status ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded, paginated operational admin read.
					$this->items = 'ASC' === $order
						? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY %i ASC LIMIT %d OFFSET %d', $table, $status, $orderby, $per_page, $offset ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded operational read.
						: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY %i DESC LIMIT %d OFFSET %d', $table, $status, $orderby, $per_page, $offset ) );
				} elseif ( '' !== $search ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded, paginated operational admin read.
					$this->items = 'ASC' === $order
						? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email LIKE %s ORDER BY %i ASC LIMIT %d OFFSET %d', $table, $search, $orderby, $per_page, $offset ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded operational read.
						: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email LIKE %s ORDER BY %i DESC LIMIT %d OFFSET %d', $table, $search, $orderby, $per_page, $offset ) );
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded, paginated operational admin read.
					$this->items = 'ASC' === $order
						? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i ASC LIMIT %d OFFSET %d', $table, $orderby, $per_page, $offset ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded operational read.
						: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i DESC LIMIT %d OFFSET %d', $table, $orderby, $per_page, $offset ) );
				}

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
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
				return sprintf( '<input type="checkbox" name="subscriber[]" value="%d" />', absint( $item->id ) );
			}

			/**
			 * Render email column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_email( $item ): string {
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
				return esc_html( $item->email );
			}

			/**
			 * Render product column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_product( $item ): string {
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
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
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
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
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
				return esc_html( $item->created_at );
			}

			/**
			 * Render notified_at column.
			 *
			 * @param object $item Row data.
			 * @return string
			 */
			public function column_notified_at( $item ): string {
				/**
				 * Subscriber row.
				 *
				 * @var \stdClass $item
				 */
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
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state used to render the selected option.
				$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
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
