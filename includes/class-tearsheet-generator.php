<?php
defined( 'ABSPATH' ) || exit;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Builds and streams the tearsheet PDF for a given WooCommerce product.
 *
 * ACF field names used:
 *   construction_notes   → Details
 *   material             → Material
 *   finish_shown         → Finish Shown
 *   upholstery_com       → Upholstery COM
 *   upholstery_col       → Upholstery COL
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

        $email = esc_html( self::BRAND_EMAIL );
        $site  = esc_html( self::BRAND_SITE );

        $mpdf->SetHTMLFooter( <<<HTML
        <table width="100%" style="padding-top:6px;">
          <tr>
            <td style="text-align:center;font-family:serif;font-size:9pt;color:#444;">
              {$email} &nbsp;&nbsp;|&nbsp;&nbsp; {$site}
            </td>
          </tr>
        </table>
        HTML );

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
            'margin_top'    => 20,
            'margin_bottom' => 22,
            'margin_left'   => 20,
            'margin_right'  => 20,
            'margin_footer' => 8,
            'fontDir'       => array_merge(
                $default_config['fontDir'],
                [ TEARSHEET_DIR . 'assets/fonts/' ]
            ),
            'fontdata'      => $default_font_config['fontdata'],
            'default_font'  => 'serif',
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
        * { box-sizing: border-box; margin: 0; padding: 0; color: #1a1a1a; }

        body {
            font-family: sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
        }

        a, a:link, a:visited {
            color: #1a1a1a !important;
            text-decoration: none !important;
        }

        .brand {
            text-align: center;
            font-family: serif;
            font-size: 26pt;
            font-weight: normal;
            font-variant: small-caps;
            letter-spacing: 2px;
            margin-bottom: 22px;
        }

        .body-table {
            width: 100%;
        }

        .col-specs {
            width: 40%;
            vertical-align: top;
            padding-right: 12px;
        }

        .col-image {
            width: 60%;
            vertical-align: bottom;
            text-align: right;
        }

        .col-image img {
            max-width: 100%;
            max-height: 210mm;
        }

        .product-header {
            font-size: 16pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .product-sku {
            font-size: 16pt;
            font-weight: normal;
            color: #1a1a1a;
            margin-left: 10px;
        }

        .product-tagline {
            font-size: 10.5pt;
            color: #1a1a1a;
            margin-bottom: 16px;
        }

        .section-title {
            font-weight: bold;
            font-size: 10pt;
            color: #1a1a1a;
            margin-top: 14px;
            margin-bottom: 6px;
        }

        .section-body {
            font-size: 10pt;
            color: #1a1a1a;
            line-height: 1.6;
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

        $notes       = $this->f( 'construction_notes' );
        $material    = $this->f( 'material' );
        $finish      = $this->f( 'finish_shown' );
        $width       = $this->f( 'dim_width' );
        $depth       = $this->f( 'dim_depth' );
        $height      = $this->f( 'dim_height' );
        $seat_height = $this->f( 'dim_seat_height' );
        $uph_com     = $this->f( 'upholstery_com' );
        $uph_col     = $this->f( 'upholstery_col' );

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

        $uph_lines = '';
        if ( $uph_com ) { $uph_lines .= 'COM: ' . esc_html( $uph_com ) . '<br>'; }
        if ( $uph_col ) { $uph_lines .= 'COL: ' . esc_html( $uph_col ) . '<br>'; }
        if ( $uph_lines ) {
            $specs_html .= $this->section( 'Upholstery', $uph_lines );
        }

        if ( $notes ) {
            $specs_html .= $this->section( 'Details', nl2br( esc_html( $notes ) ) );
        }

        $sku_html = $sku ? ' <span class="product-sku">' . $sku . '</span>' : '';
        $img_tag  = $image_url
            ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '">'
            : '';

        return <<<HTML
        <div class="brand">{$brand}</div>

        <table class="body-table">
          <tr>
            <td class="col-specs">
              <p class="product-header">{$name}{$sku_html}</p>
              <p class="product-tagline">Available in custom sizes and finishes.</p>
              {$specs_html}
            </td>
            <td class="col-image">
              {$img_tag}
            </td>
          </tr>
        </table>
        HTML;
    }

    private function section( string $title, string $body ): string {
        return '<p class="section-title">' . esc_html( $title ) . '</p>'
             . '<p class="section-body">' . $body . '</p>';
    }
}
