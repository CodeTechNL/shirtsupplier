<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => Arr::first((array) $this->url),
            'image' => $this->formatImage(),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function formatImage(): ?array
    {
        $image = $this->image;

        if (empty($image)) {
            return null;
        }

        return [
            'createdAt' => $image['createdAt'] ?? null,
            'updatedAt' => $image['updatedAt'] ?? null,
            'extension' => $image['extension'] ?? null,
            'size' => $image['size'] ?? null,
            'title' => $image['title'] ?? null,
            'thumb' => $image['thumb'] ?? null,
            'src' => $image['src'] ?? null,
        ];
    }
}
