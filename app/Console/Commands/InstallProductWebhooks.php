<?php

namespace App\Console\Commands;

use App\Jobs\Webhooks\InstallProductWebhooks as InstallProductWebhooksJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhooks:install-products')]
#[Description('Install product webhooks at Lightspeed')]
class InstallProductWebhooks extends Command
{
    public function handle(): void
    {
        $this->info('Dispatching product webhook installation job...');

        InstallProductWebhooksJob::dispatch();

        $this->info('Job dispatched. Product webhooks will be installed shortly.');
    }
}
