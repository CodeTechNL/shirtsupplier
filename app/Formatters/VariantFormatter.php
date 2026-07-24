<?php

namespace App\Formatters;

class VariantFormatter
{
    /** @param array<string, mixed> $data */
    public function __construct(
        protected array $data,
    ) {}

    /**
     * Map the Lightspeed variant resource onto the local database columns.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return [
            'product_id' => $this->productId(),
            'title' => $this->data['title'] ?? null,
            'sku' => $this->data['sku'] ?? null,
            'ean' => $this->data['ean'] ?? null,
            'article_code' => $this->data['articleCode'] ?? null,
            'is_default' => $this->data['isDefault'] ?? false,
            'sort_order' => $this->data['sortOrder'] ?? 0,
            'price_excl' => $this->data['priceExcl'] ?? 0,
            'price_incl' => $this->data['priceIncl'] ?? 0,
            'old_price_excl' => $this->data['oldPriceExcl'] ?? 0,
            'old_price_incl' => $this->data['oldPriceIncl'] ?? 0,
            'stock_tracking' => $this->data['stockTracking'] ?? null,
            'stock_level' => $this->data['stockLevel'] ?? 0,
            'weight' => $this->data['weight'] ?? 0,
            'image' => $this->data['image'] ?? null,
        ];
    }

    /**
     * Resolve the parent product id from the nested resource link.
     */
    protected function productId(): int|string|null
    {
        $product = $this->data['product'] ?? null;

        if (is_array($product)) {
            return $product['resource']['id'] ?? $product['id'] ?? null;
        }

        return $product;
    }
}
