# Crypto Wallet Payment for WooCommerce — Lite

> **Accept Bitcoin, Ethereum, Solana & XRP payments in WooCommerce — no payment processor, no API keys, no KYC**

![WooCommerce](https://img.shields.io/badge/WooCommerce-Compatible-blue)
![License](https://img.shields.io/badge/License-GPL--2.0%2B-lightgrey)

**Crypto Wallet Payment for WooCommerce — Lite** lets merchants accept cryptocurrency payments directly to their own wallet — no third-party processor, no custodial service, and no KYC required.

Accept **Bitcoin (BTC), Ethereum (ETH), Solana (SOL), and XRP** payments. Customers select a network at checkout, copy your wallet address, send from their preferred wallet app, then paste their transaction hash. You manually verify and complete the order from the WooCommerce order admin.

Browser wallet auto-send (MetaMask, Phantom, WalletConnect), stablecoins (USDT/USDC), and Cardano are available in the Pro version.

> 🔗 **[Get Pro Version on Patreon →](https://www.patreon.com/posts/crypto-wallet-157796120?source=lite)**

## External Services

This plugin connects to the **[CoinGecko API](https://api.coingecko.com)** to retrieve live cryptocurrency exchange rates. This is a server-side request, cached for 5 minutes per currency. No user personal data is transmitted — only the store's base currency code is sent as a URL parameter (e.g. `?vs_currencies=usd`).

- **CoinGecko Terms of Service:** https://www.coingecko.com/en/terms
- **CoinGecko Privacy Policy:** https://www.coingecko.com/en/privacy

No other external services are contacted. All JavaScript and CSS assets are loaded locally from within the plugin.

---

## Features

- ✅ Accept Bitcoin (BTC), Ethereum (ETH), Solana (SOL), and XRP
- ✅ Non-custodial — payments go directly to your wallet
- ✅ No API keys, no KYC, no third-party gateway required
- ✅ WooCommerce Classic & Block Checkout support
- ✅ Real-time live exchange rates via CoinGecko (free, no API key)
- ✅ Rate lock countdown timer (configurable 0–60 min)
- ✅ Manual TX ID submission at checkout or from My Account
- ✅ One-click admin verification with order status auto-update
- ✅ Block explorer links for every transaction
- ✅ HPOS (High-Performance Order Storage) compatible
- ✅ WooCommerce Blocks / Gutenberg Checkout support
- ✅ Conflict-safe prefixes — safe alongside other crypto plugins

---

## Supported Cryptocurrencies

| Coin | Symbol | Network | Lite | Pro |
|---|---|---|---|---|
| Bitcoin | BTC | Bitcoin Mainnet | ✅ | ✅ |
| Ethereum | ETH | Ethereum Mainnet | ✅ | ✅ |
| Solana | SOL | Solana Mainnet | ✅ | ✅ |
| XRP | XRP | Ripple / XRPL | ✅ | ✅ |
| Tether | USDT | ERC-20, SPL, BEP-20, TRC-20 | ❌ | ✅ |
| USD Coin | USDC | ERC-20, SPL | ❌ | ✅ |
| Cardano | ADA | Cardano Mainnet | ❌ | ✅ |
| Binance Coin | BNB | BNB Smart Chain | ❌ | ✅ |
| Polygon | MATIC / POL | Polygon PoS | ❌ | ✅ |
| Arbitrum | ARB | Arbitrum One | ❌ | ✅ |
| Optimism | OP | Optimism Mainnet | ❌ | ✅ |

---

## Supported Wallets

### Lite Version (Manual Transfer — all wallets work)
Any wallet app that can send a transaction works with the Lite version — customers copy the address, send from their preferred wallet, then paste the TX hash.

### Pro Version (Browser Wallet Auto-Send)
- **MetaMask** — EVM networks (ETH, BNB, MATIC, ARB, OP)
- **Phantom** — Solana (SOL, USDT SPL, USDC SPL)
- **Solflare** — Solana
- **Trust Wallet** — via WalletConnect
- **Coinbase Wallet** — via WalletConnect
- **Rainbow Wallet** — via WalletConnect
- **Eternl** — Cardano (ADA)
- **Lace** — Cardano (ADA)
- **Vespr** — Cardano (ADA)
- **All WalletConnect Compatible Wallets** — 300+ wallets

---

## Lite Version — Full Feature Details

| Feature | Detail |
|---|---|
| **4 supported networks** | BTC, ETH, SOL, XRP |
| **Live exchange rates** | CoinGecko free API — no API key required |
| **Rate lock countdown timer** | Configurable 0–60 min; auto-refreshes rates when expired |
| **Manual TX ID submission** | Customer pastes hash at checkout or from My Account |
| **TX ID update form** | Customers can submit/update TX on the order detail page |
| **Admin meta box** | View network, wallet, amount, TX ID, explorer link; edit TX ID |
| **One-click manual verification** | Admin marks order verified → auto-sets to Processing or Completed |
| **Orders list columns** | "Crypto Network" and "TX ID" columns with block explorer links |
| **TX ID in order emails** | Customer receives wallet + amount in the on-hold email |
| **TX ID in order totals table** | Displayed on order detail page with explorer link |
| **Retry payment button** | My Account order page lets customer re-pay without a new order |
| **Verification status notice** | Shows "verified" or "awaiting manual check" badge on order page |
| **WooCommerce Blocks support** | Works with Block Checkout (Gutenberg) |
| **HPOS compatible** | Fully compatible with High-Performance Order Storage |
| **No external dependencies** | No API keys, no SDKs, no cron jobs, no extra tables |
| **Conflict-safe prefixes** | All functions/classes/options use `cwwlite` / `CWWLITE` prefix |

---

## ⚡ Pro Version — Additional Features

Upgrade to **[Crypto Wallet Payment Pro](https://www.patreon.com/posts/crypto-wallet-157796120?source=lite)** for the full non-custodial WalletConnect and Web3 experience:

- **Auto blockchain verification** — BTC, ETH, SOL, XRP, ADA, BNB, MATIC (Polygon), ARB (Arbitrum), OP (Optimism) verified on-chain via API — no manual checking
- **Browser wallet auto-send** — MetaMask, Phantom, Solflare, Eternl, Lace, Vespr (one-click send via WalletConnect — no TX paste needed)
- **Stablecoin support** — USDT & USDC on ERC-20, SPL, TRC-20, BEP-20
- **WP-Cron + Webhook verification** — push-based or polling auto-check
- **Fraud detection & security log** — duplicate TX detection, amount checks, admin alerts
- **Refund workflow** — record crypto refunds, email admin + customer, per-network fees
- **Order expiry** — auto-cancel unpaid on-hold orders after configurable hours
- **Amount tolerance** — accept slight underpayments, flag overpayments
- **Test mode** — testnet/devnet with pre-filled addresses
- **Dashboard widget** — real-time crypto payment stats
- **Cardano (ADA) support** — Vespr, Eternl, Lace browser wallets via WalletConnect

> 🔗 **[Get Pro on Patreon →](https://www.patreon.com/posts/crypto-wallet-157796120?source=lite)**

---

## Installation

1. Upload the `crypto-wallet-payment-for-woocommerce-lite` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Payments → Crypto Wallet Payment (Lite)**.
4. Enter your wallet addresses for each network you want to accept.
5. Done — the non-custodial crypto payment method is live at checkout.

---

## How It Works

1. Customer selects a cryptocurrency (Bitcoin, Ethereum, Solana, XRP) at WooCommerce checkout.
2. Plugin displays your wallet address and the live crypto amount calculated from real-time exchange rates.
3. Customer sends payment manually via their own Web3 wallet app (MetaMask, Trust Wallet, Coinbase Wallet, etc.).
4. Customer pastes the TX hash at checkout (or later from My Account).
5. Admin sees the TX ID in the order meta box with a block explorer link.
6. Admin clicks **"✅ Mark as Verified & Complete"** → order moves to Processing/Completed.

---

## FAQ

### Does this plugin require KYC?
No. This is a fully decentralized, non-custodial crypto payment gateway. No identity verification, no third-party sign-up, and no custodial service is involved. Payments go directly to your wallet.

### Does it support WalletConnect?
WalletConnect browser wallet auto-send is a **Pro** feature. The Lite version supports manual transfers from any WalletConnect-compatible wallet — customers copy your address and send from their wallet app.

### Can I accept Bitcoin payments in WooCommerce?
Yes. Customers can pay in Bitcoin (BTC) directly from their crypto wallets. The plugin displays your BTC address and the real-time BTC amount at checkout.

### Does it support MetaMask?
MetaMask one-click auto-send is a **Pro** feature. In the Lite version, customers can copy your Ethereum address and manually send ETH from MetaMask.

### Does it support USDT or USDC stablecoins?
Stablecoin support (USDT / USDC on ERC-20, SPL, BEP-20, TRC-20) is available in the **Pro version**.

### Is there a transaction fee?
No. This is a direct wallet-to-wallet non-custodial payment. Only standard blockchain network fees (gas fees) apply — the plugin takes no cut.

### Does it work with the WooCommerce Block Checkout?
Yes. The plugin fully supports WooCommerce Block Checkout (Gutenberg) as well as the Classic Checkout.

### Is it compatible with WooCommerce HPOS?
Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables).

---

## File Structure

```
crypto-wallet-payment-for-woocommerce-lite/
├── ryanplugins-crypto-wallet-lite.php           ← Main plugin file
├── includes/
│   ├── class-ryanplugins-cwwlite-gateway.php    ← Payment gateway
│   ├── class-ryanplugins-cwwlite-admin.php      ← Admin columns & meta box
│   ├── class-ryanplugins-cwwlite-order-handler.php ← My Account TX form
│   └── class-ryanplugins-cwwlite-blocks.php     ← Block Checkout support
├── assets/
│   ├── css/
│   │   ├── checkout.css
│   │   ├── admin.css
│   │   └── settings.css
│   ├── js/
│   │   ├── checkout.js
│   │   ├── admin.js
│   │   ├── settings.js
│   │   └── blocks-lite.js
│   └── img/
│       └── *.svg                                ← Network & wallet icons
└── README.md
```

---

## Prefix Reference

| Scope | Prefix |
|---|---|
| PHP classes | `RyanPlugins_CWWLITE_` |
| PHP functions | `ryanplugins_cwwlite_` |
| WP option keys | `woocommerce_ryanplugins_cwwlite_crypto_settings` |
| Order meta keys | `_cwwlite_*` |
| CSS classes | `.cwwlite-*` |
| JS globals | `cwwliteData`, `cwwliteAdmin` |
| AJAX actions | `ryanplugins_cwwlite_*` |
| Transients | `ryanplugins_cwwlite_*` |

---


## Screenshots

### 1. Classic Checkout — Pill Network Selector & Address Box
![WooCommerce Crypto Checkout - Pill network selector with Bitcoin, Ethereum, Solana, XRP options and address copy box](https://raw.githubusercontent.com/ryanplugins/wc-ryanplugins-crypto-wallet-lite/main/screenshots/woocommerce-crypto-checkout.png)

*Pill-style network selector with live crypto amounts, wallet address copy button, and TX ID submission — Classic Checkout.*

---

### 2. Block Checkout — Full Gutenberg Layout
![WooCommerce Block Checkout Crypto Payment - Network pills, rate timer, manual transfer card](https://raw.githubusercontent.com/ryanplugins/wc-ryanplugins-crypto-wallet-lite/main/screenshots/woocommerce-block-checkout.png)

*Identical UI in the WooCommerce Block (Gutenberg) Checkout — same pill selector, rate countdown, address box, and TX ID field.*

---

### 3. Settings Page — Tabbed Admin Interface
![WooCommerce Crypto Payment Gateway Settings - Tabbed settings with General, Networks and Wallets, Upgrade to Pro tabs](https://raw.githubusercontent.com/ryanplugins/wc-ryanplugins-crypto-wallet-lite/main/screenshots/plugin-settings-page.png)

*Tabbed settings page: General settings (title, description, rate lock) and Networks & Wallets (wallet addresses per coin).*

---

### 4. Admin Order Management — Crypto Meta Box & Orders Columns
![WooCommerce Crypto Order Admin - Crypto Network and TX ID columns, manual verification meta box](https://raw.githubusercontent.com/ryanplugins/wc-ryanplugins-crypto-wallet-lite/main/screenshots/admin-order-meta-box.png)

*Per-order meta box shows network, wallet, amount, TX ID with block explorer link, and one-click "Mark as Verified" button. Orders list shows Crypto Network and TX ID columns.*

---

### 5. Upgrade to Pro — Settings Tab
![Crypto Wallet Payment Gateway Lite Upgrade to Pro - Dark gradient upgrade box with Pro features list](https://raw.githubusercontent.com/ryanplugins/wc-ryanplugins-crypto-wallet-lite/main/screenshots/upgrade-to-pro-settings.png)

*Upgrade to Pro tab highlights auto-verification, MetaMask/Phantom/WalletConnect auto-send, stablecoin support, and more.*
