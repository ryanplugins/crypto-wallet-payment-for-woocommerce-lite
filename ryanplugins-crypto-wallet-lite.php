<?php
/**
 * Plugin Name: Crypto Wallet Payment for WooCommerce — Lite
 * Plugin URI:  https://ryanplugins.net/product/non-custodial-crypto-payment-gateway-for-woocommerce-direct-wallet-payments/
 * Description: Accept BTC, ETH, SOL and XRP payments in WooCommerce. Manual TX verification, live exchange rates via CoinGecko. Lite version — no auto-verification, no stablecoins, no browser wallet auto-send.
 * Version:     1.0.0
 * Author:      RyanPlugins
 * Author URI:  https://ryanplugins.net
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crypto-wallet-payment-for-woocommerce-lite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.6
 */

defined( 'ABSPATH' ) || exit;

define( 'RyanPlugins_CWWLITE_VERSION',    '1.0.0' );
define( 'RyanPlugins_CWWLITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RyanPlugins_CWWLITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook( __FILE__, function () {
    flush_rewrite_rules();
    foreach ( [ 'usd', 'eur', 'gbp', 'php', 'jpy', 'aud', 'cad' ] as $cur ) {
        delete_transient( 'ryanplugins_cwwlite_live_rates_' . $cur );
    }
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

// ── WASM MIME fix (needed for MetaMask / EVM wallets in future) ───────────────

add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {
    if ( substr( $filename, -5 ) === '.wasm' ) {
        $data['ext']  = 'wasm';
        $data['type'] = 'application/wasm';
    }
    return $data;
}, 10, 4 );

// ── Main init ────────────────────────────────────────────────────────────────

function ryanplugins_cwwlite_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'ryanplugins_cwwlite_missing_wc_notice' );
        return;
    }

    require_once RyanPlugins_CWWLITE_PLUGIN_DIR . 'includes/class-ryanplugins-cwwlite-gateway.php';
    require_once RyanPlugins_CWWLITE_PLUGIN_DIR . 'includes/class-ryanplugins-cwwlite-admin.php';
    require_once RyanPlugins_CWWLITE_PLUGIN_DIR . 'includes/class-ryanplugins-cwwlite-order-handler.php';

    add_filter( 'woocommerce_payment_gateways', 'ryanplugins_cwwlite_register_gateway' );

    new RyanPlugins_CWWLITE_Admin();
    new RyanPlugins_CWWLITE_Order_Handler();
}
add_action( 'plugins_loaded', 'ryanplugins_cwwlite_init' );

// ── WooCommerce Blocks support ────────────────────────────────────────────────

add_action( 'plugins_loaded', function () {
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        'ryanplugins_cwwlite_register_blocks_payment_method'
    );
}, 5 );

function ryanplugins_cwwlite_register_blocks_payment_method( $registry ): void {
    if ( defined( 'RYANPLUGINS_CWWLITE_BLOCKS_REGISTERED' ) ) return;
    define( 'RYANPLUGINS_CWWLITE_BLOCKS_REGISTERED', true );

    if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) return;

    if ( ! class_exists( 'RyanPlugins_CWWLITE_Blocks' ) ) {
        require_once RyanPlugins_CWWLITE_PLUGIN_DIR . 'includes/class-ryanplugins-cwwlite-blocks.php';
    }

    $registry->register( new RyanPlugins_CWWLITE_Blocks() );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a block-explorer URL for a given TX hash.
 *
 * @param string $base  Explorer base URL.
 * @param string $txid  Transaction hash.
 * @return string
 */
function ryanplugins_cwwlite_explorer_url( string $base, string $txid ): string {
    if ( empty( $base ) || empty( $txid ) ) return '';
    if ( strpos( $base, '{txid}' ) !== false ) {
        return str_replace( '{txid}', rawurlencode( $txid ), $base );
    }
    return $base . $txid;
}

function ryanplugins_cwwlite_register_gateway( $gateways ) {
    $gateways[] = 'RyanPlugins_CWWLITE_Gateway';
    return $gateways;
}

function ryanplugins_cwwlite_missing_wc_notice() {
    echo '<div class="error"><p><strong>Crypto Wallet Payment (Lite)</strong> requires WooCommerce to be installed and active.</p></div>';
}

// ── HPOS / Blocks compatibility declarations ──────────────────────────────────

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        $f = \Automattic\WooCommerce\Utilities\FeaturesUtil::class;
        $f::declare_compatibility( 'custom_order_tables',  __FILE__, true );
        $f::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        $f::declare_compatibility( 'cache_product_objects', __FILE__, true );
        $f::declare_compatibility( 'product_block_editor',  __FILE__, true );
    }
} );
