<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Google;

/**
 * Streams a Google Merchant RSS 2.0 document to a file with XMLWriter, so
 * memory stays flat regardless of catalogue size.
 *
 * Structure (verified, see {@see Spec}):
 *   <rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
 *     <channel><title/><link/><description/>
 *       <item>
 *         <title/><link/><description/>   (plain RSS)
 *         <g:id/> <g:image_link/> …        (Google namespace)
 *       </item>
 *     </channel>
 *   </rss>
 */
class FeedWriter
{
    private \XMLWriter $xml;

    public function open(
        string $filePath,
        string $channelTitle,
        string $channelLink,
        string $channelDescription
    ): void {
        $this->xml = new \XMLWriter();
        $this->xml->openUri($filePath);
        $this->xml->setIndent(true);
        $this->xml->setIndentString('  ');
        $this->xml->startDocument('1.0', 'UTF-8');

        $this->xml->startElement('rss');
        $this->xml->writeAttribute('version', '2.0');
        $this->xml->writeAttribute('xmlns:' . Spec::NAMESPACE_PREFIX, Spec::NAMESPACE_URI);
        $this->xml->startElement('channel');
        $this->cdata('title', $channelTitle);
        $this->plain('link', $channelLink);
        $this->cdata('description', $channelDescription);
    }

    public function writeItem(Item $item): void
    {
        $this->xml->startElement('item');
        try {
            // Plain RSS elements (no g: prefix).
            $this->cdata('title', $item->title);
            $this->plain('link', $item->link);
            $this->cdata('description', $item->description);

            // Google namespace elements.
            $this->g('id', $item->id);
            // image_link is required, but an empty tag is never valid — omit it
            // so the product is reported as "missing image_link" rather than
            // carrying a bogus empty value.
            if ($item->imageLink !== '') {
                $this->g('image_link', $item->imageLink);
            }
            foreach ($item->additionalImageLinks as $img) {
                $this->g('additional_image_link', $img);
            }
            $this->g('availability', $item->availability);
            $this->g('price', $item->price);
            if ($item->salePrice !== null) {
                $this->g('sale_price', $item->salePrice);
                if ($item->salePriceEffectiveDate !== null) {
                    $this->g('sale_price_effective_date', $item->salePriceEffectiveDate);
                }
            }
            $this->g('condition', $item->condition);

            if ($item->brand !== null) {
                $this->gCdata('brand', $item->brand);
            }
            if ($item->gtin !== null) {
                $this->g('gtin', $item->gtin);
            }
            if ($item->mpn !== null) {
                $this->gCdata('mpn', $item->mpn);
            }
            if (!$item->identifierExists) {
                $this->g('identifier_exists', 'no');
            }

            if ($item->googleProductCategory !== null) {
                $this->gCdata('google_product_category', $item->googleProductCategory);
            }
            if ($item->productType !== null) {
                $this->gCdata('product_type', $item->productType);
            }
            if ($item->itemGroupId !== null) {
                $this->g('item_group_id', $item->itemGroupId);
            }
            if ($item->color !== null) {
                $this->gCdata('color', $item->color);
            }
            if ($item->size !== null) {
                $this->gCdata('size', $item->size);
            }
            if ($item->gender !== null) {
                $this->g('gender', $item->gender);
            }
            if ($item->ageGroup !== null) {
                $this->g('age_group', $item->ageGroup);
            }
            if ($item->shippingWeight !== null) {
                $this->g('shipping_weight', $item->shippingWeight);
            }
            if ($item->productLength !== null) {
                $this->g('product_length', $item->productLength);
            }
            if ($item->productWidth !== null) {
                $this->g('product_width', $item->productWidth);
            }
            if ($item->productHeight !== null) {
                $this->g('product_height', $item->productHeight);
            }

            foreach ($item->customLabels as $index => $value) {
                if ($value !== '') {
                    $this->gCdata('custom_label_' . $index, $value);
                }
            }

            foreach ($item->productHighlights as $highlight) {
                $this->gCdata('product_highlight', $highlight);
            }

            foreach ($item->productDetails as $detail) {
                $this->xml->startElement(Spec::NAMESPACE_PREFIX . ':product_detail');
                $this->gCdata('section_name', $detail['section']);
                $this->gCdata('attribute_name', $detail['name']);
                $this->gCdata('attribute_value', $detail['value']);
                $this->xml->endElement();
            }

            if ($item->shipping !== null) {
                $this->xml->startElement(Spec::NAMESPACE_PREFIX . ':shipping');
                $this->g('country', $item->shipping['country']);
                if ($item->shipping['service'] !== '') {
                    $this->g('service', $item->shipping['service']);
                }
                $this->g('price', $item->shipping['price']);
                $this->xml->endElement();
            }
        } finally {
            $this->xml->endElement(); // item
        }
    }

    public function close(): void
    {
        $this->xml->endElement(); // channel
        $this->xml->endElement(); // rss
        $this->xml->endDocument();
        $this->xml->flush();
    }

    // --- element helpers ---

    private function plain(string $name, string $value): void
    {
        $this->xml->writeElement($name, $this->sanitizeText($value));
    }

    private function cdata(string $name, string $value): void
    {
        $this->xml->startElement($name);
        $this->xml->writeCdata($this->sanitizeCdata($value));
        $this->xml->endElement();
    }

    private function g(string $name, string $value): void
    {
        $this->xml->writeElement(Spec::NAMESPACE_PREFIX . ':' . $name, $this->sanitizeText($value));
    }

    private function gCdata(string $name, string $value): void
    {
        $this->xml->startElement(Spec::NAMESPACE_PREFIX . ':' . $name);
        $this->xml->writeCdata($this->sanitizeCdata($value));
        $this->xml->endElement();
    }

    /**
     * Strip characters illegal in XML 1.0 (control chars below U+0020 except
     * tab/LF/CR) and repair invalid UTF-8, so a single bad byte in one product
     * can't corrupt the whole feed.
     */
    private function sanitizeText(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $previous = set_error_handler(static fn (): bool => true);
            try {
                $converted = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            } finally {
                set_error_handler($previous);
            }
            $value = $converted !== false ? $converted : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    /** As {@see sanitizeText}, plus break a literal "]]>" that would close CDATA early. */
    private function sanitizeCdata(string $value): string
    {
        return str_replace(']]>', ']] >', $this->sanitizeText($value));
    }
}
