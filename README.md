# Crypto Wallet Payment for WooCommerce — Lite

A lightweight, zero-dependency crypto payment gateway for WooCommerce.  
Accepts **Bitcoin (BTC), Ethereum (ETH), Solana (SOL), and XRP** with manual TX verification and live exchange rates.

---

## ✅ Lite Version — Included Features

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

Upgrade to [RyanPlugins Crypto Wallet Pro](https://www.patreon.com/posts/crypto-wallet-157796120?source=lite) for:

- **Auto blockchain verification** — BTC, ETH, SOL, XRP, ADA, BNB, MATIC (Polygon), ARB (Arbitrum), OP (Optimism) verified on-chain via API
- **Browser wallet auto-send** — MetaMask, Phantom, Solflare, Eternl, Lace, Vespr (one-click send, no TX paste needed)
- **Stablecoin support** — USDT & USDC on ERC-20, SPL, TRC-20, BEP-20
- **WP-Cron + Webhook verification** — push-based or polling auto-check
- **Fraud detection & security log** — duplicate TX detection, amount checks, admin alerts
- **Refund workflow** — record crypto refunds, email admin + customer, per-network fees
- **Order expiry** — auto-cancel unpaid on-hold orders after configurable hours
- **Amount tolerance** — accept slight underpayments, flag overpayments
- **Test mode** — testnet/devnet with pre-filled addresses
- **Dashboard widget** — real-time crypto payment stats
- **Cardano (ADA) support** — Vespr, Eternl, Lace browser wallets

---

## Installation

1. Upload the `wc-ryanplugins-crypto-wallet-lite` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Payments → Crypto Wallet Payment (Lite)**.
4. Enter your wallet addresses for each network you want to accept.
5. Done — the payment method is live at checkout.

---

## How it works

1. Customer selects a cryptocurrency at checkout.
2. Plugin displays your wallet address and the live crypto amount.
3. Customer sends payment manually via their own wallet app.
4. Customer pastes the TX hash at checkout (or later from My Account).
5. Admin sees the TX ID in the order meta box with a block explorer link.
6. Admin clicks **"✅ Mark as Verified & Complete"** → order moves to Processing/Completed.

---

## File structure

```
wc-ryanplugins-crypto-wallet-lite/
├── ryanplugins-crypto-wallet-lite.php        ← Main plugin file
├── includes/
│   ├── class-ryanplugins-cwwlite-gateway.php     ← Payment gateway
│   ├── class-ryanplugins-cwwlite-admin.php       ← Admin columns & meta box
│   ├── class-ryanplugins-cwwlite-order-handler.php ← My Account TX form
│   └── class-ryanplugins-cwwlite-blocks.php      ← Block Checkout support
├── assets/
│   ├── css/
│   │   ├── checkout.css
│   │   └── admin.css
│   ├── js/
│   │   ├── checkout.js
│   │   ├── admin.js
│   │   └── blocks-lite.js
│   └── img/
│       └── *.svg                                  ← Network icons
└── README.md
```

---

## Prefix reference

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

**License:** GPL-2.0+  
**Author:** RyanPlugins — https://www.patreon.com/posts/crypto-wallet-157796120?source=lite
