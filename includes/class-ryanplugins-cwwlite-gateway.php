<?php
defined( 'ABSPATH' ) || exit;

/**
 * RyanPlugins_CWWLITE_Gateway
 *
 * Lite WooCommerce payment gateway for crypto.
 *
 * Supported networks (Lite): Bitcoin (BTC), Ethereum (ETH), Solana (SOL), XRP.
 * — No stablecoins, no EVM L2s, no Cardano browser-wallet auto-send.
 * — Manual TX submission only; admin verifies orders by hand.
 * — Live exchange rates via CoinGecko (no API key needed).
 */
class RyanPlugins_CWWLITE_Gateway extends WC_Payment_Gateway {

    public string $instructions       = '';
    public string $exchange_rate_note = '';
    public string $hold_message       = '';

    // ─────────────────────────────────────────────────────────────────────────
    // Supported networks (Lite edition)
    // ─────────────────────────────────────────────────────────────────────────

    public static array $networks = [
        'bitcoin' => [
            'label'       => 'Bitcoin (BTC)',
            'symbol'      => 'BTC',
            'coingecko'   => 'bitcoin',
            'icon'        => 'bitcoin',
            'explorer'    => 'https://blockstream.info/tx/',
            'addr_prefix' => [ '1', '3', 'bc1' ],
            'addr_len'    => [ 25, 62 ],
            'wallet_type' => 'manual',
        ],
        'ethereum' => [
            'label'       => 'Ethereum (ETH)',
            'symbol'      => 'ETH',
            'coingecko'   => 'ethereum',
            'icon'        => 'ethereum',
            'explorer'    => 'https://etherscan.io/tx/',
            'addr_prefix' => [ '0x' ],
            'addr_len'    => [ 42, 42 ],
            'wallet_type' => 'manual',
        ],
        'solana' => [
            'label'       => 'Solana (SOL)',
            'symbol'      => 'SOL',
            'coingecko'   => 'solana',
            'icon'        => 'solana',
            'explorer'    => 'https://solscan.io/tx/',
            'addr_prefix' => [],
            'addr_len'    => [ 43, 44 ],
            'wallet_type' => 'manual',
        ],
        'xrp' => [
            'label'       => 'XRP (Ripple)',
            'symbol'      => 'XRP',
            'coingecko'   => 'ripple',
            'icon'        => 'xrp',
            'explorer'    => 'https://xrpscan.com/tx/',
            'addr_prefix' => [ 'r' ],
            'addr_len'    => [ 25, 34 ],
            'wallet_type' => 'manual',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────────────────

    public function __construct() {
        $this->id                 = 'ryanplugins_cwwlite_crypto';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __( 'Crypto Wallet Payment (Lite)', 'crypto-wallet-payment-for-woocommerce-lite' );
        $this->method_description = __( 'Accept BTC, ETH, SOL and XRP. Manual verification — no API keys needed.', 'crypto-wallet-payment-for-woocommerce-lite' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title               = $this->get_option( 'title' );
        $this->description         = $this->get_option( 'description' );
        $this->instructions        = $this->get_option( 'instructions' );
        $this->exchange_rate_note  = $this->get_option( 'exchange_rate_note' );
        $this->hold_message        = $this->get_option( 'hold_message' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_payment_fields' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
        add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_assets' ] );

        // AJAX: live rates
        add_action( 'wp_ajax_ryanplugins_cwwlite_get_rates',        [ $this, 'ajax_get_rates' ] );
        add_action( 'wp_ajax_nopriv_ryanplugins_cwwlite_get_rates', [ $this, 'ajax_get_rates' ] );

        // AJAX: poll order status (customer polling)
        add_action( 'wp_ajax_ryanplugins_cwwlite_order_status',        [ $this, 'ajax_order_status' ] );
        add_action( 'wp_ajax_nopriv_ryanplugins_cwwlite_order_status', [ $this, 'ajax_order_status' ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings
    // ─────────────────────────────────────────────────────────────────────────

    public function init_form_fields() {
        $this->form_fields = [

            // ── General ──────────────────────────────────────────────────────
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Crypto Wallet Payment (Lite)', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __( 'Payment Title', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'text',
                'description' => __( 'Title shown at checkout.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => __( 'Pay with Crypto (BTC / ETH / SOL / XRP)', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'   => __( 'Description', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'    => 'textarea',
                'default' => __( 'Pay with cryptocurrency. Your order is confirmed once we manually verify your transaction.', 'crypto-wallet-payment-for-woocommerce-lite' ),
            ],
            'instructions' => [
                'title'   => __( 'Thank You Page Instructions', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'    => 'textarea',
                'default' => __( 'Please send the exact crypto amount shown to the wallet address below. Paste your transaction ID and we will confirm your order within 24 hours.', 'crypto-wallet-payment-for-woocommerce-lite' ),
            ],
            'exchange_rate_note' => [
                'title'    => __( 'Exchange Rate Note', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'     => 'text',
                'default'  => __( 'Crypto amount is approximate. Please send the displayed amount.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'desc_tip' => true,
            ],
            'hold_message' => [
                'title'   => __( 'On-Hold Order Message', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'    => 'textarea',
                'default' => __( 'Your order is on hold pending crypto payment verification. We will update your order once we confirm your transaction.', 'crypto-wallet-payment-for-woocommerce-lite' ),
            ],

            // ── Networks ─────────────────────────────────────────────────────
            'networks_section' => [
                'title'       => __( 'Wallet Addresses', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'title',
                'description' => __( 'Enter your wallet addresses for each network you want to accept. Leave blank to hide a network at checkout.', 'crypto-wallet-payment-for-woocommerce-lite' ),
            ],
            'wallet_bitcoin' => [
                'title'       => __( 'Bitcoin (BTC) Address', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'text',
                'description' => __( 'Your mainnet BTC wallet address.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'wallet_ethereum' => [
                'title'       => __( 'Ethereum (ETH) Address', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'text',
                'description' => __( 'Your mainnet ETH wallet address (0x…).', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'wallet_solana' => [
                'title'       => __( 'Solana (SOL) Address', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'text',
                'description' => __( 'Your mainnet SOL wallet address.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'wallet_xrp' => [
                'title'       => __( 'XRP Address', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'text',
                'description' => __( 'Your mainnet XRP wallet address.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => '',
                'desc_tip'    => true,
            ],

            // ── Order status ──────────────────────────────────────────────────
            'verified_status' => [
                'title'       => __( 'Manually Verified Order Status', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'        => 'select',
                'description' => __( 'Order status to set when you manually mark a transaction as verified in the admin.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'     => 'processing',
                'desc_tip'    => true,
                'options'     => [
                    'processing' => __( 'Processing', 'crypto-wallet-payment-for-woocommerce-lite' ),
                    'completed'  => __( 'Completed', 'crypto-wallet-payment-for-woocommerce-lite' ),
                ],
            ],

            // ── Rate Lock ─────────────────────────────────────────────────────
            'rate_lock_minutes' => [
                'title'             => __( 'Rate Valid For (minutes)', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'type'              => 'number',
                'description'       => __( 'How many minutes the quoted crypto amount stays locked. 0 disables the countdown timer.', 'crypto-wallet-payment-for-woocommerce-lite' ),
                'default'           => '15',
                'desc_tip'          => true,
                'custom_attributes' => [ 'min' => '0', 'max' => '60', 'step' => '1' ],
            ],

        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tabbed settings page (overrides WC default flat layout)
    // ─────────────────────────────────────────────────────────────────────────

    public function admin_options() {
        // Compute missing-wallet badge count for the Networks tab
        $missing_wallets = 0;
        foreach ( array_keys( self::$networks ) as $key ) {
            if ( empty( $this->get_option( 'wallet_' . $key ) ) ) $missing_wallets++;
        }
        $all_missing = ( $missing_wallets === count( self::$networks ) );
        ?>
        <div id="ryanplugins-cwwlite-settings-wrap">
            <form method="post" id="mainform" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'woocommerce-settings' ); ?>
            <input type="hidden"
                   name="ryanplugins_cwwlite_active_tab"
                   id="ryanplugins-cwwlite-active-tab-input"
                   value="general" />

            <!-- ── Tab nav ───────────────────────────────────────────────── -->
            <div class="ryanplugins-cwwlite-nav-tabs" role="tablist">

              <button type="button"
                      class="ryanplugins-cwwlite-nav-tab ryanplugins-cwwlite-nav-tab-active"
                      data-tab="general"
                      role="tab" aria-selected="true"
                      aria-controls="ryanplugins-cwwlite-panel-general">
                  <?php esc_html_e( '⚙ General', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
              </button>

              <button type="button"
                      class="ryanplugins-cwwlite-nav-tab"
                      data-tab="networks"
                      role="tab" aria-selected="false"
                      aria-controls="ryanplugins-cwwlite-panel-networks">
                  <?php esc_html_e( '🔗 Networks & Wallets', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                  <?php if ( $all_missing ) : ?>
                      <span class="ryanplugins-cwwlite-tab-badge ryanplugins-cwwlite-tab-badge-warn"
                            title="<?php esc_attr_e( 'No wallets configured', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>">!</span>
                  <?php elseif ( $missing_wallets > 0 ) : ?>
                      <span class="ryanplugins-cwwlite-tab-badge ryanplugins-cwwlite-tab-badge-warn"
                            title="<?php /* translators: %d: number of crypto networks without a wallet address configured */ echo esc_attr( sprintf( __( '%d network(s) not set', 'crypto-wallet-payment-for-woocommerce-lite' ), $missing_wallets ) ); ?>">
                          <?php echo esc_html( $missing_wallets ); ?>
                      </span>
                  <?php endif; ?>
              </button>

            </div><!-- .ryanplugins-cwwlite-nav-tabs -->

            <!-- ── Tab: General ──────────────────────────────────────────── -->
            <div class="ryanplugins-cwwlite-tab-panel ryanplugins-cwwlite-tab-panel-active"
                 id="ryanplugins-cwwlite-panel-general"
                 role="tabpanel">

              <div class="ryanplugins-cwwlite-section-heading">
                  <?php esc_html_e( 'GENERAL SETTINGS', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
              </div>
              <table class="form-table">
                <?php
                $saved = $this->form_fields;
                $this->form_fields = array_intersect_key( $saved, array_flip( [ 'enabled', 'title', 'description', 'instructions', 'exchange_rate_note', 'hold_message' ] ) );
                $this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $this->form_fields = $saved;
                ?>
              </table>

              <div class="ryanplugins-cwwlite-section-heading" style="margin-top:20px;">
                  <?php esc_html_e( 'ORDER SETTINGS', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
              </div>
              <table class="form-table">
                <?php
                $saved = $this->form_fields;
                $this->form_fields = array_intersect_key( $saved, array_flip( [ 'verified_status', 'rate_lock_minutes' ] ) );
                $this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $this->form_fields = $saved;
                ?>
              </table>

            </div><!-- #ryanplugins-cwwlite-panel-general -->

            <!-- ── Tab: Networks & Wallets ───────────────────────────────── -->
            <div class="ryanplugins-cwwlite-tab-panel"
                 id="ryanplugins-cwwlite-panel-networks"
                 role="tabpanel" hidden>

              <div class="ryanplugins-cwwlite-section-heading">
                  <?php esc_html_e( 'WALLET ADDRESSES', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
              </div>
              <p class="description" style="margin-bottom:16px;">
                  <?php esc_html_e( 'Enter your wallet address for each network you want to accept. Leave blank to hide that network at checkout.', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
              </p>
              <table class="form-table">
                <?php
                $saved = $this->form_fields;
                $this->form_fields = array_intersect_key( $saved, array_flip( [ 'wallet_bitcoin', 'wallet_ethereum', 'wallet_solana', 'wallet_xrp' ] ) );
                $this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $this->form_fields = $saved;
                ?>
              </table>

            </div><!-- #ryanplugins-cwwlite-panel-networks -->

            <!-- ── Save button — WC's outer form submits this ───────────── -->
            <p class="ryanplugins-cwwlite-submit" id="ryanplugins-cwwlite-save-btn-row">
                <button type="submit" name="save" value="Save changes" class="button-primary">
                    <?php esc_html_e( 'Save changes', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                </button>
            </p>

            </form>
        </div><!-- #ryanplugins-cwwlite-settings-wrap -->
        <?php
    }



    public function process_payment_fields() {
        $this->process_admin_options();
        // Bust rate cache on save.
        foreach ( [ 'usd', 'eur', 'gbp', 'php', 'jpy', 'aud', 'cad' ] as $cur ) {
            delete_transient( 'ryanplugins_cwwlite_live_rates_' . $cur );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Checkout: payment fields
    // ─────────────────────────────────────────────────────────────────────────

    public function payment_fields() {
        $networks  = $this->get_enabled_networks();
        $amounts   = $this->get_crypto_display_amounts( $networks );
        $rate_lock = absint( $this->get_option( 'rate_lock_minutes', 15 ) );
        $img_url   = RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/img/';

        if ( empty( $networks ) ) {
            echo '<p class="cwwlite-no-networks">' . esc_html__( 'No crypto networks are configured yet. Please contact the store owner.', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</p>';
            return;
        }

        $first_key = array_key_first( $networks );
        ?>

        <div class="cwwlite-checkout-wrap">

            <?php if ( $this->description ) : ?>
                <p class="cwwlite-description"><?php echo wp_kses_post( $this->description ); ?></p>
            <?php endif; ?>

            <!-- ── Network pill selector ─────────────────────────────────── -->
            <p class="cwwlite-select-label"><?php esc_html_e( 'Select network:', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></p>

            <div class="cwwlite-pill-selector" role="group" aria-label="<?php esc_attr_e( 'Select cryptocurrency', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>">
                <?php foreach ( $networks as $key => $cfg ) :
                    $icon_file = $img_url . esc_attr( $cfg['icon'] ) . '.svg';
                    $active    = ( $key === $first_key ) ? ' cwwlite-pill-active' : '';
                ?>
                <button type="button"
                        class="cwwlite-pill<?php echo esc_attr( $active ); ?>"
                        data-network="<?php echo esc_attr( $key ); ?>"
                        aria-pressed="<?php echo $key === $first_key ? 'true' : 'false'; ?>">
                    <img src="<?php echo esc_url( $icon_file ); ?>"
                         alt="<?php echo esc_attr( $cfg['symbol'] ); ?>"
                         class="cwwlite-pill-icon"
                         width="20" height="20"
                         onerror="this.style.display='none'" />
                    <span class="cwwlite-pill-label"><?php echo esc_html( $cfg['label'] ); ?></span>
                </button>
                <?php endforeach; ?>
            </div><!-- .cwwlite-pill-selector -->

            <!-- Hidden input carries the selected network -->
            <input type="hidden" id="cwwlite_network" name="cwwlite_network" value="<?php echo esc_attr( $first_key ); ?>" />

            <!-- ── Rate countdown bar ─────────────────────────────────────── -->
            <?php if ( $rate_lock > 0 ) : ?>
            <div class="cwwlite-rate-bar">
                <span class="cwwlite-rate-bar-icon">⏱</span>
                <?php esc_html_e( 'Rate valid for:', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                <span class="cwwlite-countdown cwwlite-countdown-highlight"
                      data-seconds="<?php echo esc_attr( $rate_lock * 60 ); ?>">
                    <?php echo esc_html( sprintf( '%d:00', $rate_lock ) ); ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- ── Per-network detail panels ─────────────────────────────── -->
            <?php foreach ( $networks as $key => $cfg ) :
                $wallet = $this->get_option( 'wallet_' . $key );
                $amount = $amounts[ $key ] ?? '';
            ?>
            <div class="cwwlite-network-panel" id="cwwlite-panel-<?php echo esc_attr( $key ); ?>"<?php echo ( '' === $hidden ) ? '' : ' style="display:none;"'; ?>>

                <!-- Manual Transfer card -->
                <p class="cwwlite-pay-method-label"><?php esc_html_e( 'How would you like to pay?', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></p>
                <div class="cwwlite-method-cards">
                    <div class="cwwlite-method-card cwwlite-method-card-active">
                        <span class="cwwlite-method-icon">📋</span>
                        <strong><?php esc_html_e( 'Manual Transfer', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></strong>
                        <span class="cwwlite-method-sub"><?php esc_html_e( 'Copy address &amp; send', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></span>
                    </div>
                </div>

                <!-- Wallet address -->
                <div class="cwwlite-address-box">
                    <div class="cwwlite-address-row">
                        <span class="cwwlite-address-label"><?php esc_html_e( 'Send to:', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></span>
                        <?php if ( $amount ) : ?>
                        <span class="cwwlite-amount-badge">
                            <?php echo esc_html( $amount . ' ' . $cfg['symbol'] ); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="cwwlite-address-display">
                        <code class="cwwlite-wallet-address"><?php echo esc_html( $wallet ); ?></code>
                        <button type="button"
                                class="cwwlite-copy-btn"
                                data-copy="<?php echo esc_attr( $wallet ); ?>"
                                aria-label="<?php esc_attr_e( 'Copy address', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <?php esc_html_e( 'Copy', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                        </button>
                    </div>
                    <?php if ( $this->exchange_rate_note ) : ?>
                        <p class="cwwlite-rate-note"><?php echo esc_html( $this->exchange_rate_note ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- TX ID field -->
                <div class="cwwlite-txid-wrap">
                    <label for="cwwlite_txid_<?php echo esc_attr( $key ); ?>" class="cwwlite-txid-label">
                        <?php esc_html_e( 'Transaction ID / Hash', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
                        <span class="cwwlite-optional"><?php esc_html_e( '(optional)', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></span>
                    </label>
                    <input type="text"
                           id="cwwlite_txid_<?php echo esc_attr( $key ); ?>"
                           name="cwwlite_txid"
                           class="cwwlite-txid-input"
                           placeholder="<?php esc_attr_e( 'Paste your TX hash here — you can also submit later', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>"
                           autocomplete="off" />
                </div>

            </div><!-- .cwwlite-network-panel -->
            <?php endforeach; ?>

        </div><!-- .cwwlite-checkout-wrap -->

        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validate fields
    // ─────────────────────────────────────────────────────────────────────────

    public function validate_fields(): bool {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce
        $network = isset( $_POST['cwwlite_network'] ) ? sanitize_key( wp_unslash( $_POST['cwwlite_network'] ) ) : '';
        $txid    = isset( $_POST['cwwlite_txid'] )    ? sanitize_text_field( wp_unslash( $_POST['cwwlite_txid'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( empty( $network ) || ! array_key_exists( $network, self::$networks ) ) {
            wc_add_notice( __( 'Please select a cryptocurrency network.', 'crypto-wallet-payment-for-woocommerce-lite' ), 'error' );
            return false;
        }

        $enabled = $this->get_enabled_networks();
        if ( ! array_key_exists( $network, $enabled ) ) {
            wc_add_notice( __( 'The selected network is not available. Please choose another.', 'crypto-wallet-payment-for-woocommerce-lite' ), 'error' );
            return false;
        }

        // TX ID is optional but if supplied do a basic sanity check.
        if ( ! empty( $txid ) ) {
            if ( strlen( $txid ) < 10 || strlen( $txid ) > 200 ) {
                wc_add_notice( __( 'The transaction ID looks invalid. Please double-check it.', 'crypto-wallet-payment-for-woocommerce-lite' ), 'error' );
                return false;
            }
            if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $txid ) ) {
                wc_add_notice( __( 'Transaction ID should only contain letters and numbers.', 'crypto-wallet-payment-for-woocommerce-lite' ), 'error' );
                return false;
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Process payment
    // ─────────────────────────────────────────────────────────────────────────

    public function process_payment( $order_id ): array {
        $order   = wc_get_order( $order_id );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce checkout
        $network = sanitize_key( wp_unslash( $_POST['cwwlite_network'] ?? '' ) );
        $txid    = sanitize_text_field( wp_unslash( $_POST['cwwlite_txid'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $cfg = self::$networks[ $network ] ?? null;
        if ( ! $cfg ) {
            wc_add_notice( __( 'Invalid network selected.', 'crypto-wallet-payment-for-woocommerce-lite' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $wallet  = $this->get_option( 'wallet_' . $network );
        $amounts = $this->get_crypto_raw_amounts( [ $network => $cfg ] );
        $crypto_amount = $amounts[ $network ] ?? '';

        // Store order meta
        $order->update_meta_data( '_cwwlite_network',       $network );
        $order->update_meta_data( '_cwwlite_network_label', $cfg['label'] );
        $order->update_meta_data( '_cwwlite_symbol',        $cfg['symbol'] );
        $order->update_meta_data( '_cwwlite_wallet',        $wallet );
        $order->update_meta_data( '_cwwlite_crypto_amount', $crypto_amount );
        if ( ! empty( $txid ) ) {
            $order->update_meta_data( '_cwwlite_txid', $txid );
        }
        $order->save();

        // Place on hold
        $hold_note = $this->hold_message ?: __( 'Order on hold pending crypto payment verification.', 'crypto-wallet-payment-for-woocommerce-lite' );
        $order->update_status( 'on-hold', $hold_note );

        // Reduce stock
        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Thank-you page
    // ─────────────────────────────────────────────────────────────────────────

    public function thankyou_page( $order_id ) {
        $order   = wc_get_order( $order_id );
        if ( ! $order ) return;

        $network = $order->get_meta( '_cwwlite_network' );
        $label   = $order->get_meta( '_cwwlite_network_label' );
        $symbol  = $order->get_meta( '_cwwlite_symbol' );
        $wallet  = $order->get_meta( '_cwwlite_wallet' );
        $amount  = $order->get_meta( '_cwwlite_crypto_amount' );
        $txid    = $order->get_meta( '_cwwlite_txid' );
        $cfg     = self::$networks[ $network ] ?? null;
        ?>
        <div class="cwwlite-thankyou">
            <h3><?php esc_html_e( 'Complete Your Crypto Payment', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></h3>

            <?php if ( $this->instructions ) : ?>
                <p><?php echo wp_kses_post( $this->instructions ); ?></p>
            <?php endif; ?>

            <table class="cwwlite-payment-summary">
                <tr>
                    <th><?php esc_html_e( 'Network', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th>
                    <td><?php echo esc_html( $label ); ?></td>
                </tr>
                <?php if ( $amount ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Amount to Send', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th>
                    <td><strong><?php echo esc_html( $amount . ' ' . $symbol ); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Send To Address', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th>
                    <td><code><?php echo esc_html( $wallet ); ?></code></td>
                </tr>
                <?php if ( $txid ) : ?>
                <tr>
                    <th><?php esc_html_e( 'TX ID Submitted', 'crypto-wallet-payment-for-woocommerce-lite' ); ?></th>
                    <td>
                        <?php if ( $cfg && ! empty( $cfg['explorer'] ) ) : ?>
                            <a href="<?php echo esc_url( ryanplugins_cwwlite_explorer_url( $cfg['explorer'], $txid ) ); ?>" target="_blank">
                                <?php echo esc_html( $txid ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $txid ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <p class="cwwlite-awaiting-notice">
                <?php esc_html_e( 'Once you have sent the payment, paste your transaction ID on the order page if you have not already. We will manually verify and update your order status.', 'crypto-wallet-payment-for-woocommerce-lite' ); ?>
            </p>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Email instructions
    // ─────────────────────────────────────────────────────────────────────────

    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $order->get_payment_method() !== $this->id ) return;
        if ( ! in_array( $order->get_status(), [ 'on-hold', 'pending' ], true ) ) return;

        $wallet = $order->get_meta( '_cwwlite_wallet' );
        $amount = $order->get_meta( '_cwwlite_crypto_amount' );
        $symbol = $order->get_meta( '_cwwlite_symbol' );
        $label  = $order->get_meta( '_cwwlite_network_label' );

        if ( $plain_text ) {
            echo "\n" . esc_html( $this->instructions ) . "\n\n";
            echo esc_html( $label ) . "\n";
            if ( $amount ) echo esc_html( $amount . ' ' . $symbol ) . "\n";
            echo esc_html( $wallet ) . "\n";
        } else {
            echo '<h3>' . esc_html__( 'Crypto Payment Instructions', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</h3>';
            echo '<p>' . wp_kses_post( $this->instructions ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Network:', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</strong> ' . esc_html( $label ) . '</p>';
            if ( $amount ) echo '<p><strong>' . esc_html__( 'Amount:', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</strong> ' . esc_html( $amount . ' ' . $symbol ) . '</p>';
            echo '<p><strong>' . esc_html__( 'Send to:', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</strong> <code>' . esc_html( $wallet ) . '</code></p>';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: live rates
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_get_rates() {
        check_ajax_referer( 'ryanplugins_cwwlite_checkout', 'nonce' );
        wp_send_json_success( $this->get_live_rates() );
    }

    public function get_live_rates(): array {
        $currency = strtolower( get_woocommerce_currency() );
        $cache_key = 'ryanplugins_cwwlite_live_rates_' . $currency;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $ids = 'bitcoin,ethereum,solana,ripple';
        $url = "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies={$currency}";

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) return [];

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) return [];

        $map = [
            'bitcoin'  => $body['bitcoin'][ $currency ]  ?? null,
            'ethereum' => $body['ethereum'][ $currency ]  ?? null,
            'solana'   => $body['solana'][ $currency ]    ?? null,
            'xrp'      => $body['ripple'][ $currency ]    ?? null,
        ];
        $map = array_filter( $map );

        set_transient( $cache_key, $map, 5 * MINUTE_IN_SECONDS );
        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: order status (customer polling after checkout)
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_order_status() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public order status poll, no sensitive data
        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) wp_send_json_error( 'invalid' );

        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_send_json_error( 'not_found' );

        wp_send_json_success( [ 'status' => $order->get_status() ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Asset enqueueing
    // ─────────────────────────────────────────────────────────────────────────

    public function enqueue_checkout_assets() {
        if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) return;
        if ( 'yes' !== $this->get_option( 'enabled' ) ) return;

        wp_enqueue_style(
            'ryanplugins-cwwlite-checkout',
            RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            RyanPlugins_CWWLITE_VERSION
        );

        wp_enqueue_script(
            'ryanplugins-cwwlite-checkout',
            RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery' ],
            RyanPlugins_CWWLITE_VERSION,
            true
        );

        $enabled   = $this->get_enabled_networks();
        $amounts   = $this->get_crypto_display_amounts( $enabled );
        $rate_lock = absint( $this->get_option( 'rate_lock_minutes', 15 ) );

        wp_localize_script( 'ryanplugins-cwwlite-checkout', 'cwwliteData', [
            'gatewayId'   => $this->id,
            'nonce'       => wp_create_nonce( 'ryanplugins_cwwlite_checkout' ),
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'networks'    => $enabled,
            'amounts'     => $amounts,
            'currency'    => get_woocommerce_currency(),
            'rateLockSec' => $rate_lock * 60,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function get_enabled_networks(): array {
        $out = [];
        foreach ( self::$networks as $key => $cfg ) {
            $wallet = $this->get_option( 'wallet_' . $key );
            if ( ! empty( $wallet ) ) {
                $out[ $key ] = $cfg;
            }
        }
        return $out;
    }

    private function get_cart_total(): float {
        if ( is_wc_endpoint_url( 'order-pay' ) ) {
            global $wp;
            $order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
            return $order ? (float) $order->get_total() : 0.0;
        }
        return WC()->cart ? (float) WC()->cart->get_total( 'raw' ) : 0.0;
    }

    public function get_crypto_display_amounts( array $networks ): array {
        $total = $this->get_cart_total();
        $rates = $this->get_live_rates();
        $out   = [];

        foreach ( $networks as $key => $cfg ) {
            if ( ! isset( $rates[ $key ] ) || $rates[ $key ] <= 0 ) {
                $out[ $key ] = '';
                continue;
            }
            $crypto        = $total / $rates[ $key ];
            $out[ $key ]   = rtrim( rtrim( number_format( $crypto, 8, '.', '' ), '0' ), '.' );
        }
        return $out;
    }

    private function get_crypto_raw_amounts( array $networks ): array {
        $total = $this->get_cart_total();
        $rates = $this->get_live_rates();
        $out   = [];

        foreach ( $networks as $key => $cfg ) {
            if ( ! isset( $rates[ $key ] ) || $rates[ $key ] <= 0 ) {
                $out[ $key ] = '';
                continue;
            }
            $out[ $key ] = number_format( $total / $rates[ $key ], 8, '.', '' );
        }
        return $out;
    }
}
