<?php

namespace App\Console\Commands;

use App\Jobs\Webhooks\InstallVariantWebhooks as InstallVariantWebhooksJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhooks:install-variants')]
#[Description('Install variant webhooks at Lightspeed')]
class InstallVariantWebhooks extends Command
{
    public function handle(): void
    {
        $this->info('Dispatching variant webhook installation job...');

        InstallVariantWebhooksJob::dispatch();

        $this->info('Job dispatched. Variant webhooks will be installed shortly.');
    }
}
