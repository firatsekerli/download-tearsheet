<?php
defined( 'ABSPATH' ) || exit;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Builds and streams the tearsheet PDF for a given WooCommerce product.
 *
 * Data sources:
 *   - Brand (main title) : WooCommerce Brands taxonomy (product_brand)
 *   - Product name       : WC product title
 *   - SKU                : WC product SKU
 *   - All specs          : existing ACF fields (see field names below)
 *
 * ACF field names used:
 *   construction_notes   → Details
 *   material             → Material
 *   finish_shown         → Finish Shown
 *   dim_width            → Width
 *   dim_depth            → Depth
 *   dim_height           → Height
 *   dim_seat_height      → Seat Height
 */
class Tearsheet_Generator {

    private WC_Product $product;
    private int        $post_id;

    private const BRAND_EMAIL = 'info@fournircollections.com';
    private const BRAND_SITE  = 'www.fournircollections.com';

    public function __construct( WC_Product $product ) {
        $this->product = $product;
        $this->post_id = $product->get_id();
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public function stream(): void {
        $mpdf = $this->make_mpdf();
        $mpdf->WriteHTML( $this->css(), 1 );
        $mpdf->WriteHTML( $this->html(), 2 );

        $filename = sanitize_title( $this->product->get_name() ) . '-tearsheet.pdf';
        $mpdf->Output( $filename, 'D' );
        exit;
    }

    // ------------------------------------------------------------------
    // mPDF
    // ------------------------------------------------------------------

    private function make_mpdf(): Mpdf {
        $default_config      = ( new ConfigVariables() )->getDefaults();
        $default_font_config = ( new FontVariables() )->getDefaults();

        return new Mpdf( [
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 18,
            'margin_bottom' => 20,
            'margin_left'   => 18,
            'margin_right'  => 18,
            'fontDir'       => array_merge(
                $default_config['fontDir'],
                [ TEARSHEET_DIR . 'assets/fonts/' ]
            ),
            'fontdata'      => $default_font_config['fontdata'],
            'default_font'  => 'helvetica',
            'tempDir'       => sys_get_temp_dir() . '/tearsheet_mpdf',
        ] );
    }

    // ------------------------------------------------------------------
    // Data helpers
    // ------------------------------------------------------------------

    private function f( string $field_name ): string {
        if ( ! function_exists( 'get_field' ) ) {
            return '';
        }
        return trim( (string) get_field( $field_name, $this->post_id ) );
    }

    private function image_url(): string {
        $id  = $this->product->get_image_id();
        $src = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
        return $src ? $src[0] : '';
    }

    /** Brand name from WooCommerce Brands taxonomy. */
    private function brand(): string {
        $terms = get_the_terms( $this->post_id, 'product_brand' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            return esc_html( $terms[0]->name );
        }
        return '';
    }

    // ------------------------------------------------------------------
    // CSS
    // ------------------------------------------------------------------

    private function css(): string {
        return <<<CSS
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: helvetica, sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
        }

        .brand {
            text-align: center;
            font-size: 28pt;
            font-weight: normal;
            letter-spacing: 4px;
            font-variant: small-caps;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }

        .body-table {
            width: 100%;
        }

        .col-specs {
            width: 42%;
            vertical-align: top;
            padding-right: 14px;
        }

        .col-image {
            width: 58%;
            vertical-align: top;
            text-align: right;
        }

        .col-image img {
            max-width: 100%;
            max-height: 210mm;
        }

        .product-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .product-sku {
            font-size: 11pt;
            font-weight: normal;
            margin-left: 14px;
        }

        .product-tagline {
            font-style: italic;
            font-size: 9pt;
            color: #555;
            margin-top: 4px;
            margin-bottom: 16px;
        }

        .section-title {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 12px;
            margin-bottom: 3px;
        }

        .section-body {
            font-size: 10pt;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            font-size: 9pt;
            color: #555;
            border-top: 1px solid #333;
            padding-top: 6px;
            margin-top: 16px;
        }
        CSS;
    }

    // ------------------------------------------------------------------
    // HTML
    // ------------------------------------------------------------------

    private function html(): string {
        $name      = esc_html( $this->product->get_name() );
        $sku       = esc_html( $this->product->get_sku() );
        $brand     = $this->brand();
        $image_url = $this->image_url();

        // ACF fields.
        $notes       = $this->f( 'construction_notes' );
        $material    = $this->f( 'material' );
        $finish      = $this->f( 'finish_shown' );
        $width       = $this->f( 'dim_width' );
        $depth       = $this->f( 'dim_depth' );
        $height      = $this->f( 'dim_height' );
        $seat_height = $this->f( 'dim_seat_height' );

        $specs_html = '';

        $dim_lines = '';
        if ( $width )       { $dim_lines .= 'Width: '       . esc_html( $width )       . '<br>'; }
        if ( $depth )       { $dim_lines .= 'Depth: '       . esc_html( $depth )       . '<br>'; }
        if ( $height )      { $dim_lines .= 'Height: '      . esc_html( $height )      . '<br>'; }
        if ( $seat_height ) { $dim_lines .= 'Seat Height: ' . esc_html( $seat_height ) . '<br>'; }
        if ( $dim_lines ) {
            $specs_html .= $this->section( 'Dimensions', $dim_lines );
        }

        if ( $material ) {
            $specs_html .= $this->section( 'Material', nl2br( esc_html( $material ) ) );
        }

        if ( $finish ) {
            $specs_html .= $this->section( 'Finish Shown', esc_html( $finish ) );
        }

        if ( $notes ) {
            $specs_html .= $this->section( 'Details', nl2br( esc_html( $notes ) ) );
        }

        $sku_html = $sku ? '<span class="product-sku">' . $sku . '</span>' : '';
        $img_tag  = $image_url
            ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '">'
            : '';

        $email = esc_html( self::BRAND_EMAIL );
        $site  = esc_html( self::BRAND_SITE );

        return <<<HTML
        <div class="brand">{$brand}</div>

        <table class="body-table">
          <tr>
            <td class="col-specs">
              <p class="product-name">{$name}{$sku_html}</p>
              <p class="product-tagline">Available in custom sizes and finishes.</p>
              {$specs_html}
            </td>
            <td class="col-image">
              {$img_tag}
            </td>
          </tr>
        </table>

        <div class="footer">
          {$email} &nbsp;&nbsp;|&nbsp;&nbsp; {$site}
        </div>
        HTML;
    }

    private function section( string $title, string $body ): string {
        return '<p class="section-title">' . esc_html( $title ) . '</p>'
             . '<p class="section-body">' . $body . '</p>';
    }
}
