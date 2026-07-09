# Google Feed for Magento 2

**Spec-compliant Google Merchant Center (Google Shopping) product feed for Magento 2.**

One channel, done right. Built from the verified 2026 Google Merchant
[product data specification](https://support.google.com/merchants/answer/7052112),
not from memory. Streaming `XMLWriter` so memory stays flat regardless of catalogue
size. Everything is store-view configurable from the admin.

- License: **MIT**
- Requires: PHP ≥ 8.3 · `magento/framework` ≥ 103 · `dlabsit/module-core` ^1.0
- Admin: **Stores → Configuration → Dlabsit → Google Feed**

## What it exports

RSS 2.0 with the `xmlns:g="http://base.google.com/ns/1.0"` namespace. Each product
is an `<item>`; configurable variants each get their own item sharing an
`item_group_id`. All correctness rules (valid values, formats, character limits)
live in one place — `Model/Google/Spec.php` — so the spec can only drift in one file.

Supported attributes: `id`, `title`, `description`, `link`, `image_link`,
`additional_image_link`, `availability`, `price`, `sale_price` (+ effective date),
`condition`, `brand`, `gtin` (check-digit validated), `mpn`, `identifier_exists`,
`google_product_category`, `product_type`, `item_group_id`, `color`, `size`,
`gender`, `age_group`, `shipping`, `shipping_weight`, `product_length/width/height`,
`custom_label_0…4`, `product_detail`, `product_highlight`.

Key spec facts encoded: `availability` uses underscores (`in_stock`, not `in stock`);
`price` is `"99.00 EUR"`; invalid GTINs are dropped; `identifier_exists=no` only when
there is no brand, gtin **and** mpn.

## Google Product Category — the admin-native way

Set a **Google Product Category** directly on a Magento category
(*Catalog → Categories*). It is inherited by every product underneath, and can be
overridden per product (a field on the product edit page). Resolution order:

1. product-level override attribute
2. nearest category up the tree with a value
3. the store-wide default (config)

## Custom labels, product details & fitment

For auto parts (and anything with fitment), map product attributes to
`custom_label_0…4` for campaign segmentation (vehicle make, price tier, margin,
position), and select attributes to emit as structured `product_detail` entries
(e.g. "Vehicle: VW Golf 6", "Position: Left"). Fitment in the title/description is
reinforced by these structured fields.

## Generating the feed

Three ways, all store-view aware:

```bash
# CLI — all enabled stores, or a specific store (id or code)
bin/magento dlabsit:google-feed:generate
bin/magento dlabsit:google-feed:generate --store=default
```

- **Cron** — runs on the crontab expression in the config (default daily 23:30).
- **Admin** — a *Generate Now* button on the config page.

Output: `pub/media/googlefeed/<store-code>/<filename>` (default `google.xml`),
served at the stable per-store URL **`https://<store>/googlefeed`** (generated on
demand if missing) and directly via the media URL.

## Development

```bash
composer install
composer ci      # phpcs (Magento2) + phpstan (level 4) + phpunit
```

CI (GitHub Actions) runs phpcs and the pure-PHP unit tests without Magento
credentials; phpstan runs against a Magento install (needs `magento/framework`).
