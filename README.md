# Tearsheet Downloader — WordPress / WooCommerce Plugin

Adds a branded PDF tearsheet download to WooCommerce product pages.

Clicking the **Download** button on a product page streams a PDF that mirrors
the Quatrain tearsheet design: brand name at the top, product specs on the
left, featured image on the right, and contact info in the footer.

---

## Requirements

| Dependency | Version |
|---|---|
| WordPress | ≥ 6.0 |
| WooCommerce | ≥ 7.0 |
| PHP | ≥ 8.0 |
| Composer | any recent version |

---

## Installation

### 1 — Copy the plugin folder

Upload the `tearsheet-downloader/` folder to:

```
wp-content/plugins/tearsheet-downloader/
```

### 2 — Install Composer dependencies

Open a terminal inside the plugin folder and run:

```bash
cd wp-content/plugins/tearsheet-downloader
composer install --no-dev --optimize-autoloader
```

This installs **mPDF**, the PHP library used to render the PDF.

### 3 — Activate the plugin

Go to **WordPress Admin → Plugins** and activate **Tearsheet Downloader**.

### 4 — Flush rewrite rules (if needed)

Go to **Settings → Permalinks** and click **Save Changes**.

---

## Wiring up your Download button

The plugin automatically scans for the download button on each product page.
It looks for elements matching these CSS selectors (checked in order):

```
a.tearsheet-download
button.tearsheet-download
[data-action="tearsheet"]
.product-actions a[title*="ownload"]
.woocommerce-product-actions a[title*="ownload"]
a.product-download
```

If none match, it falls back to searching all links/buttons inside `.summary`
or `.product` whose visible text, `title`, or `aria-label` contains "download".

**Recommended:** add `class="tearsheet-download"` to your download button
so the selector is unambiguous, e.g.:

```html
<a href="#" class="tearsheet-download" title="Download tearsheet">
  <svg><!-- download icon --></svg>
  <span>DOWNLOAD</span>
</a>
```

---

## Customising the PDF content

### Brand / contact info

Open `includes/class-tearsheet-generator.php` and edit the constants near
the top of the class:

```php
private const BRAND_NAME   = 'Quatrain';
private const BRAND_EMAIL  = 'info@fournircollections.com';
private const BRAND_SITE   = 'www.fournircollections.com';
```

### Product attribute mapping

The generator reads standard WooCommerce attributes. The expected slugs are:

| Attribute slug | Tearsheet field |
|---|---|
| `width` / `pa_width` | Width |
| `depth` / `pa_depth` | Depth |
| `height` / `pa_height` | Height |
| `seat-height` / `pa_seat-height` | Seat Height |
| `material` / `pa_material` | Material |
| `finish` / `pa_finish` | Finish Shown |
| `com` / `pa_com` | COM (upholstery) |
| `col` / `pa_col` | COL (upholstery) |
| `details` / `pa_details` | Details |
| `lead-time` / `pa_lead-time` | Estimated Lead Time |

If your attribute slugs differ, update the `attr()` calls inside
`build_specs_html()` in the same file.

### Collection / brand name

The generator tries to read the brand from common brand taxonomies
(`product_brand`, `pwb-brand`, `yith_product_brand`) and meta keys
(`_brand`, `_collection`). If none are found it falls back to `BRAND_NAME`.

---

## How the download URL works

The plugin registers a WooCommerce API endpoint:

```
https://yoursite.com/?wc-api=tearsheet&product_id=<ID>
```

Visiting this URL streams a `.pdf` file directly to the browser with the
`Content-Disposition: attachment` header (forces download).

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Composer dependencies are missing" admin notice | Run `composer install` inside the plugin folder |
| Button click does nothing | Check browser console — the JS may not be finding your button; add `class="tearsheet-download"` to it |
| Blank / broken PDF | Enable `WP_DEBUG` and check `debug.log`; the mPDF temp dir (`/tmp/tearsheet_mpdf`) must be writable |
| Image not appearing in PDF | Ensure the featured image is accessible over HTTP/S from the server itself (some staging environments block loopback) |
