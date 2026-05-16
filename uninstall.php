<?php
/**
 * Uninstall — Crypto Wallet Payment for WooCommerce (Lite)
 *
 * Runs when the plugin is deleted from WP Admin → Plugins.
 * Removes all plugin options and per-user dismissal meta.
 *
 * Note: Order meta (_cwwlite_*) is intentionally preserved
 * so that existing order records remain complete after uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// ── 1. Delete gateway settings ─────────────────────────────────────────────
delete_option( 'woocommerce_ryanplugins_cwwlite_crypto_settings' );

// ── 2. Delete cached exchange rates ───────────────────────────────────────
$ryanplugins_cwwlite_uninstall_currencies = array( 'usd', 'eur', 'gbp', 'php', 'jpy', 'aud', 'cad', 'sgd', 'inr', 'brl' );
foreach ( $ryanplugins_cwwlite_uninstall_currencies as $ryanplugins_cwwlite_uninstall_currency ) {
    delete_transient( 'ryanplugins_cwwlite_live_rates_' . $ryanplugins_cwwlite_uninstall_currency );
}

