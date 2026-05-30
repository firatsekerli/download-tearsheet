( function () {
    'use strict';

    /**
     * Finds the download button/icon on the WooCommerce product page and wires
     * it up to trigger a tearsheet PDF download.
     *
     * The selector targets:
     *  - <a> or <button> elements whose aria-label / title / text contains "download"
     *  - elements with a class or data attribute referencing "tearsheet" or "download"
     *
     * Adjust SELECTORS below if your theme uses a different class or markup.
     */
    var SELECTORS = [
        'a.tearsheet-download',
        'button.tearsheet-download',
        '[data-action="tearsheet"]',
        '.product-actions a[title*="ownload"]',
        '.woocommerce-product-actions a[title*="ownload"]',
        'a.product-download',
    ];

    function buildUrl() {
        return TearsheetData.endpoint + TearsheetData.productId;
    }

    function handleClick( e ) {
        e.preventDefault();
        window.location.href = buildUrl();
    }

    function init() {
        var found = false;

        SELECTORS.forEach( function ( selector ) {
            var els = document.querySelectorAll( selector );
            els.forEach( function ( el ) {
                el.addEventListener( 'click', handleClick );
                found = true;
            } );
        } );

        if ( ! found ) {
            // Fallback: search all links on the page whose visible text or
            // title attribute suggests a "download" action.
            var links = document.querySelectorAll( '.summary a, .summary button, .product a, .product button' );
            links.forEach( function ( el ) {
                var text  = ( el.textContent || '' ).toLowerCase();
                var title = ( el.getAttribute( 'title' ) || '' ).toLowerCase();
                var aria  = ( el.getAttribute( 'aria-label' ) || '' ).toLowerCase();

                if (
                    text.indexOf( 'download' ) !== -1 ||
                    title.indexOf( 'download' ) !== -1 ||
                    aria.indexOf( 'download' ) !== -1 ||
                    text.indexOf( 'tearsheet' ) !== -1
                ) {
                    el.addEventListener( 'click', handleClick );
                }
            } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
