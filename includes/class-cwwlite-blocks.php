<?php
/**
 * WooCommerce Blocks integration for the Crypto Wallet Lite payment method.
 *
 * @package CWWLITE
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * CWWLITE_Blocks
 *
 * WooCommerce Block Checkout integration for the Lite gateway.
 */
class CWWLITE_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'cwwlite_crypto';

	/**
	 * Initialize the Blocks integration by loading settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_cwwlite_crypto_settings', array() );
	}

	/**
	 * Check if the payment method is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Return script handles for the payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		$handle = 'cwwlite-blocks';

		// Enqueue checkout CSS for the block checkout page.
		wp_enqueue_style(
			'cwwlite-checkout',
			CWWLITE_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			CWWLITE_VERSION
		);

		wp_register_script(
			$handle,
			CWWLITE_PLUGIN_URL . 'assets/js/blocks-lite.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n' ),
			CWWLITE_VERSION,
			true
		);
		return array( $handle );
	}

	/**
	 * Return data passed to the JS payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		$gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ] ?? null;

		$networks  = $gateway ? $gateway->get_enabled_networks() : array();
		$amounts   = ( $gateway && ! empty( $networks ) ) ? $gateway->get_crypto_display_amounts( $networks ) : array();
		$rate_lock = absint( $this->settings['rate_lock_minutes'] ?? 15 );

		// Build wallet map keyed by network.
		$wallets = array();
		foreach ( array_keys( $networks ) as $key ) {
			$wallets[ $key ] = $gateway ? $gateway->get_option( 'wallet_' . $key ) : '';
		}

		return array(
			'title'              => $this->settings['title'] ?? __( 'Pay with Crypto (Lite)', 'crypto-wallet-payment-for-woocommerce-lite' ),
			'description'        => $this->settings['description'] ?? '',
			'exchange_rate_note' => $this->settings['exchange_rate_note'] ?? '',
			'networks'           => $networks,
			'wallets'            => $wallets,
			'amounts'            => $amounts,
			'rateLockSec'        => $rate_lock * 60,
			'imgUrl'             => CWWLITE_PLUGIN_URL . 'assets/img/',
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'cwwlite_checkout' ),
			'supports'           => array( 'products' ),
		);
	}

	/**
	 * Return script handles for admin context.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin(): array {
		return $this->get_payment_method_script_handles();
	}
}
