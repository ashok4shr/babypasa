<?php
/**
 * Feedback / review-request email (id: bp_feedback).
 *
 * Thank-you + request to review the purchased products. Scheduled a few days
 * after order completion by BP_OE_Feedback_Scheduler (Action Scheduler), and
 * also sendable on demand from the Order actions dropdown.
 *
 * The body (customer-feedback-request.php) lists every purchased product with a
 * per-product review link, so the class exposes those via get_template_vars().
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Feedback_Email extends BP_OE_Email_Base {

	/** One-shot guard meta: set once the feedback email has been sent for an order. */
	const SENT_META = '_bp_feedback_sent';

	/** @var string */
	protected $bp_template = 'emails/customer-feedback-request.php';

	public function __construct() {
		$this->id          = 'bp_feedback';
		$this->title       = __( 'Baby Pasa feedback request', 'babypasa-order-emails' );
		$this->description = __( 'Thank-you and product-review request, sent to the customer a few days after an order is completed.', 'babypasa-order-emails' );

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'How are you enjoying your order #{order_number}?', 'babypasa-order-emails' );
	}

	public function get_default_heading(): string {
		return __( 'We&rsquo;d love your feedback!', 'babypasa-order-emails' );
	}

	/** Whether the feedback email has already gone out for this order. */
	public static function already_sent( WC_Order $order ): bool {
		return (bool) $order->get_meta( self::SENT_META );
	}

	/**
	 * Products purchased on the order, each with a review link (#reviews on the
	 * product page). Variations resolve to their parent product's review page.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_template_vars(): array {
		$products    = array();
		$primary_url = '';

		if ( $this->object instanceof WC_Order ) {
			foreach ( $this->object->get_items() as $item ) {
				$product = $item->get_product();
				$pid     = $product ? $product->get_id() : 0;
				// A variation has no reviews of its own — point at the parent product.
				if ( $product && $product->is_type( 'variation' ) ) {
					$pid = $product->get_parent_id();
				}

				$url = $pid ? get_permalink( $pid ) . '#reviews' : '';
				if ( '' === $primary_url && $url ) {
					$primary_url = $url;
				}

				$products[] = array(
					'name' => $item->get_name(),
					'qty'  => (int) $item->get_quantity(),
					'url'  => $url,
				);
			}
		}

		// CTA fallback when no product URL is resolvable (e.g. deleted products).
		if ( '' === $primary_url ) {
			$primary_url = wc_get_page_permalink( 'shop' );
		}

		$days_since_order = 0;
		if ( $this->object instanceof WC_Order && $this->object->get_date_created() ) {
			$days_since_order = (int) floor( ( time() - $this->object->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS );
		}

		return array(
			'products'         => $products,
			'review_cta_url'   => $primary_url,
			'days_since_order' => $days_since_order,
		);
	}
}
