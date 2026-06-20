<?php
/**
 * WooCommerce payment gateway: Crypto Wallet Payment.
 *
 * @package CWWLITE
 */

defined( 'ABSPATH' ) || exit;

/**
 * CWWLITE_Gateway
 *
 * WooCommerce payment gateway for crypto.
 *
 * Supported networks: Bitcoin (BTC), Ethereum (ETH), Solana (SOL), XRP.
 * — No stablecoins, no EVM L2s, no Cardano browser-wallet auto-send.
 * — Manual TX submission only; admin verifies orders by hand.
 * — Live exchange rates via CoinGecko (no API key needed).
 */
class CWWLITE_Gateway extends WC_Payment_Gateway {

	/**
	 * Payment instructions shown to the customer.
	 *
	 * @var string
	 */
	public string $instructions = '';
	/**
	 * Exchange rate note appended to instructions.
	 *
	 * @var string
	 */
	public string $exchange_rate_note = '';
	/**
	 * Custom hold status message.
	 *
	 * @var string
	 */
	public string $hold_message = '';

	// ─────────────────────────────────────────────────────────────────────────
	// Supported networks.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Supported crypto network configurations.
	 *
	 * @var array
	 */
	public static array $networks = array(
		'bitcoin'  => array(
			'label'            => 'Bitcoin (BTC)',
			'symbol'           => 'BTC',
			'coingecko'        => 'bitcoin',
			'icon'             => 'bitcoin',
			'explorer'         => 'https://blockstream.info/tx/',
			'explorer_testnet' => 'https://blockstream.info/testnet/tx/',
			'addr_prefix'      => array( '1', '3', 'bc1' ),
			'addr_prefix_test' => array( 'm', 'n', 'tb1' ),
			'addr_len'         => array( 25, 62 ),
			'wallet_type'      => 'manual',
		),
		'ethereum' => array(
			'label'            => 'Ethereum (ETH)',
			'symbol'           => 'ETH',
			'coingecko'        => 'ethereum',
			'icon'             => 'ethereum',
			'explorer'         => 'https://etherscan.io/tx/',
			'explorer_testnet' => 'https://sepolia.etherscan.io/tx/',
			'addr_prefix'      => array( '0x' ),
			'addr_prefix_test' => array( '0x' ),
			'addr_len'         => array( 42, 42 ),
			'wallet_type'      => 'manual',
		),
		'solana'   => array(
			'label'            => 'Solana (SOL)',
			'symbol'           => 'SOL',
			'coingecko'        => 'solana',
			'icon'             => 'solana',
			'explorer'         => 'https://solscan.io/tx/',
			'explorer_testnet' => 'https://solscan.io/tx/{txid}?cluster=devnet',
			'addr_prefix'      => array(),
			'addr_prefix_test' => array(),
			'addr_len'         => array( 43, 44 ),
			'wallet_type'      => 'manual',
		),
		'xrp'      => array(
			'label'            => 'XRP (Ripple)',
			'symbol'           => 'XRP',
			'coingecko'        => 'ripple',
			'icon'             => 'xrp',
			'explorer'         => 'https://xrpscan.com/tx/',
			'explorer_testnet' => 'https://testnet.xrpscan.com/tx/',
			'addr_prefix'      => array( 'r' ),
			'addr_prefix_test' => array( 'r' ),
			'addr_len'         => array( 25, 34 ),
			'wallet_type'      => 'manual',
		),
	);

	// ─────────────────────────────────────────────────────────────────────────
	// Constructor.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Constructor. Set up gateway properties and register hooks.
	 */
	public function __construct() {
		$this->id                 = 'cwwlite_crypto';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'Crypto Wallet Payment', 'crypto-wallet-payment-for-woocommerce' );
		$this->method_description = __( 'Accept BTC, ETH, SOL and XRP. Manual verification — no API keys needed.', 'crypto-wallet-payment-for-woocommerce' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->exchange_rate_note = $this->get_option( 'exchange_rate_note' );
		$this->hold_message       = $this->get_option( 'hold_message' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_payment_fields' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// AJAX: live rates.
		add_action( 'wp_ajax_cwwlite_get_rates', array( $this, 'ajax_get_rates' ) );
		add_action( 'wp_ajax_nopriv_cwwlite_get_rates', array( $this, 'ajax_get_rates' ) );

		// AJAX: poll order status (customer polling).
		add_action( 'wp_ajax_cwwlite_order_status', array( $this, 'ajax_order_status' ) );
		add_action( 'wp_ajax_nopriv_cwwlite_order_status', array( $this, 'ajax_order_status' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Settings.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Initialise gateway settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(

			// ── General ──────────────────────────────────────────────────────
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'crypto-wallet-payment-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Crypto Wallet Payment', 'crypto-wallet-payment-for-woocommerce' ),
				'default' => 'yes',
			),
			'environment'        => array(
				'title'       => __( 'Environment', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Mainnet processes real transactions. Testnet is for development and testing only — no real funds are transferred.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => 'mainnet',
				'desc_tip'    => true,
				'options'     => array(
					'mainnet' => __( 'Mainnet (Live)', 'crypto-wallet-payment-for-woocommerce' ),
					'testnet' => __( 'Testnet (Testing)', 'crypto-wallet-payment-for-woocommerce' ),
				),
			),
			'title'              => array(
				'title'       => __( 'Payment Title', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Title shown at checkout.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => __( 'Pay with Crypto (BTC / ETH / SOL / XRP)', 'crypto-wallet-payment-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'   => __( 'Description', 'crypto-wallet-payment-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay with cryptocurrency. Your order is confirmed once we manually verify your transaction.', 'crypto-wallet-payment-for-woocommerce' ),
			),
			'instructions'       => array(
				'title'   => __( 'Thank You Page Instructions', 'crypto-wallet-payment-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Please send the exact crypto amount shown to the wallet address below. Paste your transaction ID and we will confirm your order within 24 hours.', 'crypto-wallet-payment-for-woocommerce' ),
			),
			'exchange_rate_note' => array(
				'title'    => __( 'Exchange Rate Note', 'crypto-wallet-payment-for-woocommerce' ),
				'type'     => 'text',
				'default'  => __( 'Crypto amount is approximate. Please send the displayed amount.', 'crypto-wallet-payment-for-woocommerce' ),
				'desc_tip' => true,
			),
			'hold_message'       => array(
				'title'   => __( 'On-Hold Order Message', 'crypto-wallet-payment-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Your order is on hold pending crypto payment verification. We will update your order once we confirm your transaction.', 'crypto-wallet-payment-for-woocommerce' ),
			),

			// ── Networks ─────────────────────────────────────────────────────
			'networks_section'   => array(
				'title'       => __( 'Wallet Addresses', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Enter your wallet addresses for each network you want to accept. Leave blank to hide a network at checkout.', 'crypto-wallet-payment-for-woocommerce' ),
			),
			'wallet_bitcoin'     => array(
				'title'       => __( 'Bitcoin (BTC) Address', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your mainnet BTC wallet address.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'wallet_ethereum'    => array(
				'title'       => __( 'Ethereum (ETH) Address', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your mainnet ETH wallet address (0x…).', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'wallet_solana'      => array(
				'title'       => __( 'Solana (SOL) Address', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your mainnet SOL wallet address.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'wallet_xrp'         => array(
				'title'       => __( 'XRP Address', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your mainnet XRP wallet address.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// ── Order status ──────────────────────────────────────────────────
			'verified_status'    => array(
				'title'       => __( 'Manually Verified Order Status', 'crypto-wallet-payment-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Order status to set when you manually mark a transaction as verified in the admin.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'     => 'processing',
				'desc_tip'    => true,
				'options'     => array(
					'processing' => __( 'Processing', 'crypto-wallet-payment-for-woocommerce' ),
					'completed'  => __( 'Completed', 'crypto-wallet-payment-for-woocommerce' ),
				),
			),

			// ── Rate Lock ─────────────────────────────────────────────────────
			'rate_lock_minutes'  => array(
				'title'             => __( 'Rate Valid For (minutes)', 'crypto-wallet-payment-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'How many minutes the quoted crypto amount stays locked. 0 disables the countdown timer.', 'crypto-wallet-payment-for-woocommerce' ),
				'default'           => '15',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '60',
					'step' => '1',
				),
			),

		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tabbed settings page (overrides WC default flat layout).
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Output the tabbed settings page (overrides WC default flat layout).
	 */
	public function admin_options() {
		// Compute missing-wallet badge count for the Networks tab.
		$missing_wallets = 0;
		foreach ( array_keys( self::$networks ) as $key ) {
			if ( empty( $this->get_option( 'wallet_' . $key ) ) ) {
				++$missing_wallets;
			}
		}
		$all_missing = ( count( self::$networks ) === $missing_wallets );
		$is_testnet  = $this->is_testnet();
		?>
		<div id="cwwlite-settings-wrap">
			<input type="hidden"
					name="cwwlite_active_tab"
					id="cwwlite-active-tab-input"
					value="general" />

			<!-- ── Tab nav ───────────────────────────────────────────────── -->
			<div class="cwwlite-nav-tabs" role="tablist">

				<button type="button"
						class="cwwlite-nav-tab cwwlite-nav-tab-active"
						data-tab="general"
						role="tab" aria-selected="true"
						aria-controls="cwwlite-panel-general">
					<?php esc_html_e( '⚙ General', 'crypto-wallet-payment-for-woocommerce' ); ?>
					<?php if ( $is_testnet ) : ?>
						<span class="cwwlite-tab-badge cwwlite-tab-badge-testnet"
							title="<?php esc_attr_e( 'Testnet mode active', 'crypto-wallet-payment-for-woocommerce' ); ?>">
							TEST
						</span>
					<?php endif; ?>
				</button>

				<button type="button"
						class="cwwlite-nav-tab"
						data-tab="networks"
						role="tab" aria-selected="false"
						aria-controls="cwwlite-panel-networks">
					<?php esc_html_e( '🔗 Networks & Wallets', 'crypto-wallet-payment-for-woocommerce' ); ?>
					<?php if ( $all_missing ) : ?>
						<span class="cwwlite-tab-badge cwwlite-tab-badge-warn"
							title="<?php esc_attr_e( 'No wallets configured', 'crypto-wallet-payment-for-woocommerce' ); ?>">!</span>
					<?php elseif ( $missing_wallets > 0 ) : ?>
						<span class="cwwlite-tab-badge cwwlite-tab-badge-warn"
							title="<?php /* translators: %d: number of crypto networks without a wallet address configured */ echo esc_attr( sprintf( __( '%d network(s) not set', 'crypto-wallet-payment-for-woocommerce' ), $missing_wallets ) ); ?>">
							<?php echo esc_html( $missing_wallets ); ?>
						</span>
					<?php endif; ?>
				</button>

			</div><!-- .cwwlite-nav-tabs -->

			<!-- ── Tab: General ──────────────────────────────────────────── -->
			<div class="cwwlite-tab-panel cwwlite-tab-panel-active"
				id="cwwlite-panel-general"
				role="tabpanel">

				<?php if ( $is_testnet ) : ?>
				<div class="cwwlite-testnet-admin-banner">
					<span class="cwwlite-testnet-icon">🧪</span>
					<strong><?php esc_html_e( 'Testnet Mode Active', 'crypto-wallet-payment-for-woocommerce' ); ?></strong>
					— <?php esc_html_e( 'No real funds will be transferred. Switch to Mainnet before going live.', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</div>
				<?php endif; ?>

				<div class="cwwlite-section-heading">
					<?php esc_html_e( 'GENERAL SETTINGS', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</div>
				<table class="form-table">
				<?php
				$saved             = $this->form_fields;
				$this->form_fields = array_intersect_key( $saved, array_flip( array( 'enabled', 'environment', 'title', 'description', 'instructions', 'exchange_rate_note', 'hold_message' ) ) );
				$this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->form_fields = $saved;
				?>
				</table>

				<div class="cwwlite-section-heading" style="margin-top:20px;">
					<?php esc_html_e( 'ORDER SETTINGS', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</div>
				<table class="form-table">
				<?php
				$saved             = $this->form_fields;
				$this->form_fields = array_intersect_key( $saved, array_flip( array( 'verified_status', 'rate_lock_minutes' ) ) );
				$this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->form_fields = $saved;
				?>
				</table>

			</div><!-- #cwwlite-panel-general -->

			<!-- ── Tab: Networks & Wallets ───────────────────────────────── -->
			<div class="cwwlite-tab-panel"
				id="cwwlite-panel-networks"
				role="tabpanel" hidden>

				<div class="cwwlite-section-heading">
					<?php esc_html_e( 'WALLET ADDRESSES', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</div>
				<p class="description" style="margin-bottom:16px;">
					<?php esc_html_e( 'Enter your wallet address for each network you want to accept. Leave blank to hide that network at checkout.', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</p>
				<table class="form-table">
				<?php
				$saved             = $this->form_fields;
				$this->form_fields = array_intersect_key( $saved, array_flip( array( 'wallet_bitcoin', 'wallet_ethereum', 'wallet_solana', 'wallet_xrp' ) ) );
				$this->generate_settings_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->form_fields = $saved;
				?>
				</table>

			</div><!-- #cwwlite-panel-networks -->

			<!-- ── Save button — rendered inside WC's outer form ────────── -->
			<p class="cwwlite-submit" id="cwwlite-save-btn-row">
				<button type="submit" name="save" value="Save changes" class="button-primary">
					<?php esc_html_e( 'Save changes', 'crypto-wallet-payment-for-woocommerce' ); ?>
				</button>
			</p>

		</div><!-- #cwwlite-settings-wrap -->
		<?php
	}

	/**
	 * Process admin settings save and clear rate cache.
	 */
	public function process_payment_fields() {
		$this->process_admin_options();
		// Bust rate cache on save.
		foreach ( array( 'usd', 'eur', 'gbp', 'php', 'jpy', 'aud', 'cad' ) as $cur ) {
			delete_transient( 'cwwlite_live_rates_' . $cur );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Checkout: payment fields.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Output the checkout payment fields.
	 */
	public function payment_fields() {
		$networks  = $this->get_enabled_networks();
		$amounts   = $this->get_crypto_display_amounts( $networks );
		$rate_lock = absint( $this->get_option( 'rate_lock_minutes', 15 ) );
		$img_url   = CWWLITE_PLUGIN_URL . 'assets/img/';

		if ( empty( $networks ) ) {
			echo '<p class="cwwlite-no-networks">' . esc_html__( 'No crypto networks are configured yet. Please contact the store owner.', 'crypto-wallet-payment-for-woocommerce' ) . '</p>';
			return;
		}

		$first_key = array_key_first( $networks );
		?>

		<div class="cwwlite-checkout-wrap">

			<?php if ( $this->is_testnet() ) : ?>
			<div class="cwwlite-testnet-checkout-banner">
				<span>🧪</span>
				<strong><?php esc_html_e( 'Testnet Mode', 'crypto-wallet-payment-for-woocommerce' ); ?></strong>
				— <?php esc_html_e( 'This is a test environment. Do not send real funds.', 'crypto-wallet-payment-for-woocommerce' ); ?>
			</div>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<p class="cwwlite-description"><?php echo wp_kses_post( $this->description ); ?></p>
			<?php endif; ?>

			<!-- ── Network pill selector ─────────────────────────────────── -->
			<p class="cwwlite-select-label"><?php esc_html_e( 'Select network:', 'crypto-wallet-payment-for-woocommerce' ); ?></p>

			<div class="cwwlite-pill-selector" role="group" aria-label="<?php esc_attr_e( 'Select cryptocurrency', 'crypto-wallet-payment-for-woocommerce' ); ?>">
				<?php
				foreach ( $networks as $key => $cfg ) :
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
				<?php esc_html_e( 'Rate valid for:', 'crypto-wallet-payment-for-woocommerce' ); ?>
				<span class="cwwlite-countdown cwwlite-countdown-highlight"
						data-seconds="<?php echo esc_attr( $rate_lock * 60 ); ?>">
					<?php echo esc_html( sprintf( '%d:00', $rate_lock ) ); ?>
				</span>
			</div>
			<?php endif; ?>

			<!-- ── Per-network detail panels ─────────────────────────────── -->
			<?php
			foreach ( $networks as $key => $cfg ) :
				$wallet = $this->get_option( 'wallet_' . $key );
				$amount = $amounts[ $key ] ?? '';
				?>
			<div class="cwwlite-network-panel" id="cwwlite-panel-<?php echo esc_attr( $key ); ?>"<?php echo ( $key !== $first_key ) ? ' style="display:none;"' : ''; ?>>

				<!-- Manual Transfer card -->
				<p class="cwwlite-pay-method-label"><?php esc_html_e( 'How would you like to pay?', 'crypto-wallet-payment-for-woocommerce' ); ?></p>
				<div class="cwwlite-method-cards">
					<div class="cwwlite-method-card cwwlite-method-card-active">
						<span class="cwwlite-method-icon">📋</span>
						<strong><?php esc_html_e( 'Manual Transfer', 'crypto-wallet-payment-for-woocommerce' ); ?></strong>
						<span class="cwwlite-method-sub"><?php esc_html_e( 'Copy address &amp; send', 'crypto-wallet-payment-for-woocommerce' ); ?></span>
					</div>
				</div>

				<!-- Wallet address -->
				<div class="cwwlite-address-box">
					<div class="cwwlite-address-row">
						<span class="cwwlite-address-label"><?php esc_html_e( 'Send to:', 'crypto-wallet-payment-for-woocommerce' ); ?></span>
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
								aria-label="<?php esc_attr_e( 'Copy address', 'crypto-wallet-payment-for-woocommerce' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
							<?php esc_html_e( 'Copy', 'crypto-wallet-payment-for-woocommerce' ); ?>
						</button>
					</div>
					<?php if ( $this->exchange_rate_note ) : ?>
						<p class="cwwlite-rate-note"><?php echo esc_html( $this->exchange_rate_note ); ?></p>
					<?php endif; ?>
				</div>

				<!-- TX ID field -->
				<div class="cwwlite-txid-wrap">
					<label for="cwwlite_txid_<?php echo esc_attr( $key ); ?>" class="cwwlite-txid-label">
						<?php esc_html_e( 'Transaction ID / Hash', 'crypto-wallet-payment-for-woocommerce' ); ?>
						<span class="cwwlite-optional"><?php esc_html_e( '(optional)', 'crypto-wallet-payment-for-woocommerce' ); ?></span>
					</label>
					<input type="text"
							id="cwwlite_txid_<?php echo esc_attr( $key ); ?>"
							name="cwwlite_txid"
							class="cwwlite-txid-input"
							placeholder="<?php esc_attr_e( 'Paste your TX hash here — you can also submit later', 'crypto-wallet-payment-for-woocommerce' ); ?>"
							autocomplete="off" />
				</div>

			</div><!-- .cwwlite-network-panel -->
			<?php endforeach; ?>

		</div><!-- .cwwlite-checkout-wrap -->

		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Validate fields.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Validate the payment fields before processing.
	 *
	 * @return bool
	 */
	public function validate_fields(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce.
		$network = isset( $_POST['cwwlite_network'] ) ? sanitize_key( wp_unslash( $_POST['cwwlite_network'] ) ) : '';
		$txid    = isset( $_POST['cwwlite_txid'] ) ? sanitize_text_field( wp_unslash( $_POST['cwwlite_txid'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $network ) || ! array_key_exists( $network, self::$networks ) ) {
			wc_add_notice( __( 'Please select a cryptocurrency network.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
			return false;
		}

		$enabled = $this->get_enabled_networks();
		if ( ! array_key_exists( $network, $enabled ) ) {
			wc_add_notice( __( 'The selected network is not available. Please choose another.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
			return false;
		}

		// TX ID is optional but if supplied do a basic sanity check.
		if ( ! empty( $txid ) ) {
			if ( strlen( $txid ) < 10 || strlen( $txid ) > 200 ) {
				wc_add_notice( __( 'The transaction ID looks invalid. Please double-check it.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
				return false;
			}
			if ( ! preg_match( '/^[a-zA-Z0-9]+$/', $txid ) ) {
				wc_add_notice( __( 'Transaction ID should only contain letters and numbers.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
				return false;
			}
		}

		return true;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Process payment.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Process the payment for a new order.
	 *
	 * @param int $order_id The order ID.
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found. Please try again.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce checkout.
		$network = sanitize_key( wp_unslash( $_POST['cwwlite_network'] ?? '' ) );
		$txid    = sanitize_text_field( wp_unslash( $_POST['cwwlite_txid'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$cfg = self::$networks[ $network ] ?? null;
		if ( ! $cfg ) {
			wc_add_notice( __( 'Invalid network selected.', 'crypto-wallet-payment-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$wallet        = $this->get_option( 'wallet_' . $network );
		$amounts       = $this->get_crypto_raw_amounts( array( $network => $cfg ) );
		$crypto_amount = $amounts[ $network ] ?? '';

		// Resolve explorer URL based on environment.
		$explorer = $this->is_testnet()
			? ( $cfg['explorer_testnet'] ?? $cfg['explorer'] )
			: $cfg['explorer'];

		// Store order meta.
		$order->update_meta_data( '_cwwlite_network', $network );
		$order->update_meta_data( '_cwwlite_network_label', $cfg['label'] );
		$order->update_meta_data( '_cwwlite_symbol', $cfg['symbol'] );
		$order->update_meta_data( '_cwwlite_wallet', $wallet );
		$order->update_meta_data( '_cwwlite_crypto_amount', $crypto_amount );
		$order->update_meta_data( '_cwwlite_environment', $this->get_environment() );
		$order->update_meta_data( '_cwwlite_explorer', $explorer );
		if ( ! empty( $txid ) ) {
			$order->update_meta_data( '_cwwlite_txid', $txid );
		}
		$order->save();

		// Place on hold.
		$hold_note = $this->hold_message ? $this->hold_message : __( 'Order on hold pending crypto payment verification.', 'crypto-wallet-payment-for-woocommerce' );
		$order->update_status( 'on-hold', $hold_note );

		// Reduce stock.
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Thank-you page.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Output the thank-you page content for crypto orders.
	 *
	 * @param int $order_id The order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$network     = $order->get_meta( '_cwwlite_network' );
		$label       = $order->get_meta( '_cwwlite_network_label' );
		$symbol      = $order->get_meta( '_cwwlite_symbol' );
		$wallet      = $order->get_meta( '_cwwlite_wallet' );
		$amount      = $order->get_meta( '_cwwlite_crypto_amount' );
		$txid        = $order->get_meta( '_cwwlite_txid' );
		$environment = $order->get_meta( '_cwwlite_environment' ) ? $order->get_meta( '_cwwlite_environment' ) : 'mainnet';
		$explorer    = $order->get_meta( '_cwwlite_explorer' );
		$is_testnet  = ( 'testnet' === $environment );
		// Fallback: derive explorer from static config if meta missing.
		if ( empty( $explorer ) ) {
			$cfg      = self::$networks[ $network ] ?? null;
			$explorer = $cfg ? $cfg['explorer'] : '';
		}
		?>
		<div class="cwwlite-thankyou">
			<h3>
				<?php esc_html_e( 'Complete Your Crypto Payment', 'crypto-wallet-payment-for-woocommerce' ); ?>
				<?php if ( $is_testnet ) : ?>
					<span class="cwwlite-testnet-badge"><?php esc_html_e( 'TESTNET', 'crypto-wallet-payment-for-woocommerce' ); ?></span>
				<?php endif; ?>
			</h3>

			<?php if ( $is_testnet ) : ?>
			<div class="cwwlite-testnet-checkout-banner">
				<span>🧪</span>
				<strong><?php esc_html_e( 'Testnet Mode', 'crypto-wallet-payment-for-woocommerce' ); ?></strong>
				— <?php esc_html_e( 'This is a test environment. Do not send real funds.', 'crypto-wallet-payment-for-woocommerce' ); ?>
			</div>
			<?php endif; ?>

			<?php if ( $this->instructions ) : ?>
				<p><?php echo wp_kses_post( $this->instructions ); ?></p>
			<?php endif; ?>

			<table class="cwwlite-payment-summary">
				<tr>
					<th><?php esc_html_e( 'Network', 'crypto-wallet-payment-for-woocommerce' ); ?></th>
					<td><?php echo esc_html( $label ); ?></td>
				</tr>
				<?php if ( $amount ) : ?>
				<tr>
					<th><?php esc_html_e( 'Amount to Send', 'crypto-wallet-payment-for-woocommerce' ); ?></th>
					<td><strong><?php echo esc_html( $amount . ' ' . $symbol ); ?></strong></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Send To Address', 'crypto-wallet-payment-for-woocommerce' ); ?></th>
					<td><code><?php echo esc_html( $wallet ); ?></code></td>
				</tr>
				<?php if ( $txid ) : ?>
				<tr>
					<th><?php esc_html_e( 'TX ID Submitted', 'crypto-wallet-payment-for-woocommerce' ); ?></th>
					<td>
						<?php if ( ! empty( $explorer ) ) : ?>
							<a href="<?php echo esc_url( cwwlite_explorer_url( $explorer, $txid ) ); ?>" target="_blank">
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
				<?php esc_html_e( 'Once you have sent the payment, paste your transaction ID on the order page if you have not already. We will manually verify and update your order status.', 'crypto-wallet-payment-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Email instructions.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Append payment instructions to order emails.
	 *
	 * @param WC_Order $order         The order object.
	 * @param bool     $sent_to_admin Whether sent to admin.
	 * @param bool     $plain_text    Whether plain-text email.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		if ( ! in_array( $order->get_status(), array( 'on-hold', 'pending' ), true ) ) {
			return;
		}

		$wallet = $order->get_meta( '_cwwlite_wallet' );
		$amount = $order->get_meta( '_cwwlite_crypto_amount' );
		$symbol = $order->get_meta( '_cwwlite_symbol' );
		$label  = $order->get_meta( '_cwwlite_network_label' );

		if ( $plain_text ) {
			echo "\n" . esc_html( $this->instructions ) . "\n\n";
			echo esc_html( $label ) . "\n";
			if ( $amount ) {
				echo esc_html( $amount . ' ' . $symbol ) . "\n";
			}
			echo esc_html( $wallet ) . "\n";
		} else {
			echo '<h3>' . esc_html__( 'Crypto Payment Instructions', 'crypto-wallet-payment-for-woocommerce' ) . '</h3>';
			echo '<p>' . wp_kses_post( $this->instructions ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Network:', 'crypto-wallet-payment-for-woocommerce' ) . '</strong> ' . esc_html( $label ) . '</p>';
			if ( $amount ) {
				echo '<p><strong>' . esc_html__( 'Amount:', 'crypto-wallet-payment-for-woocommerce' ) . '</strong> ' . esc_html( $amount . ' ' . $symbol ) . '</p>';
			}
			echo '<p><strong>' . esc_html__( 'Send to:', 'crypto-wallet-payment-for-woocommerce' ) . '</strong> <code>' . esc_html( $wallet ) . '</code></p>';
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// AJAX: live rates.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * AJAX handler: return live exchange rates.
	 */
	public function ajax_get_rates() {
		check_ajax_referer( 'cwwlite_checkout', 'nonce' );
		wp_send_json_success( $this->get_live_rates() );
	}

	/**
	 * Fetch live crypto exchange rates from CoinGecko (cached 5 min).
	 *
	 * @return array Rates keyed by CoinGecko ID.
	 */
	public function get_live_rates(): array {
		$currency  = strtolower( get_woocommerce_currency() );
		$cache_key = 'cwwlite_live_rates_' . $currency;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$ids = 'bitcoin,ethereum,solana,ripple';
		$url = "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies={$currency}";

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array();
		}

		$map = array(
			'bitcoin'  => $body['bitcoin'][ $currency ] ?? null,
			'ethereum' => $body['ethereum'][ $currency ] ?? null,
			'solana'   => $body['solana'][ $currency ] ?? null,
			'xrp'      => $body['ripple'][ $currency ] ?? null,
		);
		$map = array_filter( $map );

		set_transient( $cache_key, $map, 5 * MINUTE_IN_SECONDS );
		return $map;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// AJAX: order status (customer polling after checkout)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * AJAX handler: return current order payment status.
	 */
	public function ajax_order_status() {
		check_ajax_referer( 'cwwlite_checkout', 'nonce' );
		$order_id = absint( $_POST['order_id'] ?? 0 );
		if ( ! $order_id ) {
			wp_send_json_error( 'invalid' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'not_found' );
		}

		// Ownership check: only the order's customer or an admin can poll status.
		$customer_id = $order->get_customer_id();
		if ( is_user_logged_in() ) {
			if ( $customer_id && get_current_user_id() !== $customer_id && ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				wp_send_json_error( 'forbidden' );
			}
		} else {
			// Guest: only allow polling for a short window after checkout (5 min).
			$order_date = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			if ( $order_date && ( time() - $order_date ) > 300 ) {
				wp_send_json_error( 'forbidden' );
			}
		}

		wp_send_json_success( array( 'status' => $order->get_status() ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Asset enqueueing.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Enqueue checkout page scripts and styles.
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}
		if ( 'yes' !== $this->get_option( 'enabled' ) ) {
			return;
		}

		wp_enqueue_style(
			'cwwlite-checkout',
			CWWLITE_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			CWWLITE_VERSION
		);

		wp_enqueue_script(
			'cwwlite-checkout',
			CWWLITE_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			CWWLITE_VERSION,
			true
		);

		$enabled   = $this->get_enabled_networks();
		$amounts   = $this->get_crypto_display_amounts( $enabled );
		$rate_lock = absint( $this->get_option( 'rate_lock_minutes', 15 ) );

		wp_localize_script(
			'cwwlite-checkout',
			'cwwliteData',
			array(
				'gatewayId'   => $this->id,
				'nonce'       => wp_create_nonce( 'cwwlite_checkout' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'networks'    => $enabled,
				'amounts'     => $amounts,
				'currency'    => get_woocommerce_currency(),
				'rateLockSec' => $rate_lock * 60,
			)
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Helpers.
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Return the subset of networks that have a wallet address configured.
	 *
	 * @return array
	 */
	public function get_enabled_networks(): array {
		$out = array();
		foreach ( self::$networks as $key => $cfg ) {
			$wallet = $this->get_option( 'wallet_' . $key );
			if ( ! empty( $wallet ) ) {
				$out[ $key ] = $cfg;
			}
		}
		return $out;
	}

	/**
	 * Return the current network environment (mainnet or testnet).
	 *
	 * @return string
	 */
	public function get_environment(): string {
		return $this->get_option( 'environment', 'mainnet' );
	}

	/**
	 * Whether testnet mode is active.
	 *
	 * @return bool
	 */
	public function is_testnet(): bool {
		return 'testnet' === $this->get_environment();
	}

	/**
	 * Get the current cart or order total in the shop currency.
	 *
	 * @return float
	 */
	private function get_cart_total(): float {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			global $wp;
			$order = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			return $order ? (float) $order->get_total() : 0.0;
		}
		return WC()->cart ? (float) WC()->cart->get_total( 'raw' ) : 0.0;
	}

	/**
	 * Return display-formatted crypto amounts for each enabled network.
	 *
	 * @param array $networks Enabled network configs.
	 * @return array
	 */
	public function get_crypto_display_amounts( array $networks ): array {
		$total = $this->get_cart_total();
		$rates = $this->get_live_rates();
		$out   = array();

		foreach ( $networks as $key => $cfg ) {
			if ( ! isset( $rates[ $key ] ) || $rates[ $key ] <= 0 ) {
				$out[ $key ] = '';
				continue;
			}
			$crypto      = $total / $rates[ $key ];
			$out[ $key ] = rtrim( rtrim( number_format( $crypto, 8, '.', '' ), '0' ), '.' );
		}
		return $out;
	}

	/**
	 * Return raw (full-precision) crypto amounts for each enabled network.
	 *
	 * @param array $networks Enabled network configs.
	 * @return array
	 */
	private function get_crypto_raw_amounts( array $networks ): array {
		$total = $this->get_cart_total();
		$rates = $this->get_live_rates();
		$out   = array();

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
