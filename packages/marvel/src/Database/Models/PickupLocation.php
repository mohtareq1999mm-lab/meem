<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickupLocation extends Model
{
    use SoftDeletes;

    protected $table = 'pickup_locations';

    protected $fillable = [
        'store_name',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'working_hours',
        'status',
        'display_order',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_location_id');
    }
}
