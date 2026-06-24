<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin menu, list table, edit form, and settings page for BP Ads Manager.
 */
class BP_Ads_Admin {

	/** Registers admin hooks. */
	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_bp_save_ad',       array( $this, 'handle_save_ad' ) );
		add_action( 'admin_post_bp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_notices',         array( $this, 'show_notice' ) );
	}

	/**
	 * Registers the top-level menu and submenus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Ads Manager', 'bp-ads-manager' ),
			__( 'Ads Manager', 'bp-ads-manager' ),
			'manage_options',
			'bp-ads-manager',
			array( $this, 'render_list_page' ),
			'dashicons-megaphone',
			58
		);

		add_submenu_page(
			'bp-ads-manager',
			__( 'All Ads', 'bp-ads-manager' ),
			__( 'All Ads', 'bp-ads-manager' ),
			'manage_options',
			'bp-ads-manager',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'bp-ads-manager',
			__( 'Add New Ad', 'bp-ads-manager' ),
			__( 'Add New Ad', 'bp-ads-manager' ),
			'manage_options',
			'bp-ads-add-new',
			array( $this, 'render_edit_page' )
		);

		add_submenu_page(
			'bp-ads-manager',
			__( 'Settings', 'bp-ads-manager' ),
			__( 'Settings', 'bp-ads-manager' ),
			'manage_options',
			'bp-ads-settings',
			array( $this, 'render_settings_page' )
		);

		// Hidden page for editing an existing ad.
		add_submenu_page(
			null,
			__( 'Edit Ad', 'bp-ads-manager' ),
			__( 'Edit Ad', 'bp-ads-manager' ),
			'manage_options',
			'bp-ads-edit',
			array( $this, 'render_edit_page' )
		);
	}

	/**
	 * Enqueues admin CSS only on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$plugin_pages = array(
			'toplevel_page_bp-ads-manager',
			'ads-manager_page_bp-ads-add-new',
			'ads-manager_page_bp-ads-settings',
			'admin_page_bp-ads-edit',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'bp-ads-admin',
			BP_ADS_URL . 'admin/css/admin.css',
			array(),
			filemtime( BP_ADS_PATH . 'admin/css/admin.css' )
		);

		// Enqueue the media uploader if on the edit/add page.
		if ( in_array( $hook, array( 'ads-manager_page_bp-ads-add-new', 'admin_page_bp-ads-edit' ), true ) ) {
			wp_enqueue_media();
		}
	}

	// ────────────────────────────────────────────────────────────────────────────
	// Page renderers
	// ────────────────────────────────────────────────────────────────────────────

	/**
	 * Renders the All Ads list page.
	 * Handles bulk actions submitted via the list table form before rendering.
	 */
	public function render_list_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bp-ads-manager' ) );
		}

		// Load WP_List_Table if not already available.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new BP_Ads_List_Table();

		// Handle bulk actions submitted from the list form.
		$bulk_action = $table->current_action();
		if ( $bulk_action && in_array( $bulk_action, array( 'enable', 'disable', 'delete' ), true ) ) {
			check_admin_referer( 'bulk-ads' );

			$ids = isset( $_POST['ad_ids'] ) ? array_map( 'absint', (array) $_POST['ad_ids'] ) : array();

			if ( ! empty( $ids ) ) {
				$affected = BP_Ads_DB::bulk_action( $ids, $bulk_action );
				$message  = sprintf(
					/* translators: %1$d: number of ads, %2$s: action name */
					__( '%1$d ad(s) %2$s successfully.', 'bp-ads-manager' ),
					$affected,
					esc_html( $bulk_action . 'd' )
				);
				$this->set_notice( 'success', $message );
			}

			// Preserve the active tab on redirect after a bulk action.
			$redirect = admin_url( 'admin.php?page=bp-ads-manager' );
			$ad_type  = isset( $_POST['ad_type'] ) ? sanitize_key( wp_unslash( $_POST['ad_type'] ) ) : '';
			if ( in_array( $ad_type, array( 'banner', 'popup' ), true ) ) {
				$redirect = add_query_arg( 'ad_type', $ad_type, $redirect );
			}

			wp_safe_redirect( $redirect );
			exit;
		}

		$table->prepare_items();
		include BP_ADS_PATH . 'admin/views/ads-list.php';
	}

	/**
	 * Renders the Add New / Edit Ad form page.
	 */
	public function render_edit_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bp-ads-manager' ) );
		}

		$ad_id = absint( $_GET['id'] ?? 0 );
		$ad    = $ad_id ? BP_Ads_DB::get_by_id( $ad_id ) : null;

		include BP_ADS_PATH . 'admin/views/ad-edit.php';
	}

	/**
	 * Renders the Settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bp-ads-manager' ) );
		}

		$settings = get_option( 'bp_ads_settings', array( 'ads_enabled' => 1 ) );
		include BP_ADS_PATH . 'admin/views/settings.php';
	}

	// ────────────────────────────────────────────────────────────────────────────
	// Form handlers
	// ────────────────────────────────────────────────────────────────────────────

	/**
	 * Processes the Add/Edit ad form (admin_post_bp_save_ad).
	 */
	public function handle_save_ad() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bp-ads-manager' ) );
		}

		check_admin_referer( 'bp_save_ad' );

		$ad_id        = absint( $_POST['bp_ad_id'] ?? 0 );
		$type         = sanitize_key( $_POST['bp_ad_type'] ?? 'popup' );
		$content_mode = sanitize_key( $_POST['bp_ad_content_mode'] ?? 'html' );
		if ( ! in_array( $content_mode, array( 'html', 'image' ), true ) ) {
			$content_mode = 'html';
		}

		// Placement only applies to banners; popups ignore it. The raw checkbox
		// array is sanitised against the valid slug whitelist in BP_Ads_DB.
		$placement = ( 'banner' === $type && isset( $_POST['bp_ad_placement'] ) )
			? (array) wp_unslash( $_POST['bp_ad_placement'] )
			: array();

		$data = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['bp_ad_title'] ?? '' ) ),
			'type'         => $type,
			'content_mode' => $content_mode,
			'content'      => ( 'html' === $content_mode )
				? wp_kses( wp_unslash( $_POST['bp_ad_content'] ?? '' ), BP_Ads_DB::get_html_kses_allowed() )
				: '',
			'image_id'     => ( 'image' === $content_mode ) ? absint( $_POST['bp_ad_image_id'] ?? 0 ) : 0,
			'active'       => isset( $_POST['bp_ad_active'] ) ? 1 : 0,
			'device'       => sanitize_key( $_POST['bp_ad_device'] ?? 'all' ),
			'popup_delay'  => absint( $_POST['bp_ad_popup_delay'] ?? 0 ),
			'frequency'    => sanitize_key( $_POST['bp_ad_frequency'] ?? 'once' ),
			'link_url'     => esc_url_raw( wp_unslash( $_POST['bp_ad_link_url'] ?? '' ) ),
			'placement'    => $placement,
			'start_date'   => sanitize_text_field( wp_unslash( $_POST['bp_ad_start_date'] ?? '' ) ),
			'end_date'     => sanitize_text_field( wp_unslash( $_POST['bp_ad_end_date'] ?? '' ) ),
			'sort_order'   => absint( $_POST['bp_ad_sort_order'] ?? 0 ),
		);

		if ( $ad_id > 0 ) {
			$result = BP_Ads_DB::update( $ad_id, $data );
			if ( false === $result ) {
				$this->set_notice( 'error', __( 'Failed to update ad. Please try again.', 'bp-ads-manager' ) );
			} else {
				$this->set_notice( 'success', __( 'Ad updated successfully.', 'bp-ads-manager' ) );
			}
			// Stay on the edit page after saving.
			wp_safe_redirect( admin_url( 'admin.php?page=bp-ads-edit&id=' . $ad_id ) );
		} else {
			$result = BP_Ads_DB::insert( $data );
			if ( is_wp_error( $result ) ) {
				$this->set_notice( 'error', $result->get_error_message() );
				// On insert error stay on add-new page.
				wp_safe_redirect( admin_url( 'admin.php?page=bp-ads-add-new' ) );
			} else {
				$this->set_notice( 'success', __( 'Ad created successfully.', 'bp-ads-manager' ) );
				// After creating, land on the edit page for the new ad.
				wp_safe_redirect( admin_url( 'admin.php?page=bp-ads-edit&id=' . $result ) );
			}
		}

		exit;
	}

	/**
	 * Processes the Settings form (admin_post_bp_save_settings).
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bp-ads-manager' ) );
		}

		check_admin_referer( 'bp_save_settings' );

		$allowed_scopes = array( 'global', 'frontpage', 'disabled' );
		$scope          = sanitize_key( $_POST['bp_ads_scope'] ?? 'global' );
		if ( ! in_array( $scope, $allowed_scopes, true ) ) {
			$scope = 'global';
		}

		$settings = array(
			'ads_enabled' => $scope, // stores 'global' | 'frontpage' | 'disabled'
		);

		update_option( 'bp_ads_settings', $settings );
		$this->set_notice( 'success', __( 'Settings saved.', 'bp-ads-manager' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=bp-ads-settings' ) );
		exit;
	}

	// ────────────────────────────────────────────────────────────────────────────
	// Admin notices
	// ────────────────────────────────────────────────────────────────────────────

	/**
	 * Stores an admin notice in a short-lived transient.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice text.
	 */
	private function set_notice( $type, $message ) {
		set_transient(
			'bp_ads_notice_' . get_current_user_id(),
			array( 'type' => $type, 'message' => $message ),
			60
		);
	}

	/**
	 * Outputs stored admin notice and clears the transient.
	 */
	public function show_notice() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'bp-ads' ) === false ) {
			return;
		}

		$key    = 'bp_ads_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$class = ( 'success' === $notice['type'] ) ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}

// ============================================================================
// WP List Table for ads
// ============================================================================

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Paginated list table for all ads.
 *
 * @extends WP_List_Table
 */
class BP_Ads_List_Table extends WP_List_Table {

	/** @var string Current view filter: 'banner' or 'popup'. */
	private $current_view = 'banner';

	/** Sets up singular/plural labels and resolves the active tab. */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'ad',
			'plural'   => 'ads',
			'ajax'     => false,
		) );

		// Resolve the active tab from the query string. Banner Ads is the
		// default landing tab; existing ads default to 'banner' so nothing
		// disappears for stores that upgrade.
		$requested          = isset( $_GET['ad_type'] ) ? sanitize_key( wp_unslash( $_GET['ad_type'] ) ) : 'banner';
		$this->current_view = in_array( $requested, array( 'banner', 'popup' ), true ) ? $requested : 'banner';
	}

	/**
	 * Returns the active view (ad type) for the current request.
	 *
	 * @return string 'banner' or 'popup'.
	 */
	public function get_current_view() {
		return $this->current_view;
	}

	/**
	 * Renders the "Banner Ads" / "Popup Ads" tab links above the list table.
	 *
	 * @return array views array consumed by WP_List_Table::views().
	 */
	protected function get_views() {
		$all       = BP_Ads_DB::get_all();
		$counts    = array( 'banner' => 0, 'popup' => 0 );
		foreach ( $all as $ad ) {
			$type = ( 'popup' === $ad->type ) ? 'popup' : 'banner';
			$counts[ $type ]++;
		}

		$views = array();
		$tabs  = array(
			'banner' => __( 'Banner Ads', 'bp-ads-manager' ),
			'popup'  => __( 'Popup Ads', 'bp-ads-manager' ),
		);

		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array( 'page' => 'bp-ads-manager', 'ad_type' => $slug ),
				admin_url( 'admin.php' )
			);
			$class = ( $this->current_view === $slug ) ? ' class="current"' : '';

			$views[ $slug ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( $label ),
				absint( $counts[ $slug ] )
			);
		}

		return $views;
	}

	/**
	 * Returns all column definitions.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'     => '<input type="checkbox">',
			'title'  => __( 'Title', 'bp-ads-manager' ),
			'type'   => __( 'Type', 'bp-ads-manager' ),
			'active' => __( 'Status', 'bp-ads-manager' ),
			'device' => __( 'Device Target', 'bp-ads-manager' ),
		);

		if ( 'popup' === $this->current_view ) {
			$columns['frequency']  = __( 'Frequency', 'bp-ads-manager' );
			$columns['delay']      = __( 'Delay', 'bp-ads-manager' );
			$columns['sort_order'] = __( 'Order', 'bp-ads-manager' );
		} else {
			$columns['placement'] = __( 'Placement', 'bp-ads-manager' );
		}

		$columns['created_at'] = __( 'Created', 'bp-ads-manager' );

		return $columns;
	}

	/**
	 * Placement column — lists the homepage sections a banner is assigned to.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_placement( $item ) {
		$choices = BP_Ads_DB::get_placement_choices();
		$slugs   = BP_Ads_DB::parse_placement( isset( $item->placement ) ? $item->placement : '' );

		$labels = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $choices[ $slug ] ) ) {
				$labels[] = $choices[ $slug ];
			}
		}

		if ( empty( $labels ) ) {
			return '<span class="bp-na">—</span>';
		}

		return esc_html( implode( ', ', $labels ) );
	}

	/**
	 * Returns sortable column definitions.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'title'      => array( 'title', false ),
			'type'       => array( 'type', false ),
			'sort_order' => array( 'sort_order', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Returns bulk action options.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'enable'  => __( 'Enable Selected', 'bp-ads-manager' ),
			'disable' => __( 'Disable Selected', 'bp-ads-manager' ),
			'delete'  => __( 'Delete Selected', 'bp-ads-manager' ),
		);
	}

	/**
	 * Default column renderer — escapes HTML output.
	 *
	 * @param object $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ad_ids[]" value="%d">',
			absint( $item->id )
		);
	}

	/**
	 * Title column with row actions (Edit / Delete).
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_title( $item ) {
		$edit_url    = admin_url( 'admin.php?page=bp-ads-edit&id=' . absint( $item->id ) );
		$delete_nonce = wp_create_nonce( 'bp_delete_ad_' . $item->id );

		$title = '<strong><a href="' . esc_url( $edit_url ) . '">'
		         . esc_html( $item->title )
		         . '</a></strong>';

		$row_actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'bp-ads-manager' ) . '</a>',
			'delete' => '<a href="#" class="bp-delete-ad submitdelete" '
			            . 'data-id="' . absint( $item->id ) . '" '
			            . 'data-nonce="' . esc_attr( $delete_nonce ) . '">'
			            . esc_html__( 'Delete', 'bp-ads-manager' )
			            . '</a>',
		);

		return $title . $this->row_actions( $row_actions );
	}

	/**
	 * Type column — pill badge.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_type( $item ) {
		$class = ( 'popup' === $item->type ) ? 'bp-badge-popup' : 'bp-badge-banner';
		return '<span class="bp-badge ' . esc_attr( $class ) . '">' . esc_html( ucfirst( $item->type ) ) . '</span>';
	}

	/**
	 * Status column — AJAX toggle button.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_active( $item ) {
		$is_active = (int) $item->active === 1;
		$nonce     = wp_create_nonce( 'bp_toggle_ad_' . $item->id );
		$label     = $is_active ? __( 'Active', 'bp-ads-manager' ) : __( 'Inactive', 'bp-ads-manager' );
		$css_class = $is_active ? 'bp-toggle-on' : 'bp-toggle-off';

		return sprintf(
			'<button type="button" class="bp-toggle-active %s" data-id="%d" data-nonce="%s">%s</button>',
			esc_attr( $css_class ),
			absint( $item->id ),
			esc_attr( $nonce ),
			esc_html( $label )
		);
	}

	/**
	 * Device column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_device( $item ) {
		$labels = array(
			'all'     => __( 'All Devices', 'bp-ads-manager' ),
			'mobile'  => __( 'Mobile Only', 'bp-ads-manager' ),
			'desktop' => __( 'Desktop Only', 'bp-ads-manager' ),
		);
		return esc_html( $labels[ $item->device ] ?? $item->device );
	}

	/**
	 * Frequency column — popup only.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_frequency( $item ) {
		if ( 'popup' !== $item->type ) {
			return '<span class="bp-na">—</span>';
		}
		return esc_html( 'once' === $item->frequency
			? __( 'Once per session', 'bp-ads-manager' )
			: __( 'Every visit', 'bp-ads-manager' )
		);
	}

	/**
	 * Delay column — popup only.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_delay( $item ) {
		if ( 'popup' !== $item->type ) {
			return '<span class="bp-na">—</span>';
		}
		return esc_html( absint( $item->popup_delay ) . 's' );
	}

	/**
	 * Order column — popup display sequence number.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_sort_order( $item ) {
		if ( 'popup' !== $item->type ) {
			return '<span class="bp-na">—</span>';
		}
		return '<span class="bp-sort-order">' . absint( $item->sort_order ) . '</span>';
	}

	/**
	 * Created column — formatted date.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );
	}

	/**
	 * Fetches and paginates items, applies simple column sorting.
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$all_ads      = BP_Ads_DB::get_all();

		// Filter to the active tab (Banner Ads / Popup Ads). Treat any
		// non-popup type as a banner so legacy rows always land in a tab.
		$view    = $this->current_view;
		$all_ads = array_values( array_filter( $all_ads, function ( $ad ) use ( $view ) {
			$type = ( 'popup' === $ad->type ) ? 'popup' : 'banner';
			return $type === $view;
		} ) );

		// Basic in-memory sort support.
		$orderby = sanitize_key( $_GET['orderby'] ?? 'created_at' );
		$order   = ( isset( $_GET['order'] ) && 'asc' === $_GET['order'] ) ? 'asc' : 'desc';

		usort( $all_ads, function ( $a, $b ) use ( $orderby, $order ) {
			$val_a = $a->$orderby ?? '';
			$val_b = $b->$orderby ?? '';
			$cmp   = strcmp( (string) $val_a, (string) $val_b );
			return ( 'asc' === $order ) ? $cmp : -$cmp;
		} );

		$total = count( $all_ads );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$this->items = array_slice( $all_ads, ( $current_page - 1 ) * $per_page, $per_page );
	}
}
