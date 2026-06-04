<?php

namespace App\Jobs\Webhooks;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DestroyProduct implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Product $product,
    ) {}

    public function handle(): void
    {
        $this->product->delete();
    }
}
