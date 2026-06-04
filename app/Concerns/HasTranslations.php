<?php

namespace App\Concerns;

trait HasTranslations
{

    public function setTranslation(string $language, string $property, mixed $value): static
    {
        $attributeData = $this->getAttribute($property);

        if (! is_array($attributeData)) {
            $attributeData = [];
        }

        $attributeData[$language] = $value;

        $this->setAttribute($property, $attributeData);

        return $this;
    }

    public function getTranslation(string $language, string $property): string
    {
        $data = $this->getAttribute($property) ?? [];

        return $data[$language] ?? '';
    }

    public function setTitle(string $language, mixed $title): static
    {
        return $this->setTranslation($language, 'title', $title);
    }

    /** @return array<string, string> */
    public function getTranslatables(): array
    {
        return $this->translatables;
    }
}
