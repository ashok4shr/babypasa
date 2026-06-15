<?php
/**
 * WP_List_Table subclass for the newsletter subscribers screen.
 */

namespace BabypasaNewsletter\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class List_Table extends \WP_List_Table {

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
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'              => '<input type="checkbox">',
			'email'           => __( 'Email', 'babypasa-newsletter' ),
			'status'          => __( 'Status', 'babypasa-newsletter' ),
			'subscribed_at'   => __( 'Subscribed Date', 'babypasa-newsletter' ),
			'unsubscribed_at' => __( 'Unsubscribed Date', 'babypasa-newsletter' ),
		);
	}

	/**
	 * @return array<string,array{string,bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'email'         => array( 'email', false ),
			'status'        => array( 'status', false ),
			'subscribed_at' => array( 'subscribed_at', true ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'delete' => __( 'Delete', 'babypasa-newsletter' ),
		);
	}

	/**
	 * @param object $item
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="subscriber_ids[]" value="%d">',
			(int) $item->id
		);
	}

	protected function column_email( object $item ): string {
		return esc_html( $item->email );
	}

	protected function column_status( object $item ): string {
		$label = esc_html( $item->status );
		$class = ( 'active' === $item->status ) ? 'bpnl-badge-active' : 'bpnl-badge-unsubscribed';
		return '<span class="bpnl-badge ' . esc_attr( $class ) . '">' . $label . '</span>';
	}

	protected function column_subscribed_at( object $item ): string {
		return esc_html( $item->subscribed_at );
	}

	protected function column_unsubscribed_at( object $item ): string {
		return ! empty( $item->unsubscribed_at ) ? esc_html( $item->unsubscribed_at ) : '&mdash;';
	}

	/**
	 * @param object $item
	 * @param string $column_name
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}

	public function prepare_items(): void {
		$search  = isset( $_REQUEST['s'] )       ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) )       : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] )   ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) )   : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$query_args = array(
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => $offset,
		);

		$total_items  = \BabypasaNewsletter\Includes\Subscriber::count( array( 'search' => $search ) );
		$this->items  = \BabypasaNewsletter\Includes\Subscriber::get_all( $query_args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}
}
