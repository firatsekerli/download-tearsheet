<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the ?wc-api=tearsheet endpoint.
 */
class Tearsheet_Endpoint {

    public static function register(): void {
        add_action( 'woocommerce_api_tearsheet', [ __CLASS__, 'handle' ] );
    }

    public static function handle(): void {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_die( 'Missing product ID.', 400 );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_die( 'Product not found.', 404 );
        }

        try {
            $generator = new Tearsheet_Generator( $product );
            $generator->stream();
        } catch ( \Throwable $e ) {
            wp_die( esc_html( 'PDF generation failed: ' . $e->getMessage() ), 500 );
        }
    }
}
