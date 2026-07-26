<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Jobs\GenerateInvoicePdfJob;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;

class InvoiceService
{
    public function __construct(
        private InvoiceSnapshotService $snapshotService,
        private InvoiceSnapshotValidator $snapshotValidator,
        private SnapshotIntegrityService $integrityService,
        private InvoiceNumberService $numberService,
    ) {}

    public function generateFromOrder(Order $order): ?Invoice
    {
        $existing = Invoice::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($order) {
            $snapshot = $this->snapshotService->buildFullSnapshot($order);

            $this->snapshotValidator->validate($snapshot);

            $hash = $this->integrityService->computeHash($snapshot);

            $numberData = $this->numberService->generateNext();

            $paidTransaction = $order->transactions()
                ->where('status', 'paid')
                ->latest()
                ->first();

            $subtotal = (float) ($order->price ?? 0);
            $promotionDiscount = (float) ($order->promotion_discount ?? 0);
            $couponDiscount = (float) ($order->coupon_discount ?? 0);
            $shippingPrice = (float) ($order->shipping_price ?? 0);
            $fastFee = (float) ($order->fast_shipping_fee ?? 0);
            $totalDiscount = $promotionDiscount + $couponDiscount;
            $total = (float) ($order->total_price ?? 0);

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'transaction_id' => $paidTransaction?->id,
                'user_id' => $order->user_id,
                'invoice_number' => $numberData['number'],
                'invoice_series' => $numberData['series'],
                'sequence_number' => $numberData['sequence'],
                'sequence_year' => $numberData['year'],
                'subtotal' => $subtotal,
                'shipping_price' => $shippingPrice,
                'coupon_discount' => $couponDiscount,
                'promotion_discount' => $promotionDiscount,
                'total_discount' => $totalDiscount,
                'total' => $total,
                'amount_paid' => $total,
                'currency' => $paidTransaction?->currency ?? 'EGP',
                'payment_method' => $order->payment_method,
                'payment_gateway' => $order->payment_gateway,
                'status' => 'generated',
                'data' => $snapshot,
                'snapshot_hash' => $hash,
                'generated_at' => now(),
                'generated_by' => 'system',
            ]);

            GenerateInvoicePdfJob::dispatch($invoice);

            return $invoice;
        });
    }
}
