<?php

namespace App\Console\Commands;

use App\Jobs\SyncVariants as SyncVariantsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('variants:sync')]
#[Description('Sync all variants from Lightspeed')]
class SyncVariants extends Command
{
    public function handle(): void
    {
        $this->info('Dispatching variant sync job...');

        SyncVariantsJob::dispatch();

        $this->info('Job dispatched. Variants will be synced shortly.');
    }
}
