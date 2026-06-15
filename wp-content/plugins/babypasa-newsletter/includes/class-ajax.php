<?php
/**
 * Registers and handles all AJAX endpoints.
 */

namespace BabypasaNewsletter\Includes;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_bpnl_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_bpnl_subscribe',        array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_bpnl_send_newsletter',  array( $this, 'send_newsletter' ) );
		add_action( 'bpnl_send_batch',               array( $this, 'process_batch' ), 10, 2 );
	}

	/**
	 * Handles front-end subscription form submissions.
	 *
	 * Response shape mirrors babypasa-wishlist-compare:
	 *   { success: true,  data: { message: string } }
	 *   { success: false, data: { message: string } }
	 */
	public function subscribe(): void {
		check_ajax_referer( 'bpnl_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter valid email address.' ) );
		}

		$existing = Subscriber::get_by_email( $email );

		if ( $existing ) {
			if ( 'active' === $existing->status ) {
				wp_send_json_error( array( 'message' => 'You are already Subscribed.' ) );
			}

			// Re-activate previously unsubscribed address.
			Subscriber::reactivate( (int) $existing->id );
			wp_send_json_success( array( 'message' => 'Congratulation!! You are Subscribed to our Newsletter.' ) );
		}

		$new_id = Subscriber::insert( $email );
		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => 'Something went wrong. Please try again.' ) );
		}

		$subscriber = Subscriber::get_by_email( $email );
		if ( $subscriber ) {
			$template = Email::get_template( 'bpnl_template_welcome' );
			error_log( 'BPNL subscribe: template subject="' . $template['subject'] . '" body_length=' . strlen( $template['body'] ) );

			$sent = Email::send_welcome( $subscriber );
			error_log( 'BPNL subscribe: wp_mail() returned ' . ( $sent ? 'true' : 'false' ) . ' for ' . $email );

			if ( ! $sent ) {
				global $phpmailer;
				if ( isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
					error_log( 'BPNL subscribe: mailer error — ' . $phpmailer->ErrorInfo );
				}
			}
		} else {
			error_log( 'BPNL subscribe: could not re-fetch subscriber after insert for ' . $email );
		}

		wp_send_json_success( array( 'message' => 'Congratulation!! You are Subscribed to our Newsletter.' ) );
	}

	/**
	 * Handles admin newsletter send requests.
	 */
	public function send_newsletter(): void {
		check_ajax_referer( 'bpnl_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$recipients_raw = isset( $_POST['recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['recipients'] ) ) : 'all';
		$subject        = isset( $_POST['subject'] )    ? sanitize_text_field( wp_unslash( $_POST['subject'] ) )    : '';
		$body           = isset( $_POST['body'] )       ? wp_kses_post( wp_unslash( $_POST['body'] ) )              : '';
		$reply_to       = isset( $_POST['reply_to'] )   ? sanitize_email( wp_unslash( $_POST['reply_to'] ) )        : '';

		if ( empty( $subject ) || empty( $body ) ) {
			wp_send_json_error( array( 'message' => 'Subject and body are required.' ) );
		}

		$subscriber_ids = array();
		if ( 'all' !== $recipients_raw ) {
			$decoded = json_decode( stripslashes( $recipients_raw ), true );
			if ( is_array( $decoded ) ) {
				$subscriber_ids = array_map( 'intval', $decoded );
			}
		}

		$subscribers = Subscriber::get_active_subscribers( $subscriber_ids );

		if ( empty( $subscribers ) ) {
			wp_send_json_error( array( 'message' => 'No active subscribers found.' ) );
		}

		$template = array(
			'subject'  => $subject,
			'body'     => $body,
			'reply_to' => $reply_to,
		);

		if ( count( $subscribers ) > 50 ) {
			$chunks = array_chunk(
				array_map( function ( object $s ) { return (int) $s->id; }, $subscribers ),
				50
			);
			foreach ( $chunks as $i => $chunk ) {
				wp_schedule_single_event(
					time() + ( $i * 60 ),
					'bpnl_send_batch',
					array( $chunk, $template )
				);
			}
			wp_send_json_success(
				array(
					'message' => sprintf(
						'Newsletter scheduled for %d subscribers in %d batches.',
						count( $subscribers ),
						count( $chunks )
					),
					'sent'    => 0,
					'failed'  => array(),
					'batched' => true,
				)
			);
		}

		$sent   = 0;
		$failed = array();
		foreach ( $subscribers as $subscriber ) {
			if ( Email::send_to( $subscriber, $template ) ) {
				++$sent;
			} else {
				$failed[] = $subscriber->email;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf( 'Newsletter sent: %d sent, %d failed.', $sent, count( $failed ) ),
				'sent'    => $sent,
				'failed'  => $failed,
				'batched' => false,
			)
		);
	}

	/**
	 * WP-Cron callback that sends a single batch.
	 *
	 * @param int[]  $ids      Subscriber IDs to send to.
	 * @param array{subject:string,body:string,reply_to:string} $template
	 */
	public function process_batch( array $ids, array $template ): void {
		if ( empty( $ids ) || empty( $template ) ) {
			return;
		}
		$subscribers = Subscriber::get_active_subscribers( $ids );
		foreach ( $subscribers as $subscriber ) {
			Email::send_to( $subscriber, $template );
		}
	}
}
