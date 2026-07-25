<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReindexProductSearch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    public function __construct(
        protected int $productId,
    ) {}

    /**
     * Re-index the product so identifiers from variants that arrived after
     * the product webhook are included in its search record.
     */
    public function handle(): void
    {
        Product::find($this->productId)?->searchable();
    }
}
