<?php
/**
 * Plugin Name: Crypto Wallet Payment for WooCommerce
 * Plugin URI:  https://ryanplugins.net
 * Description: Accept BTC, ETH, SOL and XRP payments in WooCommerce. Manual TX verification, live exchange rates via CoinGecko.
 * Version:     1.0.0
 * Author:      RyanPlugins
 * Author URI:  https://profiles.wordpress.org/ryanplugins/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crypto-wallet-payment-for-woocommerce-lite
 * Domain Path: /languages
 *
 * @package CWWLITE
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.6
 */

defined( 'ABSPATH' ) || exit;

define( 'CWWLITE_VERSION', '1.0.0' );
define( 'CWWLITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CWWLITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
		foreach ( array( 'usd', 'eur', 'gbp', 'php', 'jpy', 'aud', 'cad' ) as $cur ) {
			delete_transient( 'cwwlite_live_rates_' . $cur );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

// ── WASM MIME fix (needed for MetaMask / EVM wallets in future) ───────────────

add_filter(
	'wp_check_filetype_and_ext',
	/**
	 * Allow .wasm MIME type uploads.
	 *
	 * @param array  $data     File data array.
	 * @param string $file     Full path to the file.
	 * @param string $filename Filename.
	 * @param array  $mimes    Allowed mime types.
	 * @return array
	 */
	function ( $data, $file, $filename, $mimes ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP filter requires 4 params.
		if ( substr( $filename, -5 ) === '.wasm' ) {
			$data['ext']  = 'wasm';
			$data['type'] = 'application/wasm';
		}
		return $data;
	},
	10,
	4
);

// ── Main init ────────────────────────────────────────────────────────────────

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function cwwlite_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cwwlite_missing_wc_notice' );
		return;
	}

	require_once CWWLITE_PLUGIN_DIR . 'includes/class-cwwlite-gateway.php';
	require_once CWWLITE_PLUGIN_DIR . 'includes/class-cwwlite-admin.php';
	require_once CWWLITE_PLUGIN_DIR . 'includes/class-cwwlite-order-handler.php';

	add_filter( 'woocommerce_payment_gateways', 'cwwlite_register_gateway' );

	new CWWLITE_Admin();
	new CWWLITE_Order_Handler();
}
add_action( 'plugins_loaded', 'cwwlite_init' );

// ── WooCommerce Blocks support ────────────────────────────────────────────────

add_action(
	'plugins_loaded',
	function () {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			'cwwlite_register_blocks_payment_method'
		);
	},
	5
);

/**
 * Register the Blocks payment method integration.
 *
 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Registry instance.
 */
function cwwlite_register_blocks_payment_method( $registry ): void {
	if ( defined( 'CWWLITE_BLOCKS_REGISTERED' ) ) {
		return;
	}
	define( 'CWWLITE_BLOCKS_REGISTERED', true );

	if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
		return;
	}

	if ( ! class_exists( 'CWWLITE_Blocks' ) ) {
		require_once CWWLITE_PLUGIN_DIR . 'includes/class-cwwlite-blocks.php';
	}

	$registry->register( new CWWLITE_Blocks() );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a block-explorer URL for a given TX hash.
 *
 * @param string $base Explorer base URL.
 * @param string $txid Transaction hash.
 * @return string
 */
function cwwlite_explorer_url( string $base, string $txid ): string {
	if ( empty( $base ) || empty( $txid ) ) {
		return '';
	}
	if ( strpos( $base, '{txid}' ) !== false ) {
		return str_replace( '{txid}', rawurlencode( $txid ), $base );
	}
	return $base . $txid;
}

/**
 * Register the crypto gateway with WooCommerce.
 *
 * @param array $gateways Existing gateways.
 * @return array
 */
function cwwlite_register_gateway( $gateways ) {
	$gateways[] = 'CWWLITE_Gateway';
	return $gateways;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cwwlite_action_links' );
/**
 * Add a Settings link on the Plugins list page.
 *
 * @param array $links Existing action links.
 * @return array
 */
function cwwlite_action_links( $links ) {
	$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cwwlite_crypto' );
	array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'crypto-wallet-payment-for-woocommerce-lite' ) . '</a>' );
	return $links;
}

/**
 * Show admin notice when WooCommerce is not active.
 */
function cwwlite_missing_wc_notice() {
	echo '<div class="error"><p><strong>Crypto Wallet Payment (Lite)</strong> requires WooCommerce to be installed and active.</p></div>';
}

// ── HPOS / Blocks compatibility declarations ──────────────────────────────────

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			$f = \Automattic\WooCommerce\Utilities\FeaturesUtil::class;
			$f::declare_compatibility( 'custom_order_tables', __FILE__, true );
			$f::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			$f::declare_compatibility( 'cache_product_objects', __FILE__, true );
			$f::declare_compatibility( 'product_block_editor', __FILE__, true );
		}
	}
);
