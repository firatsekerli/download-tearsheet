<?php
defined( 'ABSPATH' ) || exit;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Builds and streams the tearsheet PDF for a given WooCommerce product.
 *
 * PDF layout mirrors the Quatrain tearsheet design:
 *  - Centered brand/collection name at top
 *  - Two-column body: specs on the left, product image on the right
 *  - Footer with contact email and website
 */
class Tearsheet_Generator {

    private WC_Product $product;

    // Attribute slug → tearsheet label mapping.
    // Adjust slugs to match your WooCommerce attribute names.
    private const ATTR_MAP = [
        'width'           => 'Width',
        'depth'           => 'Depth',
        'height'          => 'Height',
        'seat-height'     => 'Seat Height',
        'seat_height'     => 'Seat Height',
        'material'        => null, // handled as a dedicated section
        'finish'          => null, // handled as a dedicated section
        'pa_width'        => 'Width',
        'pa_depth'        => 'Depth',
        'pa_height'       => 'Height',
        'pa_seat-height'  => 'Seat Height',
        'pa_seat_height'  => 'Seat Height',
        'pa_material'     => null,
        'pa_finish'       => null,
    ];

    // Brand / contact info — edit to match your site.
    private const BRAND_NAME   = 'Quatrain';
    private const BRAND_EMAIL  = 'info@fournircollections.com';
    private const BRAND_SITE   = 'www.fournircollections.com';

    public function __construct( WC_Product $product ) {
        $this->product = $product;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public function stream(): void {
        $mpdf = $this->make_mpdf();
        $mpdf->WriteHTML( $this->css(), 1 );
        $mpdf->WriteHTML( $this->html(), 2 );

        $filename = sanitize_title( $this->product->get_name() ) . '-tearsheet.pdf';
        $mpdf->Output( $filename, 'D' ); // 'D' = force download
        exit;
    }

    // ------------------------------------------------------------------
    // mPDF initialisation
    // ------------------------------------------------------------------

    private function make_mpdf(): Mpdf {
        $default_config  = ( new ConfigVariables() )->getDefaults();
        $font_dirs       = $default_config['fontDir'];

        $default_font_config = ( new FontVariables() )->getDefaults();
        $font_data           = $default_font_config['fontdata'];

        return new Mpdf( [
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'margin_top'        => 18,
            'margin_bottom'     => 20,
            'margin_left'       => 18,
            'margin_right'      => 18,
            'fontDir'           => array_merge( $font_dirs, [ TEARSHEET_DIR . 'assets/fonts/' ] ),
            'fontdata'          => $font_data,
            'default_font'      => 'helvetica',
            'tempDir'           => sys_get_temp_dir() . '/tearsheet_mpdf',
        ] );
    }

    // ------------------------------------------------------------------
    // Data helpers
    // ------------------------------------------------------------------

    /** Returns a readable attribute value or empty string. */
    private function attr( string ...$slugs ): string {
        foreach ( $slugs as $slug ) {
            // Try global PA attribute first.
            $value = $this->product->get_attribute( $slug );
            if ( $value !== '' ) {
                return $value;
            }
            // Try without pa_ prefix.
            $alt = str_replace( 'pa_', '', $slug );
            $value = $this->product->get_attribute( $alt );
            if ( $value !== '' ) {
                return $value;
            }
        }
        return '';
    }

    /** Returns the product's featured image URL (full size). */
    private function image_url(): string {
        $id = $this->product->get_image_id();
        if ( ! $id ) {
            return '';
        }
        $src = wp_get_attachment_image_src( $id, 'large' );
        return $src ? $src[0] : '';
    }

    /** Resolves the collection / brand name from a custom taxonomy or meta. */
    private function collection(): string {
        // Try product_brand taxonomy (used by many WC brand plugins).
        foreach ( [ 'product_brand', 'pwb-brand', 'yith_product_brand' ] as $tax ) {
            $terms = get_the_terms( $this->product->get_id(), $tax );
            if ( $terms && ! is_wp_error( $terms ) ) {
                return esc_html( $terms[0]->name );
            }
        }
        // Fallback: custom meta _brand or _collection.
        foreach ( [ '_brand', '_collection', 'brand', 'collection' ] as $key ) {
            $v = get_post_meta( $this->product->get_id(), $key, true );
            if ( $v ) {
                return esc_html( $v );
            }
        }
        return self::BRAND_NAME;
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
            line-height: 1.5;
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
        $name       = esc_html( $this->product->get_name() );
        $sku        = esc_html( $this->product->get_sku() );
        $collection = $this->collection();
        $image_url  = $this->image_url();

        // Dimensions
        $width       = $this->attr( 'pa_width',       'width' );
        $depth       = $this->attr( 'pa_depth',       'depth' );
        $height      = $this->attr( 'pa_height',      'height' );
        $seat_height = $this->attr( 'pa_seat-height', 'pa_seat_height', 'seat-height', 'seat_height' );

        // Specs
        $material   = $this->attr( 'pa_material',     'material' );
        $finish     = $this->attr( 'pa_finish',        'finish', 'pa_finish-shown', 'finish-shown', 'finish_shown' );
        $com        = $this->attr( 'pa_com',           'com' );
        $col        = $this->attr( 'pa_col',           'col' );
        $details    = $this->attr( 'pa_details',       'details' );
        $lead_time  = $this->attr( 'pa_lead-time',     'pa_lead_time', 'lead-time', 'lead_time', 'estimated-lead-time' );

        // Fall back: use short description for details if attribute is empty.
        if ( $details === '' ) {
            $details = wp_strip_all_tags( $this->product->get_short_description() );
        }

        // Build dimensions block.
        $dim_lines = '';
        if ( $width )       { $dim_lines .= 'Width: ' . esc_html( $width ) . '<br>'; }
        if ( $depth )       { $dim_lines .= 'Depth: ' . esc_html( $depth ) . '<br>'; }
        if ( $height )      { $dim_lines .= 'Height: ' . esc_html( $height ) . '<br>'; }
        if ( $seat_height ) { $dim_lines .= 'Seat Height: ' . esc_html( $seat_height ) . '<br>'; }

        // Build upholstery block.
        $upholstery = '';
        if ( $com ) { $upholstery .= 'COM: ' . esc_html( $com ) . '<br>'; }
        if ( $col ) { $upholstery .= 'COL: ' . esc_html( $col ) . '<br>'; }

        // Image tag.
        $img_tag = $image_url
            ? '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '">'
            : '';

        $specs_html = '';

        if ( $dim_lines ) {
            $specs_html .= '<p class="section-title">Dimensions</p><p class="section-body">' . $dim_lines . '</p>';
        }
        if ( $material ) {
            $specs_html .= '<p class="section-title">Material</p><p class="section-body">' . esc_html( $material ) . '</p>';
        }
        if ( $finish ) {
            $specs_html .= '<p class="section-title">Finish Shown</p><p class="section-body">' . esc_html( $finish ) . '</p>';
        }
        if ( $upholstery ) {
            $specs_html .= '<p class="section-title">Upholstery</p><p class="section-body">' . $upholstery . '</p>';
        }
        if ( $details ) {
            $specs_html .= '<p class="section-title">Details</p><p class="section-body">' . esc_html( $details ) . '</p>';
        }
        if ( $lead_time ) {
            $specs_html .= '<p class="section-title">Estimated Lead Time</p><p class="section-body">' . esc_html( $lead_time ) . '</p>';
        }

        $sku_html = $sku ? '<span class="product-sku">' . $sku . '</span>' : '';

        return <<<HTML
        <div class="brand">{$collection}</div>

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
          {$this->esc_html_const( self::BRAND_EMAIL )} &nbsp;&nbsp;|&nbsp;&nbsp; {$this->esc_html_const( self::BRAND_SITE )}
        </div>
        HTML;
    }

    private function esc_html_const( string $v ): string {
        return esc_html( $v );
    }
}
