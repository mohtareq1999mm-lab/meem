<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Marvel\Enums\PaymentStatus;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    public $fillable = [
        'user_id',
        'governorate_id',
        'name',
        'user_phone',
        'user_email',
        'address',
        'notes',
        'shipping_method',
        'expected_delivery_at',
        'fast_shipping_fee',
        'fulfillment_type',
        'payment_method',
        'payment_gateway',
        'pickup_location_id',
        'pickup_location_name',
        'pickup_location_address',
        'pickup_location_phone',
        'pickup_location_coordinates',
        'price',
        'shipping_price',
        'total_price',
        'coupon',
        'coupon_discount',
        'coupon_discount_type',
        'coupon_discount_max_amount',
        'promotion_id',
        'promotion_code',
        'promotion_type',
        'promotion_discount',
        'status',
    ];

    protected $casts = [
        'address' => 'array',
        'expected_delivery_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('created_at', 'desc');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class, 'pickup_location_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('shipping_method', 'SCHEDULED');
    }

    public function scopeFast(Builder $query): Builder
    {
        return $query->where('shipping_method', 'FAST');
    }

    public function scopeDelivery(Builder $query): Builder
    {
        return $query->where('fulfillment_type', 'delivery');
    }

    public function scopePickup(Builder $query): Builder
    {
        return $query->where('fulfillment_type', 'pickup');
    }

    public function getOrderNumberAttribute(): string
    {
        return 'ORD-' . str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function getPaymentStatusAttribute(): string
    {
        if (in_array($this->payment_method, ['cod', 'pay_at_cashier'])) {
            $latestTransaction = $this->transactions()->latest()->first();
            if ($latestTransaction) {
                return match ($latestTransaction->status) {
                    'paid' => PaymentStatus::SUCCESS,
                    'failed' => PaymentStatus::FAILED,
                    default => PaymentStatus::PENDING,
                };
            }
            if (in_array($this->status, ['completed', 'delivered'])) {
                return PaymentStatus::SUCCESS;
            }
            return PaymentStatus::PENDING;
        }

        return match ($this->status) {
            'completed', 'delivered' => PaymentStatus::SUCCESS,
            'cancelled' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
