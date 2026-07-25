<?php

namespace App\Filament\Resources\Webhooks\Pages;

use App\Filament\Resources\Webhooks\WebhookResource;
use App\Jobs\Webhooks\InstallWebhooks;
use App\Jobs\Webhooks\SyncWebhooks;
use App\Models\Webhook;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListWebhooks extends ListRecords
{
    protected static string $resource = WebhookResource::class;

    private const array GROUPS = ['products', 'variants'];

    private const array ACTIONS = ['created', 'updated', 'deleted'];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncWebhooks')
                ->label('Sync from Lightspeed')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Sync webhooks')
                ->modalDescription('This will refresh the list to match the webhooks currently installed at Lightspeed.')
                ->action(function (): void {
                    SyncWebhooks::dispatch();

                    Notification::make()
                        ->title('Webhook sync job dispatched.')
                        ->success()
                        ->send();
                }),
            Action::make('installWebhooks')
                ->label('Install webhooks')
                ->icon(Heroicon::OutlinedSignal)
                ->modalHeading('Install webhooks')
                ->modalDescription('Select the webhooks to install at Lightspeed. Webhooks that are already installed are checked and disabled.')
                ->modalSubmitActionLabel('Install')
                ->schema([
                    CheckboxList::make('hooks')
                        ->label('Webhooks')
                        ->options(fn (): array => $this->getWebhookOptions())
                        ->descriptions(fn (): array => $this->getWebhookOptionDescriptions())
                        ->default(fn (): array => $this->getInstalledWebhookKeys())
                        ->disableOptionWhen(fn (string $value): bool => in_array($value, $this->getInstalledWebhookKeys(), true))
                        ->in(fn (): array => array_keys($this->getWebhookOptions()))
                        ->bulkToggleable(),
                ])
                ->action(function (array $data): void {
                    $keys = array_values(array_diff($data['hooks'] ?? [], $this->getInstalledWebhookKeys()));

                    if ($keys === []) {
                        Notification::make()
                            ->title('No new webhooks selected.')
                            ->warning()
                            ->send();

                        return;
                    }

                    InstallWebhooks::dispatch(array_map(function (string $key): array {
                        [$group, $action, $language] = explode('|', $key);

                        return ['group' => $group, 'action' => $action, 'language' => $language];
                    }, $keys));

                    Notification::make()
                        ->title('Installation job dispatched for '.count($keys).' '.Str::plural('webhook', count($keys)).'.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * All installable hooks, keyed by "group|action|language" with a readable label.
     *
     * @return array<string, string>
     */
    protected function getWebhookOptions(): array
    {
        $options = [];

        foreach (config('webshop.languages') as $language) {
            foreach (self::GROUPS as $group) {
                foreach (self::ACTIONS as $action) {
                    $options["{$group}|{$action}|{$language}"] = Str::ucfirst(Str::singular($group))." {$action} ({$language})";
                }
            }
        }

        return $options;
    }

    /**
     * The endpoint URL each hook will point at, keyed like the options.
     *
     * @return array<string, string>
     */
    protected function getWebhookOptionDescriptions(): array
    {
        $descriptions = [];

        foreach (array_keys($this->getWebhookOptions()) as $key) {
            [$group, $action, $language] = explode('|', $key);

            $descriptions[$key] = route("webhooks.{$group}.{$action}", ['language' => $language]);
        }

        return $descriptions;
    }

    /**
     * Keys ("group|action|language") of the webhooks that are already installed.
     *
     * @return array<int, string>
     */
    protected function getInstalledWebhookKeys(): array
    {
        return Webhook::query()
            ->get(['item_group', 'item_action', 'language'])
            ->map(fn (Webhook $webhook): string => "{$webhook->item_group}|{$webhook->item_action}|{$webhook->language}")
            ->all();
    }
}
