=== Crypto Wallet Payment for WooCommerce — Lite ===
Contributors: ryanplugins
Tags: cryptocurrency, bitcoin, woocommerce, payment gateway, ethereum
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum, Solana and XRP payments in WooCommerce with manual transaction verification and live exchange rates.

== Description ==

**Crypto Wallet Payment for WooCommerce — Lite** lets your store accept cryptocurrency payments directly to your wallet — no third-party processor, no custodial service, no KYC, and no API keys required.

Customers select a network (Bitcoin, Ethereum, Solana, or XRP) at checkout, copy your wallet address, send from their own crypto wallet, then optionally paste their transaction hash. You manually verify the payment and mark the order complete with one click from the order admin.

= External Services =

This plugin connects to the **CoinGecko API** (https://api.coingecko.com) to fetch live cryptocurrency exchange rates. This call is made server-side, is cached for 5 minutes, and requires no API key. No user data is transmitted — only the store's base currency code is sent as a query parameter.

By using this plugin you agree to CoinGecko's Terms of Service: https://www.coingecko.com/en/terms
CoinGecko Privacy Policy: https://www.coingecko.com/en/privacy

= Features =

* Accept Bitcoin (BTC), Ethereum (ETH), Solana (SOL), XRP
* Live exchange rates via CoinGecko (no API key, cached 5 min)
* Rate lock countdown timer (0–60 min, configurable)
* Manual transaction ID submission at checkout or from My Account
* One-click admin verification — sets order to Processing or Completed
* Block explorer links for every transaction (Blockstream, Etherscan, Solscan, XRPScan)
* Orders list columns: Crypto Network and TX ID
* TX ID shown in order emails and order detail page
* Retry payment button on My Account order page
* Verification status notice for customers
* WooCommerce Block Checkout (Gutenberg) support
* HPOS (High-Performance Order Storage) compatible
* Tabbed settings page with live wallet status chips
* Dismissible admin upgrade notice

= Privacy =

This plugin does not collect, store, or transmit any personal data beyond what WooCommerce already stores as part of the order. The only external HTTP request made is to the CoinGecko public API to retrieve exchange rates. No user identifiable data is included in that request.

= Upgrade to Pro =

The Pro version adds auto blockchain verification, browser wallet auto-send (MetaMask, Phantom, Solflare, Eternl, Lace, Vespr), stablecoin support (USDT/USDC), Cardano (ADA), EVM L2 networks (BNB, MATIC, ARB, OP), fraud detection, refund workflow, order expiry, amount tolerance, and more.

Get Pro: https://www.patreon.com/posts/crypto-wallet-157796120?source=lite

== Installation ==

1. Upload the `wc-ryanplugins-crypto-wallet-lite` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Go to **WooCommerce → Settings → Payments → Crypto Wallet Payment (Lite)**
4. Enter your wallet address for each network you want to accept
5. Save — the payment method is immediately available at checkout

== Frequently Asked Questions ==

= Does this plugin require an API key? =
No. Exchange rates are fetched from CoinGecko's free public API. No account or API key is needed.

= Does it require KYC or a payment processor account? =
No. Payments go directly to your wallet. No third-party account, KYC, or custodial service is involved.

= Which cryptocurrencies are supported in the Lite version? =
Bitcoin (BTC), Ethereum (ETH), Solana (SOL), and XRP.

= How does manual verification work? =
Once a customer submits their transaction hash, you can view it in the order admin with a block explorer link, then click "Mark as Verified & Complete" to update the order status.

= Does it work with the Block Checkout (Gutenberg)? =
Yes. Both Classic and Block Checkout are fully supported.

= Is it HPOS compatible? =
Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage.

= Can I use it alongside the Pro version? =
Yes. All functions, classes, and options use the `cwwlite` prefix to avoid conflicts.

== Screenshots ==

1. Classic checkout — pill-style network selector with live crypto amounts, wallet address copy button, and TX ID field
2. Block (Gutenberg) checkout — identical UI in the WooCommerce Block Checkout
3. Settings page — tabbed admin interface with General and Networks & Wallets tabs
4. Admin order management — crypto meta box with TX ID, block explorer link, and one-click verification button
5. Upgrade to Pro settings tab

== Changelog ==

= 1.0.0 =
* Initial release
* Bitcoin, Ethereum, Solana, XRP support
* Live CoinGecko exchange rates
* Manual TX verification workflow
* WooCommerce Block Checkout support
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release.
