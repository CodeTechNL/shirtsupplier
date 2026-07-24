<?php

namespace App\Filament\Resources\SameProductGroups\RelationManagers;

use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\Variant;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Product $record): string => static::productLabel($record))
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
                AttachAction::make()
                    ->multiple()
                    ->recordSelect(fn (Select $select): Select => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => static::attachableProductsQuery($search, $this->getOwnerRecord())
                            ->with('variants')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => static::searchResultLabel($product)])
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => static::productLabel($product)])
                            ->all())),
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
     * Products eligible to attach, searchable by title and by their variants'
     * SKU, EAN, article code, or title. A product may belong to multiple
     * groups, so only products already in the given group are excluded.
     */
    public static function attachableProductsQuery(?string $search = null, ?SameProductGroup $excludeGroup = null): Builder
    {
        return Product::query()
            ->when($excludeGroup, fn (Builder $query, SameProductGroup $group): Builder => $query->whereDoesntHave(
                'sameProductGroups',
                fn (Builder $query): Builder => $query->whereKey($group->getKey()),
            ))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('fulltitle', 'like', "%{$search}%")
                        ->orWhereHas('variants', function (Builder $query) use ($search): void {
                            $query->where('sku', 'like', "%{$search}%")
                                ->orWhere('ean', 'like', "%{$search}%")
                                ->orWhere('article_code', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                });
            });
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
        $codes = $product->variants
            ->flatMap(fn (Variant $variant): array => [$variant->sku, $variant->article_code, $variant->ean])
            ->filter()
            ->unique()
            ->take(5)
            ->implode(', ');

        return filled($codes)
            ? static::productLabel($product)." — {$codes}"
            : static::productLabel($product);
    }
}
