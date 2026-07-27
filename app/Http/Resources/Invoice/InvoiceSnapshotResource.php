<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'snapshot_version' => $data['snapshot_version'] ?? null,
            'snapshot_schema' => $data['snapshot_schema'] ?? null,
            'order' => $this->formatOrder($data['order'] ?? []),
            'customer' => $this->formatCustomer($data['customer'] ?? []),
            'billing_address' => $data['billing_address'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'fulfillment' => $this->formatFulfillment($data['fulfillment'] ?? []),
            'pickup_location' => $data['pickup_location'] ?? null,
            'items' => $this->formatItems($data['items'] ?? []),
            'pricing_breakdown' => $this->formatPricing($data['pricing_breakdown'] ?? []),
            'payment' => $this->formatPayment($data['payment'] ?? []),
            'metadata' => $data['metadata'] ?? null,
            'audit' => $this->formatAudit($data['audit'] ?? []),
        ];
    }

    private function formatOrder(array $order): array
    {
        return [
            'id' => $order['id'] ?? null,
            'order_number' => $order['order_number'] ?? null,
            'status' => $order['status'] ?? null,
            'payment_status' => $order['payment_status'] ?? null,
            'fulfillment_status' => $order['fulfillment_status'] ?? null,
        ];
    }

    private function formatCustomer(array $customer): array
    {
        return [
            'name' => $customer['name'] ?? null,
        ];
    }

    private function formatFulfillment(array $fulfillment): array
    {
        return [
            'type' => $fulfillment['type'] ?? null,
            'shipping_method' => $fulfillment['shipping_method'] ?? null,
            'shipping_price' => (float) ($fulfillment['shipping_price'] ?? 0),
            'fast_shipping_fee' => (float) ($fulfillment['fast_shipping_fee'] ?? 0),
            'expected_delivery_at' => $fulfillment['expected_delivery_at'] ?? null,
        ];
    }

    private function formatItems(array $items): array
    {
        return array_map(fn($item) => [
            'product_name' => $item['product_name'] ?? null,
            'product_sku' => $item['product_sku'] ?? null,
            'attributes' => $item['attributes'] ?? null,
            'quantity' => (int) ($item['quantity'] ?? 0),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'total_price' => (float) ($item['total_price'] ?? 0),
            'is_gift' => (bool) ($item['is_gift'] ?? false),
        ], $items);
    }

    private function formatPricing(array $pricing): array
    {
        return [
            'subtotal' => (float) ($pricing['subtotal'] ?? 0),
            'promotion_discount' => (float) ($pricing['promotion_discount'] ?? 0),
            'coupon_discount' => (float) ($pricing['coupon_discount'] ?? 0),
            'shipping_price' => (float) ($pricing['shipping_price'] ?? 0),
            'fast_shipping_fee' => (float) ($pricing['fast_shipping_fee'] ?? 0),
            'total' => (float) ($pricing['total'] ?? 0),
            'currency' => $pricing['currency'] ?? null,
        ];
    }

    private function formatPayment(array $payment): array
    {
        return [
            'method' => $payment['method'] ?? null,
            'gateway' => $payment['gateway'] ?? null,
            'paid_at' => $payment['paid_at'] ?? null,
        ];
    }

    private function formatAudit(array $audit): array
    {
        return [
            'generated_by' => $audit['generated_by'] ?? null,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
