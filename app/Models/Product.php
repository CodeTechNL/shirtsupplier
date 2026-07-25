<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, HasTranslations, Searchable, SoftDeletes;

    protected $guarded = [];

    protected array $translatables = [
        'title' => 'title',
        'url' => 'url',
        'content' => 'content',
        'fulltitle' => 'fulltitle',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'url' => 'array',
            'fulltitle' => 'array',
            'description' => 'array',
            'content' => 'array',
            'image' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    public function sameProductGroups(): BelongsToMany
    {
        return $this->belongsToMany(SameProductGroup::class, 'same_product_group_product')->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * The data indexed for search. Includes the Dutch title/fulltitle and the
     * variants' SKU, EAN, article code and title so a product is findable by
     * any of those identifiers.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('variants');

        return [
            'id' => $this->getKey(),
            'title' => $this->getTranslation('nl', 'title'),
            'fulltitle' => $this->getTranslation('nl', 'fulltitle'),
            'skus' => $this->variants->pluck('sku')->filter()->implode(' '),
            'eans' => $this->variants->pluck('ean')->filter()->implode(' '),
            'article_codes' => $this->variants->pluck('article_code')->filter()->implode(' '),
            'variant_titles' => $this->variants->pluck('title')->filter()->implode(' '),
        ];
    }
}
