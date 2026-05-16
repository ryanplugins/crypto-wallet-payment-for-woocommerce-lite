<?php
defined( 'ABSPATH' ) || exit;

/**
 * RyanPlugins_CWWLITE_Admin
 *
 * - Adds TX ID / network columns to the orders list.
 * - Adds a meta box on the order edit page for manual verification.
 * - Admin asset enqueueing.
 */
class RyanPlugins_CWWLITE_Admin {

    public function __construct() {
        // Orders list columns
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_order_columns' ] );
        add_filter( 'manage_edit-shop_order_columns',            [ $this, 'add_order_columns' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_order_column' ], 10, 2 );
        add_action( 'manage_shop_order_posts_custom_column',            [ $this, 'render_order_column' ], 10, 2 );

        // Order meta box
        add_action( 'add_meta_boxes',                   [ $this, 'add_meta_box' ] );
        add_action( 'save_post_shop_order',             [ $this, 'save_meta_box' ] );
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_meta_box' ] );

        // Admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        // AJAX: mark verified
        add_action( 'wp_ajax_ryanplugins_cwwlite_verify_order', [ $this, 'ajax_verify_order' ] );

        // Admin notice: Upgrade to Pro (7-day dismissal)
        add_action( 'admin_notices',                              [ $this, 'upgrade_admin_notice' ] );
        add_action( 'wp_ajax_ryanplugins_cwwlite_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );


    }

    // ─────────────────────────────────────────────────────────────────────────
    // Columns
    // ─────────────────────────────────────────────────────────────────────────

    public function add_order_columns( $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new['cwwlite_network'] = __( 'Crypto Network', 'crypto-wallet-payment-for-woocommerce-lite' );
                $new['cwwlite_txid']    = __( 'TX ID', 'crypto-wallet-payment-for-woocommerce-lite' );
            }
        }
        return $new;
    }

    public function render_order_column( $column, $order_or_id ) {
        $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( $order_or_id );
        if ( ! $order || $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) return;

        if ( $column === 'cwwlite_network' ) {
            $label = $order->get_meta( '_cwwlite_network_label' );
            echo $label
                ? '<span class="cwwlite-col-badge">' . esc_html( $label ) . '</span>'
                : '—';
        }

        if ( $column === 'cwwlite_txid' ) {
            $txid    = $order->get_meta( '_cwwlite_txid' );
            $network = $order->get_meta( '_cwwlite_network' );
            $cfg     = RyanPlugins_CWWLITE_Gateway::$networks[ $network ] ?? [];
            $explorer = $cfg['explorer'] ?? '';

            if ( $txid ) {
                $short = substr( $txid, 0, 12 ) . '…';
                if ( $explorer ) {
                    echo '<a href="' . esc_url( ryanplugins_cwwlite_explorer_url( $explorer, $txid ) ) . '" target="_blank" title="' . esc_attr( $txid ) . '">' . esc_html( $short ) . '</a>';
                } else {
                    echo '<span title="' . esc_attr( $txid ) . '">' . esc_html( $short ) . '</span>';
                }
            } else {
                echo '<em>' . esc_html__( 'Not provided', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</em>';
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Meta box
    // ─────────────────────────────────────────────────────────────────────────

    public function add_meta_box() {
        $screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
        foreach ( $screens as $screen ) {
            add_meta_box(
                'cwwlite_order_meta',
                __( 'Crypto Payment (Lite)', 'crypto-wallet-payment-for-woocommerce-lite' ),
                [ $this, 'render_meta_box' ],
                $screen,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WC_Order
            ? $post_or_order
            : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : $post_or_order );

        if ( ! $order || $order->get_payment_method() !== 'ryanplugins_cwwlite_crypto' ) {
            echo '<p>' . esc_html__( 'This order was not paid via Crypto Wallet (Lite).', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</p>';
            return;
        }

        $network = $order->get_meta( '_cwwlite_network' );
        $label   = $order->get_meta( '_cwwlite_network_label' );
        $symbol  = $order->get_meta( '_cwwlite_symbol' );
        $wallet  = $order->get_meta( '_cwwlite_wallet' );
        $amount  = $order->get_meta( '_cwwlite_crypto_amount' );
        $txid    = $order->get_meta( '_cwwlite_txid' );
        $cfg     = RyanPlugins_CWWLITE_Gateway::$networks[ $network ] ?? [];
        $explorer = $cfg['explorer'] ?? '';

        wp_nonce_field( 'cwwlite_save_meta_' . $order->get_id(), 'cwwlite_meta_nonce' );
        ?>
        <table class="cwwlite-meta-table" style="width:100%;font-size:12px;">
            <tr><th><?php esc_html_e( 'Network', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th><td><?php echo esc_html( $label ?: '—' ); ?></td></tr>
            <tr><th><?php esc_html_e( 'Wallet', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th><td><code style="word-break:break-all;"><?php echo esc_html( $wallet ?: '—' ); ?></code></td></tr>
            <?php if ( $amount ) : ?>
            <tr><th><?php esc_html_e( 'Amount', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th><td><?php echo esc_html( $amount . ' ' . $symbol ); ?></td></tr>
            <?php endif; ?>
        </table>

        <p style="margin-top:8px;"><label for="cwwlite_txid_admin"><strong><?php esc_html_e( 'TX ID:', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></strong></label><br>
        <input type="text" id="cwwlite_txid_admin" name="cwwlite_txid_admin"
               value="<?php echo esc_attr( $txid ); ?>"
               style="width:100%;font-size:11px;" /></p>

        <?php if ( $txid && $explorer ) : ?>
            <p><a href="<?php echo esc_url( ryanplugins_cwwlite_explorer_url( $explorer, $txid ) ); ?>" target="_blank">
                <?php esc_html_e( '🔗 View on block explorer', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
            </a></p>
        <?php endif; ?>

        <?php if ( in_array( $order->get_status(), [ 'on-hold', 'pending' ], true ) ) : ?>
            <button type="button"
                    class="button button-primary cwwlite-verify-btn"
                    data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'cwwlite_verify_' . $order->get_id() ) ); ?>"
                    style="margin-top:6px;width:100%;">
                <?php esc_html_e( '✅ Mark as Verified & Complete', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
            </button>
            <span class="cwwlite-verify-spinner" style="display:none;"> <?php esc_html_e( 'Updating…', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></span>
        <?php else : ?>
            <p style="color:green;">✅ <?php /* translators: %s: WooCommerce order status label */ printf( esc_html__( 'Status: %s', 'crypto-wallet-payment-for-woocommerce-lite' ), esc_html( wc_get_order_status_name( $order->get_status() ) ) ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function save_meta_box( $order_id ) {
        if ( ! isset( $_POST['cwwlite_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cwwlite_meta_nonce'] ) ), 'cwwlite_save_meta_' . $order_id ) ) return;
        if ( ! current_user_can( 'edit_shop_orders' ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        if ( isset( $_POST['cwwlite_txid_admin'] ) ) {
            $txid = sanitize_text_field( wp_unslash( $_POST['cwwlite_txid_admin'] ?? '' ) );
            $order->update_meta_data( '_cwwlite_txid', $txid );
            $order->save();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: manual verify
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_verify_order() {
        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error( 'unauthorized' );
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'cwwlite_verify_' . $order_id ) ) wp_send_json_error( 'nonce' );

        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_send_json_error( 'not_found' );

        $gateway  = WC()->payment_gateways()->payment_gateways()['ryanplugins_cwwlite_crypto'] ?? null;
        $new_status = $gateway ? $gateway->get_option( 'verified_status', 'processing' ) : 'processing';

        $order->update_status( $new_status, __( 'Manually marked as verified by admin (Crypto Wallet Lite).', 'crypto-wallet-payment-for-woocommerce-lite' ) );
        $order->update_meta_data( '_cwwlite_verified_by', 'manual' );
        $order->update_meta_data( '_cwwlite_verified_at', current_time( 'mysql' ) );
        $order->save();

        wp_send_json_success( [ 'new_status' => wc_get_order_status_name( $new_status ) ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────────

    public function admin_assets( $hook ) {
        $is_order_page = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
            || strpos( $hook, 'wc-orders' ) !== false;

        $is_settings_page = $this->is_cwwlite_settings_page();

        // ── Settings page: tabbed layout assets ───────────────────────────────
        if ( $is_settings_page ) {
            wp_enqueue_style(
                'ryanplugins-cwwlite-settings',
                RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/css/settings.css',
                [],
                RyanPlugins_CWWLITE_VERSION
            );
            wp_enqueue_script(
                'ryanplugins-cwwlite-settings',
                RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/js/settings.js',
                [],
                RyanPlugins_CWWLITE_VERSION,
                true // footer
            );
        }

        // ── Orders + settings page: admin utility styles ──────────────────────
        if ( $is_order_page || $is_settings_page || is_admin() ) {
            wp_enqueue_style(
                'ryanplugins-cwwlite-admin',
                RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/css/admin.css',
                [],
                RyanPlugins_CWWLITE_VERSION
            );
        }

        // ── Admin JS: orders page ──────────────────────────────────────────────
        wp_enqueue_script(
            'ryanplugins-cwwlite-admin',
            RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            RyanPlugins_CWWLITE_VERSION,
            true
        );
        wp_localize_script( 'ryanplugins-cwwlite-admin', 'cwwliteAdmin', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'dismissNonce' => wp_create_nonce( 'cwwlite_dismiss_notice' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin assets — ensure CSS+JS load on the gateway settings page too
    // ─────────────────────────────────────────────────────────────────────────

    private function is_cwwlite_settings_page(): bool {
        $screen = get_current_screen();
        if ( ! $screen ) return false;

        // WC → Settings → Payments tab — reading $_GET for page routing only, no processing
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $is_wc_settings = isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === 'wc-settings'
            && isset( $_GET['tab'] ) && sanitize_key( $_GET['tab'] ) === 'checkout'
            && isset( $_GET['section'] ) && sanitize_key( $_GET['section'] ) === 'ryanplugins_cwwlite_crypto';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return $is_wc_settings;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Upgrade to Pro — admin notice (re-appears after 7 days)
    // ─────────────────────────────────────────────────────────────────────────

    public function upgrade_admin_notice() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( ! is_admin() || wp_doing_ajax() ) return;

        $dismissed_at = (int) get_user_meta( get_current_user_id(), 'cwwlite_upgrade_notice_dismissed_at', true );
        if ( $dismissed_at && ( time() - $dismissed_at ) < 7 * DAY_IN_SECONDS ) return;

        $pro_url = 'https://www.patreon.com/posts/crypto-wallet-157796120?source=lite';
        $nonce   = wp_create_nonce( 'cwwlite_dismiss_notice' );
        ?>
        <div class="notice cwwlite-admin-notice is-dismissible" id="cwwlite-upgrade-notice"
             style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%);border:none;border-left:4px solid #f7971e;border-radius:0 6px 6px 0;padding:0;margin:5px 20px 20px 2px;box-shadow:0 2px 12px rgba(0,0,0,.18);overflow:hidden;position:relative;">
            <div class="notice-inner"
                 style="display:flex;align-items:center;gap:16px;padding:14px 48px 14px 18px;flex-wrap:wrap;box-sizing:border-box;">

                <!-- Icon -->
                <div class="notice-icon"
                     style="display:flex;align-items:center;justify-content:center;width:42px;height:42px;min-width:42px;background:linear-gradient(135deg,#f7971e,#ffd200);border-radius:50%;font-size:20px;line-height:1;box-shadow:0 2px 8px rgba(255,210,0,.4);flex-shrink:0;">
                    ⚡
                </div>

                <!-- Text -->
                <div class="notice-content" style="flex:1;min-width:0;">
                    <p class="notice-title"
                       style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 4px;padding:0;font-size:13.5px;font-weight:700;color:#fff;background:none;border:none;line-height:1.3;">
                        <?php esc_html_e( 'Crypto Wallet Payment (Lite) is active', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                        <span class="cwwlite-notice-badge"
                              style="display:inline-block;background:linear-gradient(135deg,#e65c00,#f9d423);color:#1a1a2e;font-size:9px;font-weight:800;padding:2px 7px;border-radius:20px;letter-spacing:.8px;text-transform:uppercase;vertical-align:middle;">
                            <?php esc_html_e( 'Upgrade Available', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                        </span>
                    </p>
                    <p class="notice-text"
                       style="color:#9daec0;font-size:12px;margin:0;line-height:1.6;">
                        <?php esc_html_e( 'You\'re on the free Lite plan — manual verification only.', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                        <?php echo wp_kses( __( ' Upgrade to <strong style="color:#c8d6e5;font-weight:600;">Pro</strong> for auto blockchain verification, browser wallet auto-send (MetaMask, Phantom, Solflare), stablecoins (USDT / USDC), fraud detection, refund workflow and more.', 'crypto-wallet-payment-for-woocommerce-lite' ), [ 'strong' => [ 'style' => true ] ] ); ?>
                    </p>
                </div>

                <!-- Actions -->
                <div class="notice-actions"
                     style="display:flex;align-items:center;gap:12px;flex-shrink:0;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener"
                       class="notice-cta"
                       style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#f7971e 0%,#ffd200 100%);color:#1a1a2e;font-weight:700;font-size:12px;padding:8px 18px;border-radius:5px;text-decoration:none;box-shadow:0 2px 8px rgba(247,151,30,.45);white-space:nowrap;border:none;">
                        <?php esc_html_e( 'Get Pro →', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                    </a>
                    <a href="#"
                       id="cwwlite-dismiss-notice"
                       class="notice-dismiss-link"
                       data-nonce="<?php echo esc_attr( $nonce ); ?>"
                       style="color:#7f8fa0;font-size:11px;text-decoration:none;white-space:nowrap;border:none;background:none;">
                        <?php esc_html_e( 'Dismiss for 7 days', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                    </a>
                </div>

            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: record dismissal timestamp (notice returns after 7 days)
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_dismiss_notice() {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'cwwlite_dismiss_notice' ) ) {
            wp_send_json_error( 'nonce' );
        }
        update_user_meta( get_current_user_id(), 'cwwlite_upgrade_notice_dismissed_at', time() );
        wp_send_json_success();
    }

}
