/**
 * Crypto Wallet Payment Lite — WooCommerce Block Checkout
 *
 * Matches the classic checkout UI:
 *  - Pill-style network selector with coin icons
 *  - Rate countdown bar
 *  - "Manual Transfer" method card
 *  - Address box with monospace display + copy button
 *  - TX ID input
 */

const { registerPaymentMethod }                  = window.wc.wcBlocksRegistry;
const { getSetting }                             = window.wc.wcSettings;
const { createElement: el, useState, useEffect } = window.wp.element;

const settings    = getSetting( 'cwwlite_crypto_data', {} );
const networks    = settings.networks    || {};
const wallets     = settings.wallets     || {};
const amounts     = settings.amounts     || {};
const imgUrl      = settings.imgUrl      || '';
const rateLockSec = parseInt( settings.rateLockSec || 0, 10 );

// ── Helpers ────────────────────────────────────────────────────────────────

function formatTime( sec ) {
    const m = Math.floor( sec / 60 );
    const s = sec % 60;
    return m + ':' + ( s < 10 ? '0' : '' ) + s;
}

// ── Label (shown next to the payment method radio) ─────────────────────────

const Label = () => el( 'span', null, settings.title || 'Pay with Crypto (Lite)' );

// ── Countdown hook ─────────────────────────────────────────────────────────

function useCountdown( initialSec ) {
    const [ timeLeft, setTimeLeft ] = useState( initialSec );
    useEffect( () => {
        if ( ! initialSec ) return;
        const id = setInterval( () => setTimeLeft( t => Math.max( t - 1, 0 ) ), 1000 );
        return () => clearInterval( id );
    }, [ initialSec ] );
    return timeLeft;
}

// ── CopyButton ─────────────────────────────────────────────────────────────

function CopyButton( { text } ) {
    const [ copied, setCopied ] = useState( false );

    function handleCopy() {
        const flash = () => { setCopied( true ); setTimeout( () => setCopied( false ), 2000 ); };
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( flash ).catch( () => { legacyCopy( text ); flash(); } );
        } else {
            legacyCopy( text );
            flash();
        }
    }

    return el( 'button',
        { type: 'button', className: 'cwwlite-copy-btn' + ( copied ? ' cwwlite-copied' : '' ), onClick: handleCopy },
        el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', width: '13', height: '13', viewBox: '0 0 24 24',
                     fill: 'none', stroke: 'currentColor', strokeWidth: '2',
                     strokeLinecap: 'round', strokeLinejoin: 'round' },
            copied
                ? el( 'polyline', { points: '20 6 9 17 4 12' } )
                : [ el( 'rect', { key: 'r', x: '9', y: '9', width: '13', height: '13', rx: '2', ry: '2' } ),
                    el( 'path', { key: 'p', d: 'M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1' } ) ]
        ),
        copied ? ' Copied!' : ' Copy'
    );
}

function legacyCopy( text ) {
    const ta = document.createElement( 'textarea' );
    ta.value = text;
    Object.assign( ta.style, { position: 'fixed', top: '-9999px', left: '-9999px' } );
    document.body.appendChild( ta );
    ta.select();
    try { document.execCommand( 'copy' ); } catch ( e ) {}
    document.body.removeChild( ta );
}

// ── Main Content ────────────────────────────────────────────────────────────

const Content = ( { eventRegistration, emitResponse } ) => {
    const { onPaymentSetup } = eventRegistration;

    const networkKeys = Object.keys( networks );
    const firstKey    = networkKeys[0] || '';

    const [ selectedNetwork, setSelectedNetwork ] = useState( firstKey );
    const [ txid, setTxid ]                       = useState( '' );
    const timeLeft = useCountdown( rateLockSec );

    useEffect( () => {
        const unsub = onPaymentSetup( () => {
            if ( ! selectedNetwork ) {
                return { type: emitResponse.responseTypes.ERROR, message: 'Please select a cryptocurrency network.' };
            }
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: { paymentMethodData: { cwwlite_network: selectedNetwork, cwwlite_txid: txid } },
            };
        } );
        return unsub;
    }, [ selectedNetwork, txid, onPaymentSetup ] );

    function handleNetworkChange( key ) {
        setSelectedNetwork( key );
        setTxid( '' );
    }

    if ( ! networkKeys.length ) {
        return el( 'p', { className: 'cwwlite-no-networks' }, 'No crypto networks configured. Please contact the store owner.' );
    }

    const cfg    = networks[ selectedNetwork ] || {};
    const wallet = wallets[ selectedNetwork ]  || '';
    const amount = amounts[ selectedNetwork ]  || '';

    return el( 'div', { className: 'cwwlite-checkout-wrap' },

        // Description
        settings.description ? el( 'p', { className: 'cwwlite-description' }, settings.description ) : null,

        // SELECT NETWORK label
        el( 'p', { className: 'cwwlite-select-label' }, 'Select network:' ),

        // Pill selector
        el( 'div', { className: 'cwwlite-pill-selector', role: 'group' },
            networkKeys.map( key => {
                const net = networks[ key ];
                const isActive = key === selectedNetwork;
                return el( 'button', {
                        key,
                        type:           'button',
                        className:      'cwwlite-pill' + ( isActive ? ' cwwlite-pill-active' : '' ),
                        'aria-pressed': String( isActive ),
                        onClick:        () => handleNetworkChange( key ),
                    },
                    el( 'img', {
                        src:       imgUrl + net.icon + '.svg',
                        alt:       net.symbol,
                        width:     '20',
                        height:    '20',
                        className: 'cwwlite-pill-icon',
                        onError:   ( e ) => { e.target.style.display = 'none'; },
                    } ),
                    el( 'span', { className: 'cwwlite-pill-label' }, net.label )
                );
            } )
        ),

        // Rate countdown bar
        rateLockSec > 0 ? el( 'div', { className: 'cwwlite-rate-bar' },
            el( 'span', { className: 'cwwlite-rate-bar-icon' }, '⏱' ),
            'Rate valid for: ',
            el( 'span', { className: 'cwwlite-countdown cwwlite-countdown-highlight' },
                timeLeft > 0 ? formatTime( timeLeft ) : 'Refreshing…'
            )
        ) : null,

        // HOW WOULD YOU LIKE TO PAY label
        el( 'p', { className: 'cwwlite-pay-method-label' }, 'How would you like to pay?' ),

        // Manual Transfer card
        el( 'div', { className: 'cwwlite-method-cards' },
            el( 'div', { className: 'cwwlite-method-card cwwlite-method-card-active' },
                el( 'span', { className: 'cwwlite-method-icon' }, '📋' ),
                el( 'strong', null, 'Manual Transfer' ),
                el( 'span', { className: 'cwwlite-method-sub' }, 'Copy address & send' )
            )
        ),

        // Address box
        el( 'div', { className: 'cwwlite-address-box' },
            el( 'div', { className: 'cwwlite-address-row' },
                el( 'span', { className: 'cwwlite-address-label' }, 'Send to:' ),
                amount ? el( 'span', { className: 'cwwlite-amount-badge' }, amount + ' ' + cfg.symbol ) : null
            ),
            el( 'div', { className: 'cwwlite-address-display' },
                el( 'code', { className: 'cwwlite-wallet-address' }, wallet ),
                el( CopyButton, { text: wallet } )
            ),
            settings.exchange_rate_note
                ? el( 'p', { className: 'cwwlite-rate-note' }, settings.exchange_rate_note )
                : null
        ),

        // TX ID input
        el( 'div', { className: 'cwwlite-txid-wrap' },
            el( 'label', { htmlFor: 'cwwlite-block-txid-' + selectedNetwork, className: 'cwwlite-txid-label' },
                'Transaction ID / Hash',
                el( 'span', { className: 'cwwlite-optional' }, ' (optional)' )
            ),
            el( 'input', {
                id:           'cwwlite-block-txid-' + selectedNetwork,
                type:         'text',
                className:    'cwwlite-txid-input',
                value:        txid,
                onChange:     ( e ) => setTxid( e.target.value ),
                placeholder:  'Paste your TX hash here — you can also submit later',
                autoComplete: 'off',
            } )
        )
    );
};

// ── Register ───────────────────────────────────────────────────────────────

registerPaymentMethod( {
    name:           'cwwlite_crypto',
    label:          el( Label ),
    content:        el( Content ),
    edit:           el( Content ),
    canMakePayment: () => true,
    ariaLabel:      settings.title || 'Pay with Crypto (Lite)',
    supports: {
        features: settings.supports || [ 'products' ],
    },
} );
