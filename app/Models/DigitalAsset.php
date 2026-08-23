<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Marvel\Database\Models\Product;

class DigitalAsset extends Model
{
    protected $table = 'digital_assets';

    public const TYPE_FILE = 'FILE';
    public const TYPE_LICENSE = 'LICENSE';
    public const TYPE_ACTIVATION_CODE = 'ACTIVATION_CODE';

    // MVP ships FILE only; the remaining types are reserved extension points.
    public const ACTIVE_TYPES = [self::TYPE_FILE];

    protected $fillable = [
        'uuid',
        'product_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'sort_order',
    ];

    protected $hidden = [
        'path',
        'disk',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
            }

            if (empty($asset->type)) {
                $asset->type = self::TYPE_FILE;
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
