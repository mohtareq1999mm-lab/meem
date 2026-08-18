<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Translatable\HasTranslations;

class StaticSection extends Model implements Sortable
{
    use SortableTrait, HasTranslations;

    public array $translatable = ['title', 'content'];

    public $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => true,
    ];

    protected $fillable = [
        'static_page_id',
        'title',
        'content',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function staticPage(): BelongsTo
    {
        return $this->belongsTo(StaticPage::class);
    }

    /**
     * Ordering must be scoped to the owning page so reorder operations never
     * affect sections that belong to a different static page.
     */
    public function buildSortQuery()
    {
        return static::query()->where('static_page_id', $this->static_page_id);
    }
}