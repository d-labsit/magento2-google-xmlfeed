# Google Feed for Magento 2

**Spec-compliant Google Merchant Center (Google Shopping) product feed for Magento 2.**

One channel, done right. Streaming `XMLWriter` so memory stays flat regardless of
catalogue size. Store-view configurable. Built from the verified 2026 Google
Merchant [product data specification](https://support.google.com/merchants/answer/7052112),
not from memory.

- License: **MIT**
- Requires: PHP ≥ 8.3 · `magento/framework` ≥ 103 · `dlabsit/module-core` ^1.0
- Admin: **Stores → Configuration → Dlabsit → Google Feed** (or the Google Feed
  entry under the Dlabsit admin menu)

## What it exports

RSS 2.0 with the `xmlns:g="http://base.google.com/ns/1.0"` namespace. Each product
becomes an `<item>`; configurable variants each get their own `<item>` sharing an
`item_group_id`. Correctness rules (valid values, formats, character limits) live
in one place — `Model/Google/Spec.php` — so the spec can only drift in one file.

Key spec facts encoded (and verified):

- `availability` uses underscores: `in_stock`, `out_of_stock`, `preorder`, `backorder`
  (the legacy `in stock` with a space is **not** valid)
- `price` is `"99.00 EUR"` — number, single space, ISO 4217, period decimal
- `gtin` is dropped unless it passes the GS1 mod-10 check digit (8/12/13/14 digits)
- `identifier_exists` is set to `no` only when there is no brand, no gtin and no mpn
- character limits: id 50, title 150, description 5000, product_type 750, …

## Generating the feed

```bash
# all stores where the feed is enabled
bin/magento dlabsit:google-feed:generate

# a specific store (id or code), forced regardless of the enabled flag
bin/magento dlabsit:google-feed:generate --store=default
```

Output: `pub/media/googlefeed/<store-code>/<filename>` (default `google.xml`),
served at `<media-url>/googlefeed/<store-code>/google.xml`.

Cron generation and a public feed controller are part of phase 2.

## Development

```bash
composer install
composer ci      # phpcs (Magento2) + phpstan + phpunit
```
