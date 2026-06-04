<?php

namespace App\Formatters;

use App\Models\Product;

class ProductFormatter
{
    /** @var array<string, mixed> */
    protected array $translations = [];

    public function __construct(
        protected array $data,
        protected Product $product,
    ) {}

    public function setLanguageAttribute(string $language, string $property, ?string $value): static
    {
        if (! isset($this->translations[$property])) {
            $this->translations[$property] = $this->product->getAttributeValue($property) ?? [];
        }

        $this->translations[$property][$language] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        return [
            'is_visible' => $this->data['isVisible'] ?? true,
            'image' => $this->data['image'] ?? null,
            ...$this->translations,
        ];
    }
}
