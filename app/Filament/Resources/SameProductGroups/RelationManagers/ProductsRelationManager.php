<?php

namespace App\Filament\Resources\SameProductGroups\RelationManagers;

use App\Filament\Forms\Components\AttachableProductsTable;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\Variant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Product $record): string => static::productLabel($record))
            ->reorderable(SameProductGroup::QUALIFIED_ORDER_COLUMN)
            ->defaultSort(SameProductGroup::QUALIFIED_ORDER_COLUMN)
            ->columns([
                TextColumn::make('fulltitle')
                    ->label('Title')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? ($state['nl'] ?? '') : (string) $state)
                    ->searchable(),
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('attach')
                    ->label('Attach products')
                    ->icon(Heroicon::Plus)
                    ->modalHeading('Attach products')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitActionLabel('Attach selected')
                    ->schema([
                        static::attachSearchField(),
                        AttachableProductsTable::make('recordIds')
                            ->hiddenLabel()
                            ->rowsUsing(fn (Get $get): array => static::attachableProductRows(
                                (string) ($get('search') ?? ''),
                                $this->getOwnerRecord(),
                            )),
                    ])
                    ->action(function (array $data, Action $action): void {
                        $ids = array_values(array_filter((array) ($data['recordIds'] ?? [])));

                        if ($ids === []) {
                            Notification::make()
                                ->title('Select at least one product to attach.')
                                ->warning()
                                ->send();

                            $action->halt();
                        }

                        $attached = $this->getOwnerRecord()->attachProductsToEnd($ids);

                        Notification::make()
                            ->title(trans_choice('{1} :count product attached|[2,*] :count products attached', count($attached), ['count' => count($attached)]))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The attach modal's search box. Rendered as a native search input and
     * opted out of browser/password-manager autofill so Safari's iCloud
     * Passwords (and 1Password/LastPass/Bitwarden) don't prompt on it.
     */
    protected static function attachSearchField(): TextInput
    {
        return TextInput::make('search')
            ->hiddenLabel()
            ->placeholder('Search by title, SKU, EAN or article code…')
            ->prefixIcon(Heroicon::MagnifyingGlass)
            ->type('search')
            ->autocomplete(false)
            ->extraInputAttributes([
                'data-form-type' => 'other',
                'data-1p-ignore' => 'true',
                'data-lpignore' => 'true',
                'data-bwignore' => 'true',
            ])
            ->live(debounce: 400)
            ->dehydrated(false)
            ->partiallyRenderComponentsAfterStateUpdated(['recordIds']);
    }

    /**
     * Search attachable products through Scout (Algolia), constrained to
     * products not already in the given group and ordered by Scout relevance.
     *
     * @return Collection<int, Product>
     */
    public static function searchAttachableProductModels(string $search, ?SameProductGroup $excludeGroup = null): Collection
    {
        if (blank($search)) {
            return new Collection;
        }

        $ids = Product::search($search)->take(50)->keys()->all();

        if ($ids === []) {
            return new Collection;
        }

        $order = array_flip($ids);

        return static::attachableProductsQuery($excludeGroup)
            ->whereKey($ids)
            ->with('variants')
            ->get()
            ->sortBy(fn (Product $product): int => $order[$product->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Attachable products as `[id => label]`, used for option lookups.
     *
     * @return array<int, string>
     */
    public static function searchAttachableProducts(string $search, ?SameProductGroup $excludeGroup = null): array
    {
        return static::searchAttachableProductModels($search, $excludeGroup)
            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => static::searchResultLabel($product)])
            ->all();
    }

    /**
     * Attachable products shaped as rows for the attach modal's table.
     *
     * @return array<int, array{id: int, title: string, codes: string, visible: bool}>
     */
    public static function attachableProductRows(string $search, ?SameProductGroup $excludeGroup = null): array
    {
        return static::searchAttachableProductModels($search, $excludeGroup)
            ->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'title' => static::productLabel($product),
                'codes' => static::variantCodes($product),
                'visible' => (bool) $product->is_visible,
            ])
            ->all();
    }

    /**
     * Products eligible to attach. A product may belong to multiple groups, so
     * only products already in the given group are excluded.
     */
    public static function attachableProductsQuery(?SameProductGroup $excludeGroup = null): Builder
    {
        return Product::query()
            ->when($excludeGroup, fn (Builder $query, SameProductGroup $group): Builder => $query->whereDoesntHave(
                'sameProductGroups',
                fn (Builder $query): Builder => $query->whereKey($group->getKey()),
            ));
    }

    protected static function productLabel(Product $product): string
    {
        return $product->getTranslation('nl', 'fulltitle') ?: $product->getTranslation('nl', 'title');
    }

    /**
     * Product label enriched with the variant identifiers (SKU, article code,
     * EAN) so a match on one of those columns is visible in the search results.
     */
    protected static function searchResultLabel(Product $product): string
    {
        $codes = static::variantCodes($product);

        return $codes !== ''
            ? static::productLabel($product)." — {$codes}"
            : static::productLabel($product);
    }

    /**
     * The distinct variant identifiers (SKU, article code, EAN) for a product,
     * capped so long variant lists stay readable.
     */
    protected static function variantCodes(Product $product): string
    {
        return $product->variants
            ->flatMap(fn (Variant $variant): array => [$variant->sku, $variant->article_code, $variant->ean])
            ->filter()
            ->unique()
            ->take(5)
            ->implode(', ');
    }
}
