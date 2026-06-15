<?php
/**
 * My Account — Saved Addresses endpoint template.
 *
 * Variables available: $addresses (array[]), $locations (array<string,string>)
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bp-address-book woocommerce-MyAccount-content">

	<?php if ( isset( $_GET['bp_addr_saved'] ) ) : ?>
		<div class="woocommerce-message" role="alert">
			<?php esc_html_e( 'Address saved successfully.', 'babypasa-address-book' ); ?>
		</div>
	<?php endif; ?>

	<div class="bp-address-book__notice bp-address-book__notice--hidden" id="bp-addr-notice" role="alert"></div>

	<!-- Address Cards Grid -->
	<div class="bp-address-cards" id="bp-address-cards">

		<?php foreach ( $addresses as $addr ) : ?>
			<div class="bp-address-card<?php echo $addr['is_default'] ? ' bp-address-card--default' : ''; ?>"
				data-address-id="<?php echo esc_attr( $addr['id'] ); ?>">

				<div class="bp-address-card__header">
					<span class="bp-address-card__nickname"><?php echo esc_html( $addr['nickname'] ); ?></span>
					<?php if ( $addr['is_default'] ) : ?>
						<span class="bp-address-card__badge"><?php esc_html_e( 'Default', 'babypasa-address-book' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="bp-address-card__body">
					<p><?php echo esc_html( $addr['first_name'] . ' ' . $addr['last_name'] ); ?></p>
					<p><?php echo esc_html( $addr['city'] . ', ' . $addr['state'] ); ?></p>
					<?php if ( $addr['address_1'] ) : ?>
						<p><?php echo esc_html( $addr['address_1'] ); ?></p>
					<?php endif; ?>
					<?php if ( $addr['address_2'] ) : ?>
						<p><?php echo esc_html( $addr['address_2'] ); ?></p>
					<?php endif; ?>
					<?php if ( $addr['landmark'] ) : ?>
						<p class="bp-address-card__landmark">
							<?php /* translators: %s: landmark name */ ?>
							<?php echo esc_html( sprintf( __( 'Near: %s', 'babypasa-address-book' ), $addr['landmark'] ) ); ?>
						</p>
					<?php endif; ?>
					<p><?php echo esc_html( $addr['phone'] ); ?></p>
				</div>

				<div class="bp-address-card__actions">
					<button class="bp-addr-edit button button-secondary"
						data-address-id="<?php echo esc_attr( $addr['id'] ); ?>">
						<?php esc_html_e( 'Edit', 'babypasa-address-book' ); ?>
					</button>
					<?php if ( ! $addr['is_default'] ) : ?>
						<button class="bp-addr-set-default button button-secondary"
							data-address-id="<?php echo esc_attr( $addr['id'] ); ?>">
							<?php esc_html_e( 'Set Default', 'babypasa-address-book' ); ?>
						</button>
						<button class="bp-addr-delete button bp-button--danger"
							data-address-id="<?php echo esc_attr( $addr['id'] ); ?>">
							<?php esc_html_e( 'Delete', 'babypasa-address-book' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( count( $addresses ) < BP_Address_Book::MAX_ADDRESSES ) : ?>
			<div class="bp-address-card bp-address-card--add" id="bp-addr-add-new">
				<button class="bp-addr-add-btn" type="button">
					<span class="bp-addr-add-btn__icon">+</span>
					<?php esc_html_e( 'Add New Address', 'babypasa-address-book' ); ?>
				</button>
			</div>
		<?php else : ?>
			<p class="bp-address-book__limit-msg">
				<?php
				echo esc_html( sprintf(
					/* translators: %d: max address count */
					__( 'You have reached the maximum of %d saved addresses.', 'babypasa-address-book' ),
					BP_Address_Book::MAX_ADDRESSES
				) );
				?>
			</p>
		<?php endif; ?>

	</div><!-- .bp-address-cards -->

	<!-- Add / Edit Form (hidden until JS shows it) -->
	<div class="bp-address-form-wrapper" id="bp-address-form-wrapper" style="display:none;">

		<h3 id="bp-address-form-title"><?php esc_html_e( 'Add New Address', 'babypasa-address-book' ); ?></h3>

		<form id="bp-address-form" class="woocommerce-form" novalidate>

			<input type="hidden" name="address_id" id="bp_addr_id" value="">

			<p class="form-row form-row-wide">
				<label for="bp_addr_nickname">
					<?php esc_html_e( 'Address Label', 'babypasa-address-book' ); ?>
					<span class="required" title="<?php esc_attr_e( 'required', 'babypasa-address-book' ); ?>">*</span>
				</label>
				<input type="text" name="nickname" id="bp_addr_nickname" maxlength="50"
					placeholder="<?php esc_attr_e( 'e.g. Home, Office', 'babypasa-address-book' ); ?>"
					class="input-text" required>
			</p>

			<p class="form-row form-row-first">
				<label for="bp_addr_first_name">
					<?php esc_html_e( 'First Name', 'babypasa-address-book' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" name="first_name" id="bp_addr_first_name" class="input-text" required>
			</p>

			<p class="form-row form-row-last">
				<label for="bp_addr_last_name">
					<?php esc_html_e( 'Last Name', 'babypasa-address-book' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" name="last_name" id="bp_addr_last_name" class="input-text" required>
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_hub_area">
					<?php esc_html_e( 'Delivery Area', 'babypasa-address-book' ); ?>
					<span class="required">*</span>
				</label>
				<?php if ( empty( $locations ) ) : ?>
					<span class="bp-address-book__no-locations">
						<?php esc_html_e( 'Delivery areas are loading. Please visit the checkout page first to cache them, then return here.', 'babypasa-address-book' ); ?>
					</span>
				<?php else : ?>
					<select name="hub_area" id="bp_addr_hub_area" class="wc-enhanced-select" required>
						<option value=""><?php esc_html_e( '— Select Delivery Area —', 'babypasa-address-book' ); ?></option>
						<?php foreach ( $locations as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_address_1"><?php esc_html_e( 'Street Address', 'babypasa-address-book' ); ?></label>
				<input type="text" name="address_1" id="bp_addr_address_1" class="input-text">
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_address_2"><?php esc_html_e( 'Apt / Building', 'babypasa-address-book' ); ?></label>
				<input type="text" name="address_2" id="bp_addr_address_2" class="input-text">
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_landmark"><?php esc_html_e( 'Nearest Landmark', 'babypasa-address-book' ); ?></label>
				<input type="text" name="landmark" id="bp_addr_landmark" class="input-text"
					placeholder="<?php esc_attr_e( 'e.g. Near City Bank', 'babypasa-address-book' ); ?>">
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_postcode"><?php esc_html_e( 'Postal Code', 'babypasa-address-book' ); ?></label>
				<input type="text" name="postcode" id="bp_addr_postcode" class="input-text">
			</p>

			<p class="form-row form-row-first">
				<label for="bp_addr_phone">
					<?php esc_html_e( 'Mobile Number', 'babypasa-address-book' ); ?>
					<span class="required">*</span>
				</label>
				<input type="tel" name="phone" id="bp_addr_phone" class="input-text"
					pattern="[0-9]{10}"
					placeholder="<?php esc_attr_e( '10 digits, no country code', 'babypasa-address-book' ); ?>"
					required>
			</p>

			<p class="form-row form-row-last">
				<label for="bp_addr_alternate_phone"><?php esc_html_e( 'Alternate Mobile Number', 'babypasa-address-book' ); ?></label>
				<input type="tel" name="alternate_phone" id="bp_addr_alternate_phone" class="input-text"
					pattern="[0-9]{10}"
					placeholder="<?php esc_attr_e( 'Optional', 'babypasa-address-book' ); ?>">
			</p>

			<p class="form-row form-row-wide">
				<label for="bp_addr_email"><?php esc_html_e( 'Email Address', 'babypasa-address-book' ); ?></label>
				<input type="email" name="email" id="bp_addr_email" class="input-text">
			</p>

			<p class="form-row form-row-wide">
				<label class="bp-address-form__checkbox-label">
					<input type="checkbox" name="is_default" id="bp_addr_is_default" value="1">
					<?php esc_html_e( 'Set as my default address', 'babypasa-address-book' ); ?>
				</label>
			</p>

			<div class="bp-address-form__error" id="bp-addr-form-error" role="alert" style="display:none;"></div>

			<p class="form-row bp-address-form__buttons">
				<button type="submit" class="button wp-element-button" id="bp-addr-save-btn">
					<?php esc_html_e( 'Save Address', 'babypasa-address-book' ); ?>
				</button>
				<button type="button" class="button button-secondary bp-addr-cancel">
					<?php esc_html_e( 'Cancel', 'babypasa-address-book' ); ?>
				</button>
			</p>

		</form>
	</div><!-- .bp-address-form-wrapper -->

</div><!-- .bp-address-book -->
