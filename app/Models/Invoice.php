<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'order_id',
        'transaction_id',
        'user_id',
        'correction_to_id',
        'invoice_number',
        'invoice_series',
        'sequence_number',
        'sequence_year',
        'subtotal',
        'shipping_price',
        'coupon_discount',
        'promotion_discount',
        'total_discount',
        'total',
        'amount_paid',
        'currency',
        'payment_method',
        'payment_gateway',
        'status',
        'data',
        'snapshot_hash',
        'pdf_generated_at',
        'pdf_regenerated_at',
        'pdf_path',
        'pdf_checksum',
        'generation_attempts',
        'last_generation_error',
        'is_correction',
        'correction_reason',
        'corrected_at',
        'cancelled_at',
        'cancellation_reason',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'is_correction' => 'boolean',
        'generation_attempts' => 'integer',
        'generated_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
        'pdf_regenerated_at' => 'datetime',
        'corrected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function correctionTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_to_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_to_id');
    }
}
