<?php

namespace App\Services\Gateway;

use App\DTOs\GatewayResult;
use App\Services\General\MyfatoraService;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use Marvel\Database\Models\Order;

class MyFatoorahGateway implements PaymentGatewayContract
{
    public function __construct(
        private MyfatoraService $myfatoraService,
    ) {}

    public function createInvoice(
        Order $order,
        float $amount,
        string $callbackUrl,
        string $errorUrl,
        array $metadata = []
    ): GatewayResult {
        $data = [
            'InvoiceValue' => $amount,
            'CustomerName' => $order->name ?? 'Customer',
            'NotificationOption' => 'LNK',
            'DisplayCurrencyIso' => 'EGP',
            'MobileCountryCode' => '+20',
            'CustomerMobile' => $order->user_phone,
            'CustomerEmail' => $order->user_email,
            'language' => app()->getLocale() == 'ar' ? 'ar' : 'en',
            'CallBackUrl' => $callbackUrl,
            'ErrorUrl' => $errorUrl,
        ];

        $response = $this->myfatoraService->createInvoice($data);

        if (!is_array($response)) {
            return new GatewayResult(
                success: false,
                errorMessage: 'No response from payment gateway',
            );
        }

        $invoiceUrl = data_get($response, 'Data.InvoiceURL');
        $invoiceId = data_get($response, 'Data.InvoiceId');

        if (!$invoiceUrl || !$invoiceId) {
            return new GatewayResult(
                success: false,
                errorMessage: data_get($response, 'Data.InvoiceError') ?? 'Invalid gateway response',
                rawResponse: $response,
            );
        }

        return new GatewayResult(
            success: true,
            redirectUrl: $invoiceUrl,
            gatewayTransactionId: (string) $invoiceId,
            status: 'pending',
            rawResponse: $response,
        );
    }

    public function verifyPayment(string $gatewayTransactionId): GatewayResult
    {
        $data = [
            'Key' => $gatewayTransactionId,
            'KeyType' => 'PaymentId',
        ];

        $response = $this->myfatoraService->checkInvoice($data);

        if (!is_array($response)) {
            return new GatewayResult(
                success: false,
                errorMessage: 'No response from payment gateway',
            );
        }

        $invoiceStatus = data_get($response, 'Data.InvoiceStatus');
        $invoiceId = data_get($response, 'Data.InvoiceId');
        $invoiceAmount = data_get($response, 'Data.InvoiceValue');
        $invoiceCurrency = data_get($response, 'Data.DisplayCurrencyIso');

        if (!$invoiceStatus) {
            return new GatewayResult(
                success: false,
                errorMessage: 'Invalid gateway response',
                rawResponse: $response,
            );
        }

        $isPaid = $invoiceStatus === 'Paid';

        return new GatewayResult(
            success: $isPaid,
            gatewayTransactionId: (string) $invoiceId,
            amount: $invoiceAmount !== null ? (float) $invoiceAmount : null,
            currency: $invoiceCurrency,
            status: $isPaid ? 'paid' : 'failed',
            errorMessage: $isPaid ? null : (data_get($response, 'Data.InvoiceError') ?? 'Payment not completed'),
            rawResponse: $response,
        );
    }

    public function name(): string
    {
        return 'myfatoorah';
    }

    public function refund(
        Order $order,
        float $amount,
        ?string $reason = null
    ): GatewayResult {
        $transaction = $order->transactions()
            ->whereNotNull('gateway_transaction_id')
            ->latest()
            ->first();

        if (!$transaction || !$transaction->gateway_transaction_id) {
            return new GatewayResult(
                success: false,
                errorMessage: 'No paid transaction found for this order',
            );
        }

        $data = [
            'Key' => $transaction->gateway_transaction_id,
            'KeyType' => 'PaymentId',
            'Amount' => $amount,
            'Comment' => $reason ?? 'Refund for order #' . $order->id,
        ];

        $response = $this->myfatoraService->makeRefund($data);

        if (!is_array($response)) {
            return new GatewayResult(
                success: false,
                errorMessage: 'No response from payment gateway',
            );
        }

        $refundId = data_get($response, 'Data.RefundId');
        $refundStatus = data_get($response, 'Data.RefundStatus');

        return new GatewayResult(
            success: true,
            gatewayTransactionId: $refundId ? (string) $refundId : null,
            amount: $amount,
            currency: 'EGP',
            status: $refundStatus ?? 'refunded',
            rawResponse: $response,
        );
    }
}
