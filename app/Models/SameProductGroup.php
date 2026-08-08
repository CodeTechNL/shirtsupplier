<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SameProductGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public const PIVOT_TABLE = 'same_product_group_product';

    /**
     * The pivot column holding the manual order set by dragging rows in the
     * admin. It is deliberately not applied in `products()`: the relation
     * manager's table needs an unordered base query so sorting by one of its
     * own columns still wins.
     */
    public const ORDER_COLUMN = 'sort_order';

    public const QUALIFIED_ORDER_COLUMN = self::PIVOT_TABLE.'.'.self::ORDER_COLUMN;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::PIVOT_TABLE)
            ->withPivot(self::ORDER_COLUMN)
            ->withTimestamps();
    }

    /**
     * The group's products in their manual order, falling back to the product
     * id so rows that share a position stay stable.
     */
    public function orderedProducts(): BelongsToMany
    {
        return $this->products()
            ->orderByPivot(self::ORDER_COLUMN)
            ->orderBy('products.id');
    }

    /**
     * Attach products at the end of the manual order, skipping any that are
     * already in the group.
     *
     * @param  array<int, int|string>  $productIds
     * @return array<int, int|string> The ids that were newly attached.
     */
    public function attachProductsToEnd(array $productIds): array
    {
        $existingIds = $this->products()->pluck('products.id')->all();

        $newIds = array_values(array_diff($productIds, $existingIds));

        if ($newIds === []) {
            return [];
        }

        /** Rows attached without a position sit at 0, so never start below the current row count. */
        $position = max(
            (int) $this->products()->max(self::QUALIFIED_ORDER_COLUMN),
            count($existingIds),
        );

        $this->products()->attach(collect($newIds)->mapWithKeys(fn (int|string $id): array => [
            $id => [self::ORDER_COLUMN => ++$position],
        ])->all());

        return $newIds;
    }
}
