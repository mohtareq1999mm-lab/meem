<?php

namespace Marvel\Database\Models;

// use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Marvel\Database\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Translatable\HasTranslations;

class Faqs extends Model implements Sortable
{
    use HasTranslations, SoftDeletes, SortableTrait;

    protected $table = 'faqs';

    public array $translatable = ['faq_title', 'faq_description'];

    public $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => true,
    ];

    public $fillable = [
        'faq_title',
        'faq_description',
        'status',
        'order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function scopeActive (Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
