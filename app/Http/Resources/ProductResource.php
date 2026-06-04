<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'fulltitle' => $this->fulltitle,
            'url' => $this->url,
            'image' => $this->image,
            'is_visible' => $this->is_visible,
        ];
    }
}
