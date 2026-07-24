<?php

namespace App\Models;

use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variant extends Model
{
    /** @use HasFactory<VariantFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'price_excl' => 'decimal:2',
            'price_incl' => 'decimal:2',
            'old_price_excl' => 'decimal:2',
            'old_price_incl' => 'decimal:2',
            'stock_level' => 'integer',
            'weight' => 'integer',
            'image' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
