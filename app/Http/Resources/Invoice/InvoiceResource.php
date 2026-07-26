<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'shipping_price' => (float) $this->shipping_price,
            'coupon_discount' => (float) $this->coupon_discount,
            'promotion_discount' => (float) $this->promotion_discount,
            'total_discount' => (float) $this->total_discount,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_gateway' => $this->payment_gateway,
            'snapshot_hash' => $this->snapshot_hash,
            'pdf_generated_at' => $this->pdf_generated_at?->toIso8601String(),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'is_correction' => (bool) $this->is_correction,
            'correction_reason' => $this->correction_reason,
            'corrected_at' => $this->corrected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
