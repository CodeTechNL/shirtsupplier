<?php

namespace App\Console\Commands;

use App\Jobs\SyncProducts as SyncProductsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:sync')]
#[Description('Sync all products from Lightspeed')]
class SyncProducts extends Command
{
    public function handle(): void
    {
        $this->info('Dispatching product sync job...');

        SyncProductsJob::dispatch();

        $this->info('Job dispatched. Products will be synced shortly.');
    }
}
