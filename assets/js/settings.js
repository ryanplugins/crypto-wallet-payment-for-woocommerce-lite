/**
 * Crypto Wallet Payment (Lite) — Settings Page JS
 *
 * Handles:
 *  - Horizontal tab switching (General / Networks)
 *  - Active tab persistence via sessionStorage + URL hash
 *  - Hiding WooCommerce's duplicate external "Save changes" button
 *  - Save notification toast (💾 Saving… → ✅ Saved!)
 *  - Wallet address status chips (active / inactive)
 *  - Suppressing the "Leave site?" dialog on tab click
 */
(function () {
    'use strict';

    var PREFIX  = 'cwwlite';
    var STORAGE = PREFIX + '-active-tab';
    var wrap    = document.getElementById( PREFIX + '-settings-wrap' );

    if ( ! wrap ) return; // not on our settings page

    // ── 1. Tab switching ──────────────────────────────────────────────────────

    var tabs   = Array.from( wrap.querySelectorAll( '.' + PREFIX + '-nav-tab' ) );
    var panels = Array.from( wrap.querySelectorAll( '.' + PREFIX + '-tab-panel' ) );

    function activateTab( id, saveToStorage ) {
        // Validate id exists
        var btn = tabs.find( function (b) { return b.dataset.tab === id; } );
        if ( ! btn ) {
            btn = tabs[0];
            id  = btn ? btn.dataset.tab : null;
        }
        if ( ! id ) return;

        tabs.forEach( function (b) {
            var active = b.dataset.tab === id;
            b.classList.toggle( PREFIX + '-nav-tab-active', active );
            b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
        } );

        panels.forEach( function (p) {
            var active = p.id === PREFIX + '-panel-' + id;
            p.classList.toggle( PREFIX + '-tab-panel-active', active );
            if ( active ) {
                p.removeAttribute( 'hidden' );
            } else {
                p.setAttribute( 'hidden', '' );
            }
        } );

        // Update hidden input so PHP knows the active tab on save
        var input = document.getElementById( PREFIX + '-active-tab-input' );
        if ( input ) input.value = id;

        if ( saveToStorage !== false ) {
            try { sessionStorage.setItem( STORAGE, id ); } catch (e) {}
        }
    }

    tabs.forEach( function (btn) {
        btn.addEventListener( 'click', function () {
            activateTab( btn.dataset.tab, true );
        } );
    } );

    // ── 2. Restore active tab on load ─────────────────────────────────────────

    (function () {
        // Priority: URL hash > sessionStorage > first tab
        var hash    = window.location.hash.replace( '#', '' );
        var stored  = '';
        try { stored = sessionStorage.getItem( STORAGE ) || ''; } catch (e) {}

        var id = null;
        // Validate hash against real tabs
        if ( hash && tabs.find( function (b) { return b.dataset.tab === hash; } ) ) {
            id = hash;
        } else if ( stored && tabs.find( function (b) { return b.dataset.tab === stored; } ) ) {
            id = stored;
        }

        activateTab( id || ( tabs[0] ? tabs[0].dataset.tab : null ), false );
    }());

    // ── 3. Hide WooCommerce's duplicate "Save changes" button ─────────────────
    //
    // WC appends a <p class="submit"> with its own save button AFTER admin_options()
    // which is outside our wrap div. We render our own Save button inside the wrap
    // so we hide WC's external one to avoid duplication.

    (function () {
        // Use MutationObserver to catch WC's button which may be injected after DOMContentLoaded
        function hideExternalSubmits() {
            var externalSubmits = document.querySelectorAll( 'p.submit' );
            externalSubmits.forEach( function (p) {
                if ( ! wrap.contains( p ) ) {
                    p.style.display = 'none';
                }
            } );
        }

        hideExternalSubmits();

        // Also observe for late injection
        var observer = new MutationObserver( hideExternalSubmits );
        observer.observe( document.body, { childList: true, subtree: true } );

        // Stop observing after 3s — WC will have rendered by then
        setTimeout( function () { observer.disconnect(); }, 3000 );
    }());

    // ── 4. Toast notification ─────────────────────────────────────────────────

    var toast = document.createElement( 'div' );
    toast.id  = PREFIX + '-toast';
    document.body.appendChild( toast );

    // "Saving…" — our own #mainform is inside the wrap
    var wcForm = document.getElementById( 'mainform' );
    if ( wcForm ) {
        wcForm.addEventListener( 'submit', function () {
            window.onbeforeunload = null;
            toast.className = PREFIX + '-toast-saving ' + PREFIX + '-toast-visible';
            toast.textContent = '💾 Saving settings…';
        } );
    }

    // "Saved!" — show after reload if ?settings-updated=true
    (function () {
        if ( window.location.search.indexOf( 'settings-updated' ) !== -1 ) {
            toast.className = PREFIX + '-toast-success ' + PREFIX + '-toast-visible';
            toast.textContent = '✅ Settings saved!';
            setTimeout( function () {
                toast.classList.remove( PREFIX + '-toast-visible' );
            }, 3500 );
        }
    }());

    // Click to dismiss early
    toast.addEventListener( 'click', function () {
        toast.classList.remove( PREFIX + '-toast-visible' );
    } );

    // ── 5. Wallet address live status chips ───────────────────────────────────
    //
    // Each wallet input gets a small "Active / Not set" chip that updates as
    // the admin types. Chips are injected next to each input.

    var walletInputs = Array.from(
        wrap.querySelectorAll( 'input[name$="[wallet_bitcoin]"], input[name$="[wallet_ethereum]"], input[name$="[wallet_solana]"], input[name$="[wallet_xrp]"]' )
    );

    walletInputs.forEach( function (input) {
        // Create chip
        var chip = document.createElement( 'span' );
        chip.className = PREFIX + '-network-chip';
        input.parentNode.insertBefore( chip, input.nextSibling );

        function updateChip() {
            var val = input.value.trim();
            if ( val ) {
                chip.className = PREFIX + '-network-chip ' + PREFIX + '-chip-active';
                chip.textContent = '✔ Active';
            } else {
                chip.className = PREFIX + '-network-chip ' + PREFIX + '-chip-inactive';
                chip.textContent = 'Not set';
            }
        }

        input.addEventListener( 'input', updateChip );
        updateChip(); // initial state
    } );

    // ── 6. Suppress "Leave site?" on tab button clicks ────────────────────────
    //
    // WooCommerce registers window.onbeforeunload whenever a form field changes.
    // Tab buttons are type="button" so they don't submit, but they DO change
    // the hidden active-tab input which triggers WC's guard.
    // We suppress it only for our tab buttons.

    tabs.forEach( function (btn) {
        btn.addEventListener( 'mousedown', function () {
            // Temporarily clear — the next click won't change any "real" field
            var guard = window.onbeforeunload;
            window.onbeforeunload = null;
            // Restore after the click fully processes
            setTimeout( function () { window.onbeforeunload = guard; }, 100 );
        } );
    } );

}());
