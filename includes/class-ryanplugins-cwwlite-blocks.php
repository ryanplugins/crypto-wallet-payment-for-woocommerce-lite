<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * RyanPlugins_CWWLITE_Blocks
 *
 * WooCommerce Block Checkout integration for the Lite gateway.
 */
class RyanPlugins_CWWLITE_Blocks extends AbstractPaymentMethodType {

    protected $name = 'ryanplugins_cwwlite_crypto';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_ryanplugins_cwwlite_crypto_settings', [] );
    }

    public function is_active(): bool {
        return ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] === 'yes';
    }

    public function get_payment_method_script_handles(): array {
        $handle = 'ryanplugins-cwwlite-blocks';

        // Enqueue checkout CSS for the block checkout page
        wp_enqueue_style(
            'ryanplugins-cwwlite-checkout',
            RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            RyanPlugins_CWWLITE_VERSION
        );

        wp_register_script(
            $handle,
            RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/js/blocks-lite.js',
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n' ],
            RyanPlugins_CWWLITE_VERSION,
            true
        );
        return [ $handle ];
    }

    public function get_payment_method_data(): array {
        $gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ] ?? null;

        $networks  = $gateway ? $gateway->get_enabled_networks() : [];
        $amounts   = ( $gateway && ! empty( $networks ) ) ? $gateway->get_crypto_display_amounts( $networks ) : [];
        $rate_lock = absint( $this->settings['rate_lock_minutes'] ?? 15 );

        // Build wallet map keyed by network
        $wallets = [];
        foreach ( array_keys( $networks ) as $key ) {
            $wallets[ $key ] = $gateway ? $gateway->get_option( 'wallet_' . $key ) : '';
        }

        return [
            'title'              => $this->settings['title']              ?? __( 'Pay with Crypto (Lite)', 'crypto-wallet-payment-for-woocommerce-lite' ),
            'description'        => $this->settings['description']        ?? '',
            'exchange_rate_note' => $this->settings['exchange_rate_note'] ?? '',
            'networks'           => $networks,
            'wallets'            => $wallets,
            'amounts'            => $amounts,
            'rateLockSec'        => $rate_lock * 60,
            'imgUrl'             => RyanPlugins_CWWLITE_PLUGIN_URL . 'assets/img/',
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'ryanplugins_cwwlite_checkout' ),
            'supports'           => [ 'products' ],
        ];
    }

    public function get_payment_method_script_handles_for_admin(): array {
        return $this->get_payment_method_script_handles();
    }
}
