<?php
defined( 'ABSPATH' ) || exit;

/**
 * RyanPlugins_CWWLITE_Order_Handler
 *
 * Lets customers submit or update their TX ID from the My Account / order-details page.
 */
class RyanPlugins_CWWLITE_Order_Handler {

    public function __construct() {
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'txid_update_form' ] );
        add_action( 'init',                                        [ $this, 'handle_txid_submission' ] );
        add_filter( 'woocommerce_get_order_item_totals',           [ $this, 'add_txid_to_order_table' ], 10, 2 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'verification_status_notice' ], 2 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'retry_payment_button' ], 5 );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Retry button
    // ─────────────────────────────────────────────────────────────────────────

    public function retry_payment_button( $order ) {
        if ( $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return;
        if ( ! in_array( $order->get_status(), [ 'on-hold', 'pending' ], true ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() ) return;

        $pay_url       = $order->get_checkout_payment_url();
        $network_label = $order->get_meta( '_cwwlite_network_label' ) ?: __( 'Crypto', 'wc-ryanplugins-crypto-wallet-lite' );
        ?>
        <section class="cwwlite-retry-section">
            <p class="cwwlite-retry-notice">
                <?php printf(
                    /* translators: %s: cryptocurrency network name e.g. Bitcoin (BTC) */
                    esc_html__( 'This order is awaiting %s payment. If your previous payment failed or expired, you can retry without creating a new order.', 'wc-ryanplugins-crypto-wallet-lite' ),
                    esc_html( $network_label )
                ); ?>
            </p>
            <a href="<?php echo esc_url( $pay_url ); ?>" class="button cwwlite-retry-btn">
                <?php esc_html_e( 'Retry Payment', 'wc-ryanplugins-crypto-wallet-lite' ); ?>
            </a>
        </section>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TX ID update form
    // ─────────────────────────────────────────────────────────────────────────

    public function txid_update_form( $order ) {
        if ( $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return;
        if ( ! in_array( $order->get_status(), [ 'on-hold', 'pending' ], true ) ) return;

        $existing_txid = $order->get_meta( '_cwwlite_txid' );
        $network_label = $order->get_meta( '_cwwlite_network_label' );
        $wallet        = $order->get_meta( '_cwwlite_wallet' );
        $amount        = $order->get_meta( '_cwwlite_crypto_amount' );
        $symbol        = $order->get_meta( '_cwwlite_symbol' );
        ?>
        <section class="cwwlite-txid-update">
            <h2><?php esc_html_e( 'Submit Transaction ID', 'wc-ryanplugins-crypto-wallet-lite' ); ?></h2>
            <p><?php printf(
                /* translators: %s: cryptocurrency network name e.g. Bitcoin (BTC) */
                esc_html__( 'Please send your %s payment to the address below, then paste the transaction hash here.', 'wc-ryanplugins-crypto-wallet-lite' ),
                esc_html( $network_label )
            ); ?></p>

            <p><strong><?php esc_html_e( 'Send To:', 'wc-ryanplugins-crypto-wallet-lite' ); ?></strong><br>
               <code><?php echo esc_html( $wallet ); ?></code></p>

            <?php if ( $amount ) : ?>
            <p><strong><?php esc_html_e( 'Amount:', 'wc-ryanplugins-crypto-wallet-lite' ); ?></strong>
               <?php echo esc_html( $amount . ' ' . $symbol ); ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'cwwlite_submit_txid_' . $order->get_id(), 'cwwlite_txid_nonce' ); ?>
                <input type="hidden" name="cwwlite_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />

                <p>
                    <label for="cwwlite_txid_input"><strong><?php esc_html_e( 'Transaction ID / Hash:', 'wc-ryanplugins-crypto-wallet-lite' ); ?></strong></label><br>
                    <input type="text"
                           id="cwwlite_txid_input"
                           name="cwwlite_txid_value"
                           value="<?php echo esc_attr( $existing_txid ); ?>"
                           placeholder="<?php esc_attr_e( 'Paste your TX hash here', 'wc-ryanplugins-crypto-wallet-lite' ); ?>"
                           style="width:100%;max-width:500px;" />
                </p>
                <p>
                    <button type="submit" class="button" name="cwwlite_submit_txid">
                        <?php esc_html_e( 'Submit Transaction ID', 'wc-ryanplugins-crypto-wallet-lite' ); ?>
                    </button>
                </p>
            </form>
        </section>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Handle TX ID form submission
    // ─────────────────────────────────────────────────────────────────────────

    public function handle_txid_submission() {
        if ( ! isset( $_POST['cwwlite_submit_txid'] ) ) return;

        $order_id = absint( $_POST['cwwlite_order_id'] ?? 0 );
        if ( ! $order_id ) return;

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cwwlite_txid_nonce'] ?? '' ) ), 'cwwlite_submit_txid_' . $order_id ) ) {
            wc_add_notice( __( 'Security check failed. Please try again.', 'wc-ryanplugins-crypto-wallet-lite' ), 'error' );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return;
        if ( ! in_array( $order->get_status(), [ 'on-hold', 'pending' ], true ) ) return;

        // Ownership check
        if ( is_user_logged_in() ) {
            if ( $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() ) {
                wc_add_notice( __( 'You are not allowed to update this order.', 'wc-ryanplugins-crypto-wallet-lite' ), 'error' );
                return;
            }
        } else {
            $billing_email = $order->get_billing_email();
            if ( $billing_email ) {
                // Allow unauthenticated if they are coming from the order-received page (loose check).
            }
        }

$txid = sanitize_text_field( wp_unslash( $_POST['cwwlite_txid_value'] ?? '' ) );
        if ( empty( $txid ) ) {
            wc_add_notice( __( 'Please enter a transaction ID.', 'wc-ryanplugins-crypto-wallet-lite' ), 'error' );
            return;
        }
        if ( strlen( $txid ) < 10 || strlen( $txid ) > 200 || ! preg_match( '/^[a-zA-Z0-9]+$/', $txid ) ) {
            wc_add_notice( __( 'The transaction ID looks invalid. Please double-check it.', 'wc-ryanplugins-crypto-wallet-lite' ), 'error' );
            return;
        }

        $order->update_meta_data( '_cwwlite_txid', $txid );
        $order->add_order_note( sprintf(
            /* translators: %s: blockchain transaction hash/ID */
            __( 'Customer submitted TX ID: %s', 'wc-ryanplugins-crypto-wallet-lite' ),
            $txid
        ) );
        $order->save();

        wc_add_notice( __( 'Thank you! Your transaction ID has been submitted. We will verify and update your order shortly.', 'wc-ryanplugins-crypto-wallet-lite' ), 'success' );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Add TX ID row to order totals table
    // ─────────────────────────────────────────────────────────────────────────

    public function add_txid_to_order_table( $total_rows, $order ): array {
        if ( $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return $total_rows;

        $txid    = $order->get_meta( '_cwwlite_txid' );
        $network = $order->get_meta( '_cwwlite_network' );
        $amount  = $order->get_meta( '_cwwlite_crypto_amount' );
        $symbol  = $order->get_meta( '_cwwlite_symbol' );
        $cfg     = RyanPlugins_CWWLITE_Gateway::$networks[ $network ] ?? [];
        $explorer = $cfg['explorer'] ?? '';

        if ( $amount && $symbol ) {
            $total_rows['cwwlite_crypto_amount'] = [
                'label' => __( 'Crypto Amount:', 'wc-ryanplugins-crypto-wallet-lite' ),
                'value' => esc_html( $amount . ' ' . $symbol ),
            ];
        }

        if ( $txid ) {
            $tx_display = $explorer
                ? '<a href="' . esc_url( ryanplugins_cwwlite_explorer_url( $explorer, $txid ) ) . '" target="_blank">' . esc_html( $txid ) . '</a>'
                : esc_html( $txid );

            $total_rows['cwwlite_txid'] = [
                'label' => __( 'TX ID:', 'wc-ryanplugins-crypto-wallet-lite' ),
                'value' => $tx_display,
            ];
        }

        return $total_rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Verification status notice
    // ─────────────────────────────────────────────────────────────────────────

    public function verification_status_notice( $order ) {
        if ( $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return;

        $verified_by = $order->get_meta( '_cwwlite_verified_by' );
        $status      = $order->get_status();

        if ( $verified_by === 'manual' && in_array( $status, [ 'processing', 'completed' ], true ) ) {
            echo '<div class="woocommerce-info cwwlite-verified-notice">✅ '
                . esc_html__( 'Your crypto payment has been manually verified.', 'wc-ryanplugins-crypto-wallet-lite' )
                . '</div>';
        } elseif ( in_array( $status, [ 'on-hold', 'pending' ], true ) ) {
            $txid = $order->get_meta( '_cwwlite_txid' );
            if ( $txid ) {
                echo '<div class="woocommerce-info cwwlite-pending-notice">⏳ '
                    . esc_html__( 'Your transaction has been received and is awaiting manual verification by our team.', 'wc-ryanplugins-crypto-wallet-lite' )
                    . '</div>';
            }
        }
    }
}
