<?php
/**
 * WooCommerce email class for price-drop alerts.
 *
 * Extends WC_Email so the alert appears in WooCommerce > Settings > Emails and
 * is rendered through the shared BabyPasa email header/footer (client design) —
 * the same path used by the Upaya and Returns custom emails. Replaces the old
 * plain-text wp_mail() notification that leaked raw wc_price() markup.
 *
 * @package Price_Drop_Notification
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Price_Drop_Email' ) && class_exists( 'WC_Email' ) ) {

	class BP_Price_Drop_Email extends WC_Email {

		/** @var WC_Product|null Product whose price dropped. */
		public $bp_product = null;

		/** @var string New (lower) price. */
		public $bp_new_price = '';

		/** @var string Previously subscribed price. */
		public $bp_old_price = '';

		/** @var string Recipient display name. */
		public $bp_customer_name = '';

		public function __construct() {
			$this->id             = 'bp_price_drop';
			$this->customer_email = true;
			$this->title          = __( 'Price Drop Alert', 'price-drop-notification' );
			$this->description    = __( 'Sent to a subscriber when a product they are watching drops below their subscribed price.', 'price-drop-notification' );

			// Render the body through the child theme so the shared header/footer wrap it.
			$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';
			$this->template_html  = 'emails/bp-price-drop.php';

			$this->placeholders = array(
				'{product_name}' => '',
			);

			parent::__construct();
		}

		/**
		 * Triggers the alert email.
		 *
		 * @param WP_User    $user      Subscriber.
		 * @param WC_Product $product   Product whose price dropped.
		 * @param string     $new_price New price.
		 * @param string     $old_price Subscribed price.
		 * @return void
		 */
		public function trigger( $user, $product, $new_price, $old_price ): void {
			$this->setup_locale();

			if ( $user instanceof WP_User && $product instanceof WC_Product ) {
				$this->object           = $product;
				$this->bp_product       = $product;
				$this->bp_new_price     = (string) $new_price;
				$this->bp_old_price     = (string) $old_price;
				$this->bp_customer_name = $user->display_name;
				$this->recipient        = $user->user_email;

				$this->placeholders['{product_name}'] = $product->get_name();
			}

			if ( $this->bp_product && $this->is_enabled() && $this->get_recipient() ) {
				$this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);
			}

			$this->restore_locale();
		}

		public function get_content_html(): string {
			return wc_get_template_html(
				$this->template_html,
				array(
					'product'       => $this->bp_product,
					'new_price'     => $this->bp_new_price,
					'old_price'     => $this->bp_old_price,
					'customer_name' => $this->bp_customer_name,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/** Always HTML — this design has no plain-text variant. */
		public function get_email_type() {
			return 'html';
		}

		public function get_content_type( $default_content_type = '' ): string {
			return 'text/html';
		}

		public function get_default_subject(): string {
			return __( 'Price drop on {product_name}!', 'price-drop-notification' );
		}

		public function get_default_heading(): string {
			return __( 'Good news — the price just dropped!', 'price-drop-notification' );
		}

		public function get_subject(): string {
			return $this->format_string( $this->get_option( 'subject' ) ?: $this->get_default_subject() );
		}

		public function get_heading(): string {
			return $this->format_string( $this->get_option( 'heading' ) ?: $this->get_default_heading() );
		}

		public function init_form_fields(): void {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'price-drop-notification' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'price-drop-notification' ),
					'default' => 'yes',
				),
				'subject' => array(
					'title'       => __( 'Subject', 'price-drop-notification' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'price-drop-notification' ), '{product_name}' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading' => array(
					'title'       => __( 'Email heading', 'price-drop-notification' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'price-drop-notification' ), '{product_name}' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'price-drop-notification' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'price-drop-notification' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}
}
