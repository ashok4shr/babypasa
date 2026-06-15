<?php
/**
 * Admin view: Subscribers list table.
 */

defined( 'ABSPATH' ) || exit;

$list_table = new \BabypasaNewsletter\Admin\List_Table();
$list_table->prepare_items();

$total_active = \BabypasaNewsletter\Includes\Subscriber::count( array( 'status' => 'active' ) );
$total_all    = \BabypasaNewsletter\Includes\Subscriber::count();
?>
<div class="wrap bpnl-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Newsletter Subscribers', 'babypasa-newsletter' ); ?></h1>
	<hr class="wp-header-end">

	<div class="bpnl-stats-bar">
		<span class="bpnl-stat">
			<?php
			printf(
				/* translators: %d: number of total subscribers */
				esc_html__( 'Total: %d', 'babypasa-newsletter' ),
				(int) $total_all
			);
			?>
		</span>
		<span class="bpnl-stat bpnl-stat-active">
			<?php
			printf(
				/* translators: %d: number of active subscribers */
				esc_html__( 'Active: %d', 'babypasa-newsletter' ),
				(int) $total_active
			);
			?>
		</span>
	</div>

	<form method="post" id="bpnl-subscribers-form">
		<?php $list_table->search_box( __( 'Search Subscribers', 'babypasa-newsletter' ), 'subscriber' ); ?>
		<input type="hidden" name="page" value="bpnl-subscribers">
		<?php $list_table->display(); ?>
	</form>
</div>
