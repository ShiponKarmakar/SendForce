<?php
/**
 * Email log list table.
 *
 * @package SendForce
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class SendForce_Log_List
 *
 * Extends WP_List_Table to display email logs.
 */
class SendForce_Log_List extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'to_email'   => __( 'To', 'sendforce-mail-relay' ),
			'subject'    => __( 'Subject', 'sendforce-mail-relay' ),
			'status'     => __( 'Status', 'sendforce-mail-relay' ),
			'created_at' => __( 'Date', 'sendforce-mail-relay' ),
			'actions'    => __( 'Actions', 'sendforce-mail-relay' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'to_email'   => array( 'to_email', false ),
			'subject'    => array( 'subject', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Set primary column — prevents WP responsive mode from hiding other columns.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'to_email';
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$per_page = 20;
		$page     = $this->get_pagenum();

		$args = array(
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'    => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'   => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$result = SendForce_Logger::get_logs( $args );

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', absint( $item->id ) );
	}

	/**
	 * To email column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_to_email( $item ) {
		return esc_html( $item->to_email );
	}

	/**
	 * Subject column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_subject( $item ) {
		$subject = $item->subject;
		if ( strlen( $subject ) > 60 ) {
			$subject = substr( $subject, 0, 60 ) . '...';
		}
		$out = esc_html( $subject );

		// Append a small badge when this send was redirected by a routing rule.
		if ( ! empty( $item->debug_data ) ) {
			$debug = json_decode( $item->debug_data, true );
			if ( is_array( $debug ) && ! empty( $debug['matched_rule'] ) ) {
				$out .= '<br><span class="sendforce-route-tag" title="' . esc_attr__( 'This email was routed by a Smart Routing rule', 'sendforce-mail-relay' ) . '">'
					. '<span class="dashicons dashicons-randomize" aria-hidden="true"></span> '
					. esc_html( sprintf(
						/* translators: %s: routing rule label */
						__( 'via rule: %s', 'sendforce-mail-relay' ),
						$debug['matched_rule']
					) )
					. '</span>';
			}
		}
		return $out;
	}

	/**
	 * Status column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_status( $item ) {
		$class = 'sent' === $item->status ? 'sendforce-status-sent' : 'sendforce-status-failed';
		return sprintf(
			'<span class="sendforce-status %s">%s</span>',
			esc_attr( $class ),
			esc_html( ucfirst( $item->status ) )
		);
	}

	/**
	 * Date column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $item->created_at )
			)
		);
	}

	/**
	 * Actions column.
	 *
	 * @param object $item The current item.
	 * @return string
	 */
	public function column_actions( $item ) {
		return sprintf(
			'<button type="button" class="button button-small sendforce-view-log" data-log-id="%d">%s</button>',
			absint( $item->id ),
			esc_html__( 'View', 'sendforce-mail-relay' )
		);
	}

	/**
	 * Default column handler.
	 *
	 * @param object $item        The current item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'sendforce-mail-relay' ),
		);
	}

	/**
	 * Extra table navigation (status filter).
	 *
	 * @param string $which Top or bottom.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'sendforce-mail-relay' ); ?></option>
				<option value="sent" <?php selected( $current_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'sendforce-mail-relay' ); ?></option>
				<option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'sendforce-mail-relay' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'sendforce-mail-relay' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Message for no items.
	 */
	public function no_items() {
		esc_html_e( 'No email logs found.', 'sendforce-mail-relay' );
	}
}
