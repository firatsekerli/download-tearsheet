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
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
        }

        .brand {
            text-align: center;
            font-family: serif;
            font-size: 26pt;
            font-weight: normal;
            font-variant: small-caps;
            letter-spacing: 2px;
            margin-bottom: 8mm;
        }

        .body-table { width: 100%; }

        .col-specs {
            width: 45%;
            vertical-align: top;
            padding-right: 14px;
        }

        .col-image {
            width: 55%;
            vertical-align: top;
            text-align: right;
        }

        .col-image img {
            max-width: 100%;
            max-height: 215mm;
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
            $specs_html .= $this->section( 'Details', $this->clean_richtext( $notes ) );
        }

        $sku_html = $sku ? $sku : '';
        $img_tag  = $image_url
            ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '" style="max-width:100%;max-height:230mm;">'
            : '';

        $s = 'color:#1a1a1a;font-family:sans-serif;';

        return <<<HTML
        <div style="position:fixed;bottom:25mm;right:18mm;width:55%;text-align:right;">
          {$img_tag}
        </div>

        <div class="brand">{$brand}</div>

        <div style="width:42%;">
          <p style="{$s}font-size:13pt;font-weight:bold;margin-bottom:0;">{$name}</p>
          <p style="{$s}font-size:13pt;font-weight:bold;margin-top:0;margin-bottom:5mm;">{$sku_html}</p>
          <p style="{$s}font-size:10pt;margin-bottom:5mm;">Available in custom sizes and finishes.</p>
          {$specs_html}
        </div>
        HTML;
    }

    /**
     * Cleans a rich-text ACF value for PDF output.
     *
     * Some fields (e.g. construction_notes) contain HTML links. We keep the
     * link's visible text but drop the href so long URLs don't overflow the
     * column and collide with the image. Line breaks are preserved.
     */
    private function clean_richtext( string $value ): string {
        // Preserve <br> as newlines before stripping tags.
        $value = preg_replace( '/<br\s*\/?>/i', "\n", $value );
        $value = wp_strip_all_tags( $value );
        $value = trim( $value );
        return nl2br( esc_html( $value ) );
    }

    private function section( string $title, string $body ): string {
        $base  = 'color:#1a1a1a;font-family:sans-serif;font-size:10pt;';
        $title_style = $base . 'font-weight:bold;margin-top:4mm;margin-bottom:0.5mm;';
        $body_style  = $base . 'line-height:1.4;margin-top:0;margin-bottom:1mm;overflow-wrap:break-word;word-wrap:break-word;';
        return '<p style="' . $title_style . '">' . esc_html( $title ) . '</p>'
             . '<p style="' . $body_style  . '">' . $body . '</p>';
    }
}
