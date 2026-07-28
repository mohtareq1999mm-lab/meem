<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'shipping_price' => (float) $this->shipping_price,
            'total_discount' => (float) $this->total_discount,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_gateway' => $this->payment_gateway,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'pdf_generated_at' => $this->pdf_generated_at?->toIso8601String(),
            'verification_url' => $this->when($this->uuid, fn () =>
                url('/api/v1/general/invoices/verify/' . $this->uuid)
            ),
            'download_url' => $this->when($this->uuid && $this->pdf_path, fn () =>
                url('/api/v1/general/invoices/' . $this->uuid . '/download')
            ),
            'snapshot' => $this->when($this->data, fn () =>
                InvoiceSnapshotResource::make($this->resource)
            ),
        ];
    }
}
