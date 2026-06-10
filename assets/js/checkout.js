/* global cwwliteData, jQuery */
(function ($) {
    'use strict';

    var data     = window.cwwliteData || {};
    var networks = data.networks  || {};
    var rateLock = parseInt( data.rateLockSec || 0, 10 );
    var timer    = null;
    var countdown = rateLock;

    // ── Network pill switching ────────────────────────────────────────────────

    function activateNetwork( key ) {
        // Update pills
        $( '.cwwlite-pill' ).each( function () {
            var isActive = $( this ).data( 'network' ) === key;
            $( this )
                .toggleClass( 'cwwlite-pill-active', isActive )
                .attr( 'aria-pressed', isActive ? 'true' : 'false' );
        } );

        // Show/hide panels with animation
        $( '.cwwlite-network-panel' ).each( function () {
            if ( $( this ).attr( 'id' ) === 'cwwlite-panel-' + key ) {
                $( this ).show();
            } else {
                $( this ).hide();
            }
        } );

        // Clear previous txid inputs (avoid submitting wrong network's txid)
        $( '.cwwlite-txid-input' ).val( '' );

        // Update hidden network input
        $( '#cwwlite_network' ).val( key );
    }

    $( document ).on( 'click', '.cwwlite-pill', function () {
        var key = $( this ).data( 'network' );
        if ( key ) activateNetwork( key );
    } );

    // ── Copy address ──────────────────────────────────────────────────────────

    $( document ).on( 'click', '.cwwlite-copy-btn', function () {
        var text = $( this ).data( 'copy' );
        var $btn = $( this );
        if ( ! text ) return;

        var doCopy = function () {
            $btn.addClass( 'cwwlite-copied' );
            var $svg  = $btn.find( 'svg' );
            var origHTML = $btn.html();

            $btn.html(
                '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!'
            );

            setTimeout( function () {
                $btn.removeClass( 'cwwlite-copied' ).html( origHTML );
            }, 2000 );
        };

        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( doCopy ).catch( function () {
                fallbackCopy( text );
                doCopy();
            } );
        } else {
            fallbackCopy( text );
            doCopy();
        }
    } );

    function fallbackCopy( text ) {
        var $tmp = $( '<textarea>' ).val( text ).css( { position: 'fixed', top: '-9999px' } ).appendTo( 'body' );
        $tmp[0].select();
        try { document.execCommand( 'copy' ); } catch ( e ) {}
        $tmp.remove();
    }

    // ── Countdown timer ───────────────────────────────────────────────────────

    function formatTime( sec ) {
        var m = Math.floor( sec / 60 );
        var s = sec % 60;
        return m + ':' + ( s < 10 ? '0' : '' ) + s;
    }

    function startCountdown() {
        if ( ! rateLock ) return;
        countdown = rateLock;
        clearInterval( timer );
        $( '.cwwlite-countdown' ).text( formatTime( countdown ) );

        timer = setInterval( function () {
            countdown--;
            $( '.cwwlite-countdown' ).text( formatTime( Math.max( countdown, 0 ) ) );

            if ( countdown <= 0 ) {
                clearInterval( timer );
                refreshRates();
            }
        }, 1000 );
    }

    function refreshRates() {
        $.ajax( {
            url:    data.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cwwlite_get_rates',
                nonce:  data.nonce,
            },
            success: function ( resp ) {
                if ( resp && resp.success && resp.data ) {
                    updateAmountBadges( resp.data );
                }
                startCountdown();
            },
            error: function () {
                startCountdown();
            },
        } );
    }

    function updateAmountBadges( rates ) {
        var cartTotal = parseFloat( data.cartTotal || 0 );
        if ( ! cartTotal ) return;

        $.each( networks, function ( key, cfg ) {
            var rate = rates[ key ];
            if ( ! rate ) return;
            var crypto = ( cartTotal / rate ).toFixed( 8 ).replace( /0+$/, '' ).replace( /\.$/, '' );
            $( '#cwwlite-panel-' + key + ' .cwwlite-amount-badge' ).text( crypto + ' ' + cfg.symbol );
        } );
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        var networkKeys = Object.keys( networks );
        if ( networkKeys.length ) {
            activateNetwork( networkKeys[0] );
        }
        startCountdown();
    }

    $( document ).on( 'updated_checkout', init );
    $( document ).ready( init );

}( jQuery ) );
