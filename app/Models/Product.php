<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

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
}
