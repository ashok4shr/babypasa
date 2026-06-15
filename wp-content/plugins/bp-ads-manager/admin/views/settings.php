<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Settings page view.
 *
 * @var array $settings Current settings array (ads_enabled key holds scope string).
 */

// Normalise legacy integer value.
$raw_scope = $settings['ads_enabled'] ?? 'global';
if ( 1 === $raw_scope || '1' === $raw_scope ) {
	$raw_scope = 'global';
}
$scope = in_array( $raw_scope, array( 'global', 'frontpage', 'disabled' ), true ) ? $raw_scope : 'global';
?>
<div class="wrap bp-ads-wrap">
	<h1><?php esc_html_e( 'Ads Manager — Settings', 'bp-ads-manager' ); ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bp_save_settings' ); ?>
		<input type="hidden" name="action" value="bp_save_settings">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ad Display Scope', 'bp-ads-manager' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<?php esc_html_e( 'Ad Display Scope', 'bp-ads-manager' ); ?>
						</legend>

						<label style="display:block;margin-bottom:8px">
							<input
								type="radio"
								name="bp_ads_scope"
								value="global"
								<?php checked( $scope, 'global' ); ?>
							>
							<strong><?php esc_html_e( 'Global', 'bp-ads-manager' ); ?></strong>
							&nbsp;<span class="description"><?php esc_html_e( 'Show ads on every page of the site.', 'bp-ads-manager' ); ?></span>
						</label>

						<label style="display:block;margin-bottom:8px">
							<input
								type="radio"
								name="bp_ads_scope"
								value="frontpage"
								<?php checked( $scope, 'frontpage' ); ?>
							>
							<strong><?php esc_html_e( 'Front page only', 'bp-ads-manager' ); ?></strong>
							&nbsp;<span class="description"><?php esc_html_e( 'Show ads only on the homepage (front page / blog index).', 'bp-ads-manager' ); ?></span>
						</label>

						<label style="display:block">
							<input
								type="radio"
								name="bp_ads_scope"
								value="disabled"
								<?php checked( $scope, 'disabled' ); ?>
							>
							<strong><?php esc_html_e( 'Disabled', 'bp-ads-manager' ); ?></strong>
							&nbsp;<span class="description"><?php esc_html_e( 'No ads anywhere on the site, regardless of individual ad status.', 'bp-ads-manager' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'bp-ads-manager' ) ); ?>
	</form>
</div>
