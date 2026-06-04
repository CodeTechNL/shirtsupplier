<?php

namespace App\Jobs\Webhooks;

use App\Formatters\ProductFormatter;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreOrUpdateProduct implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected array $data,
        protected string $language,
    ) {}

    public function handle(): void
    {
        $product = Product::firstOrNew(['id' => $this->data['id']]);

        $formatted = (new ProductFormatter($this->data, $product))
            ->setLanguageAttribute($this->language, 'title', $this->data['title'] ?? null)
            ->setLanguageAttribute($this->language, 'url', $this->data['url'] ?? null)
            ->setLanguageAttribute($this->language, 'fulltitle', $this->data['fulltitle'] ?? null)
            ->setLanguageAttribute($this->language, 'description', $this->data['description'] ?? null)
            ->setLanguageAttribute($this->language, 'content', $this->data['content'] ?? null)
            ->get();

        $product->forceFill($formatted);
        $product->save();
    }
}
