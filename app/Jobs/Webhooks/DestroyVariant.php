<?php

namespace App\Jobs\Webhooks;

use App\Models\Variant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DestroyVariant implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Variant $variant,
    ) {}

    public function handle(): void
    {
        $this->variant->delete();
    }
}
