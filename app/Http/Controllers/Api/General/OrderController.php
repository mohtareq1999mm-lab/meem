<?php

namespace App\Http\Controllers\Api\General;

use App\DTOs\GatewayResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderCollection;
use App\Services\General\CartInventoryService;
use App\Services\General\OrderService;
use App\Services\Gateway\CashierQrService;
use App\Services\Payment\PaymentCheckoutHandler;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;
use Marvel\Enums\ShippingMethod;
use App\Events\OrderCancelled;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use Marvel\Http\Requests\OrderCreateRequest;
use Marvel\Traits\ApiResponse;

class OrderController extends Controller
{
    use ApiResponse;
    protected $orderService;
    protected $cartInventoryService;

    public function __construct(
        OrderService $orderService,
        CartInventoryService $cartInventoryService,
        private PaymentGatewayFactory $paymentGatewayFactory,
        private PaymentCheckoutHandler $paymentCheckoutHandler,
        private CashierQrService $cashierQrService,
    ) {
        $this->orderService = $orderService;
        $this->cartInventoryService = $cartInventoryService;
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->paginateForUser($request);

        return $this->apiResponse(
            FETCH_DATA_SUCCESSFULLY,
            200,
            true,
            new OrderCollection($orders)
        );
    }

    public function eligiblePromotions(): JsonResponse
    {
        $payload = $this->orderService->eligiblePromotionsForUser();

        if (!$payload) {
            return $this->apiResponse(CART_NOT_FOUND, 400, false);
        }

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $payload);
    }

    public function checkout(OrderCreateRequest $request)
    {
        $orderDataUser = $request->validated();
        $orderDataUser['user_id'] = $request->user()->id;
        
        $cart = $this->cartInventoryService->getActiveCartForUser($request->user());
        if (!$cart) {
            return $this->apiResponse(CART_NOT_FOUND, 400, false);
        }
        
        try {
            $this->cartInventoryService->ensureCartReservation($cart);
        } catch (\Throwable $e) {
            return $this->apiResponse($e->getMessage(), 400, false);
        }

        $paymentMethod = $request->input('payment_method', 'online');
        $gateway = $request->input('gateway', config('payment.default_gateway', 'myfatoorah'));
        $fulfillmentType = $request->input('fulfillment_type', 'delivery');

        if ($paymentMethod === 'cod' && $fulfillmentType === 'pickup') {
            return $this->apiResponse(COD_NOT_AVAILABLE_FOR_PICKUP, 422, false);
        }

        $request->merge([
            'fulfillment_type' => $fulfillmentType,
            'payment_method' => $paymentMethod,
            'payment_gateway' => $paymentMethod === 'online' ? $gateway : null,
        ]);

        try {
            $order = $this->orderService->addItemsInOrder($request);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse($e->getMessage(), 422, false);
        }

        if (!$order) {
            return $this->apiResponse(ERROR_ADDING_ITEMS_TO_ORDER, 500, false);
        }

        if ($paymentMethod === 'online') {
            $orderPrice = round((float) $order->total_price, 2);
            if ($orderPrice <= 0) {
                return $this->apiResponse(FILED_TO_CREATE_ORDER_TRY_AGAIN, 500, false);
            }
            return $this->paymentCheckoutHandler->handleOnlinePayment($request, $order, $orderPrice, $gateway);
        }

        if ($paymentMethod === 'cod') {
            return $this->paymentCheckoutHandler->handleCodPayment($request, $order);
        }

        if ($paymentMethod === 'pay_at_cashier') {
            return $this->paymentCheckoutHandler->handleCashierQrPayment($request, $order);
        }

        return $this->apiResponse(INVALID_PAYMENT_METHOD, 422, false);
    }

    public function markCodAsPaid(int $orderId, Request $request): JsonResponse
    {
        $order = Order::query()->findOrFail($orderId);

        try {
            $this->orderService->markCodAsPaid($order);
        } catch (\RuntimeException $e) {
            return $this->apiResponse($e->getMessage(), 422, false);
        }

        return $this->apiResponse(PAYMENT_SUCCESSFUL, 200, true);
    }

    public function markCashierPaid(int $orderId, Request $request): JsonResponse
    {
        $order = Order::query()->findOrFail($orderId);

        try {
            $this->orderService->markCashierPaid($order);
        } catch (\RuntimeException $e) {
            return $this->apiResponse($e->getMessage(), 422, false);
        }

        return $this->apiResponse(PAYMENT_SUCCESSFUL, 200, true);
    }

    public function getTransactionQr(string $uuid, Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $transaction = Transaction::byUuid($uuid)->first();

        if (!$transaction) {
            return $this->apiResponse(TRANSACTION_NOT_FOUND, 404, false);
        }

        $order = $transaction->order;
        if (!$order || $order->user_id !== $request->user()->id) {
            return $this->apiResponse(UNAUTHORIZED_TRANSACTION_ACCESS, 403, false);
        }

        $svg = $this->cashierQrService->generateSvg($transaction);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }


    public function checkoutCallback(Request $request)
    {
        $paymentId = $request->query('paymentId', $request->input('paymentId'));
        if (!$paymentId) {
            return $this->apiResponse(MISSING_PAYMENT_ID, 400, false);
        }

        try {
            $gateway = $this->paymentGatewayFactory->make('myfatoorah');
        } catch (\App\Exceptions\UnsupportedGatewayException $e) {
            return $this->apiResponse(PAYMENT_GATEWAY_UNAVAILABLE, 500, false);
        }

        $result = $gateway->verifyPayment($paymentId);

        $verifiedInvoiceId = $result->gatewayTransactionId;

        $transaction = Transaction::where('gateway_transaction_id', $verifiedInvoiceId)
            ->orWhere('invoice_id', $verifiedInvoiceId)
            ->first();

        if ($transaction) {
            $transaction->update([
                'status' => $result->status ?? ($result->success ? 'paid' : 'failed'),
                'gateway_response' => $result->rawResponse,
                'error_message' => $result->errorMessage,
                'paid_at' => $result->success ? now() : null,
            ]);
        }

        if (!$result->success) {
            $order = null;
            if ($transaction) {
                $order = $transaction->order;
                $this->orderService->changeOrderStatus($transaction->invoice_id, 'cancelled');
                if ($order && ($user = User::find($order->user_id))) {
                    $cart = $this->cartInventoryService->getActiveCartForUser($user);
                    if ($cart) {
                        $this->cartInventoryService->releaseCart($cart, false);
                    }
                }
            }

            try {
                if ($order) {
                    event(new PaymentFailed($order));
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $errorMessage = $result->errorMessage ?? __(PAYMENT_FAILED);

            return redirect(config('app.app_url_frontend') . '/' . app()->getLocale() . '/payment/failed?' . http_build_query([
                'status' => 'failed',
                'message' => $errorMessage,
                'payment_id' => $paymentId,
            ]));
        }

        $order = null;
        if ($transaction) {
            $order = $transaction->order;

            if ($order) {
                $hasMismatch = false;

                if ($result->amount !== null && abs((float) $result->amount - (float) $order->total_price) > 0.01) {
                    $hasMismatch = true;
                    \Log::warning('Payment amount mismatch - blocking order', [
                        'order_id' => $order->id,
                        'expected' => (float) $order->total_price,
                        'received' => $result->amount,
                        'currency' => $result->currency,
                    ]);
                }

                if (!$hasMismatch && $result->currency !== null && $result->currency !== config('payment.default_currency', 'EGP')) {
                    $hasMismatch = true;
                    \Log::warning('Payment currency mismatch - blocking order', [
                        'order_id' => $order->id,
                        'expected' => config('payment.default_currency', 'EGP'),
                        'received' => $result->currency,
                    ]);
                }

                if ($hasMismatch) {
                    $this->orderService->changeOrderStatus($transaction->invoice_id, 'cancelled');
                    if ($user = User::find($order->user_id)) {
                        $cart = $this->cartInventoryService->getActiveCartForUser($user);
                        if ($cart) {
                            $this->cartInventoryService->releaseCart($cart, false);
                        }
                    }
                    try {
                        event(new PaymentFailed($order));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                    $errorMessage = $result->errorMessage ?? __(PAYMENT_FAILED);
                    return redirect(config('app.app_url_frontend') . '/' . app()->getLocale() . '/payment/failed?' . http_build_query([
                        'status' => 'failed',
                        'message' => $errorMessage,
                        'payment_id' => $paymentId,
                    ]));
                }
            }

            if ($order) {
                if ($user = User::find($order->user_id)) {
                    $cart = $this->cartInventoryService->getActiveCartForUser($user);
                    if ($cart) {
                        $shippingMethod = $order->shipping_method ?? ShippingMethod::SCHEDULED;
                        $this->cartInventoryService->finalizeItemsByShippingMethod($cart, $shippingMethod);

                        if ($order->coupon && $cart->fresh()->coupon === $order->coupon) {
                            $cart->fresh()->update(['coupon' => null]);
                        }
                    }
                }
            }

            $order = $this->orderService->changeOrderStatus($transaction->invoice_id, 'completed');
        }

        try {
            if ($order) {
                event(new PaymentSucceeded($order));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (request()->type === 'mobile') {
            return $this->apiResponse(CHECKOUT_SUCCESSFUL, 200, true, [
                'status' => 'success',
                'message' => __(PAYMENT_SUCCESSFUL),
                'payment_id' => $paymentId,
                'order_id' => $order?->id,
            ]);
        }

        return redirect(config('app.app_url_frontend') . '/' . app()->getLocale() . '/payment/success?' . http_build_query([
            'status' => 'success',
            'message' => __(PAYMENT_SUCCESSFUL),
            'payment_id' => $paymentId,
            'order_id' => $order?->id,
        ]));
        
    }

    public function checkoutErrorCallback(Request $request)
    {
        $paymentId = $request->query('paymentId', $request->input('paymentId'));
        if (!$paymentId) {
            return $this->apiResponse(MISSING_PAYMENT_ID, 400, false);
        }

        try {
            $gateway = $this->paymentGatewayFactory->make('myfatoorah');
        } catch (\App\Exceptions\UnsupportedGatewayException $e) {
            return $this->apiResponse(PAYMENT_GATEWAY_UNAVAILABLE, 500, false);
        }

        $result = $gateway->verifyPayment($paymentId);
        $invoiceStatus = $result->status;
        $errorMessage = $result->errorMessage ?? __(PAYMENT_FAILED);

        $verifiedInvoiceId = $result->gatewayTransactionId;

        $transaction = Transaction::where('gateway_transaction_id', $verifiedInvoiceId)
            ->orWhere('invoice_id', $verifiedInvoiceId)
            ->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
                'gateway_response' => $result->rawResponse,
                'error_message' => $errorMessage,
            ]);
        }

        if ($transaction && (!$invoiceStatus || $invoiceStatus !== 'paid')) {
            $order = $this->orderService->changeOrderStatus($transaction->invoice_id, 'cancelled');
            if ($order && ($user = User::find($order->user_id))) {
                $cart = $this->cartInventoryService->getActiveCartForUser($user);
                if ($cart) {
                    $this->cartInventoryService->releaseCart($cart, false);
                }
            }

            try {
                if (isset($order) && $order) {
                    event(new PaymentFailed($order));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($request->type === 'mobile') {
            return $this->apiResponse(PAYMENT_FAILED, 400, false, [
                'status' => 'failed',
                'error' => $errorMessage,
                'payment_id' => $paymentId,
            ]);
        }

        return redirect(config('app.app_url_frontend') . '/' . app()->getLocale() . '/payment/failed?' . http_build_query([
            'status' => 'failed',
            'error' => $errorMessage,
            'payment_id' => $paymentId,
        ]));
    }
}
