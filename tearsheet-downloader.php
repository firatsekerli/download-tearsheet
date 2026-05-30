<?php
/**
 * Plugin Name: Tearsheet Downloader
 * Plugin URI:  https://github.com/firatsekerli/download-tearsheet
 * Description: Generates a branded PDF tearsheet for WooCommerce products via the Download button on the product page.
 * Version:     1.0.0
 * Author:      MKS Web Design
 * Text Domain: tearsheet-downloader
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'TEARSHEET_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEARSHEET_URL', plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Autoloader — mPDF installed via Composer
// ---------------------------------------------------------------------------
$autoload = TEARSHEET_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Tearsheet Downloader:</strong> Composer dependencies are missing. Run <code>composer install</code> inside the plugin folder.</p></div>';
    } );
    return;
}
require_once $autoload;

require_once TEARSHEET_DIR . 'includes/class-tearsheet-generator.php';
require_once TEARSHEET_DIR . 'includes/class-tearsheet-endpoint.php';

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------
add_action( 'init', [ 'Tearsheet_Endpoint', 'register' ] );
add_action( 'init', function () {
    $tmp = sys_get_temp_dir() . '/tearsheet_mpdf';
    if ( ! is_dir( $tmp ) ) {
        wp_mkdir_p( $tmp );
    }
} );
add_action( 'wp_enqueue_scripts', 'tearsheet_enqueue_assets' );

function tearsheet_enqueue_assets(): void {
    if ( ! is_product() ) {
        return;
    }
    wp_enqueue_script(
        'tearsheet-download',
        TEARSHEET_URL . 'assets/js/tearsheet-download.js',
        [],
        '1.0.0',
        true
    );
    wp_localize_script( 'tearsheet-download', 'TearsheetData', [
        'endpoint' => home_url( '/?wc-api=tearsheet&product_id=' ),
        'productId' => get_the_ID(),
    ] );
}

// Flush rewrite rules on activation / deactivation so the endpoint works.
register_activation_hook( __FILE__, function () {
    ob_start();
    Tearsheet_Endpoint::register();
    flush_rewrite_rules();
    ob_end_clean();
} );
register_deactivation_hook( __FILE__, function () {
    ob_start();
    flush_rewrite_rules();
    ob_end_clean();
} );
