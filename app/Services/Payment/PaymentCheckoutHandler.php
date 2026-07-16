<?php

namespace App\Services\Payment;

use App\Services\Gateway\CashierQrService;
use App\Services\General\CartInventoryService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Enums\ShippingMethod;
use Marvel\Traits\ApiResponse;

class PaymentCheckoutHandler
{
    use ApiResponse;

    public function __construct(
        private PaymentGatewayFactory $paymentGatewayFactory,
        private CashierQrService $cashierQrService,
        private CartInventoryService $cartInventoryService,
    ) {}

    public function handleOnlinePayment(
        Request $request,
        Order $order,
        float $amount,
        string $gateway,
        ?string $callbackUrl = null,
        ?string $errorUrl = null,
    ): JsonResponse {
        try {
            $gatewayInstance = $this->paymentGatewayFactory->make($gateway);
        } catch (\App\Exceptions\UnsupportedGatewayException $e) {
            return $this->apiResponse($e->getMessage(), 422, false);
        }

        $callbackUrl ??= route('api.checkout.callback');
        $errorUrl ??= route('api.checkout.errorCallback');

        if ($request->type === 'mobile') {
            $callbackUrl .= '?type=mobile';
            $errorUrl .= '?type=mobile';
        }

        $result = $gatewayInstance->createInvoice(
            $order,
            $amount,
            $callbackUrl,
            $errorUrl,
        );

        if (!$result->success) {
            return $this->apiResponse($result->errorMessage ?? ERROR_CREATING_INVOICE, 500, false);
        }

        $transaction = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'invoice_id' => $result->gatewayTransactionId,
            'payment_method' => $gateway,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => config('payment.default_currency', 'EGP'),
            'gateway_transaction_id' => $result->gatewayTransactionId,
            'gateway_response' => $result->rawResponse,
        ]);

        if (!$transaction) {
            return $this->apiResponse(ERROR_CREATING_TRANSACTION, 500, false);
        }

        return $this->apiResponse(CHECKOUT_SUCCESSFUL, 200, true, ['url' => $result->redirectUrl]);
    }

    public function handleCodPayment(Request $request, Order $order, string $shippingMethod = ShippingMethod::SCHEDULED): JsonResponse
    {
        $transaction = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'payment_method' => 'cod',
            'status' => 'pending',
            'amount' => $order->total_price,
            'currency' => config('payment.default_currency', 'EGP'),
        ]);

        if (!$transaction) {
            return $this->apiResponse(ERROR_CREATING_TRANSACTION, 500, false);
        }

        $this->finalizeInventory($request, $shippingMethod);

        return $this->apiResponse(__('checkout.cod_success'), 200, true, [
            'order_id' => $order->id,
        ]);
    }

    public function handleCashierQrPayment(Request $request, Order $order, string $shippingMethod = ShippingMethod::SCHEDULED): JsonResponse
    {
        $transaction = Transaction::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'payment_method' => 'pay_at_cashier',
            'status' => 'pending',
            'amount' => $order->total_price,
            'currency' => config('payment.default_currency', 'EGP'),
        ]);

        if (!$transaction) {
            return $this->apiResponse(ERROR_CREATING_TRANSACTION, 500, false);
        }

        $qrDataUri = $this->cashierQrService->generateBase64DataUri($transaction);

        $this->finalizeInventory($request, $shippingMethod);

        return $this->apiResponse(CHECKOUT_SUCCESSFUL, 200, true, [
            'order_id' => $order->id,
            'transaction_uuid' => $transaction->uuid,
            'qr_code' => $qrDataUri,
        ]);
    }

    private function finalizeInventory(Request $request, string $shippingMethod): void
    {
        try {
            $cart = $this->cartInventoryService->getActiveCartForUser($request->user());
            if ($cart) {
                $this->cartInventoryService->finalizeItemsByShippingMethod($cart, $shippingMethod);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
