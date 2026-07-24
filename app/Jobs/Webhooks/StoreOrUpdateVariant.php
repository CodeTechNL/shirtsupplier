<?php

namespace App\Jobs\Webhooks;

use App\Formatters\VariantFormatter;
use App\Models\Variant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreOrUpdateVariant implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(
        protected array $data,
    ) {}

    public function handle(): void
    {
        $variant = Variant::firstOrNew(['id' => $this->data['id']]);

        $variant->forceFill((new VariantFormatter($this->data))->get());
        $variant->save();
    }
}
