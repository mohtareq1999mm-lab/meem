<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\InvoiceCollection;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Jobs\GenerateInvoicePdfJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('limit', 15), 100);

        $invoices = Invoice::query()
            ->with(['order', 'user'])
            ->when($request->has('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->has('order_id'), fn($q) => $q->where('order_id', (int) $request->get('order_id')))
            ->when($request->has('user_id'), fn($q) => $q->where('user_id', (int) $request->get('user_id')))
            ->when($request->has('from'), fn($q) => $q->whereDate('created_at', '>=', $request->get('from')))
            ->when($request->has('to'), fn($q) => $q->whereDate('created_at', '<=', $request->get('to')))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->apiResponse(
            FETCH_DATA_SUCCESSFULLY,
            200,
            true,
            new InvoiceCollection($invoices)
        );
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with(['order.orderItems', 'transaction', 'user'])->findOrFail($id);

        return $this->apiResponse(
            FETCH_DATA_SUCCESSFULLY,
            200,
            true,
            InvoiceResource::make($invoice)
        );
    }

    public function regenerate(int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        if (!in_array($invoice->status, ['failed', 'ready'], true)) {
            return $this->apiResponse(ERROR_ADDING_ITEMS_TO_ORDER, 422, false);
        }

        $invoice->update([
            'status' => 'pdf_generating',
            'generation_attempts' => $invoice->generation_attempts + 1,
            'last_generation_error' => null,
        ]);

        GenerateInvoicePdfJob::dispatch($invoice);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
            'invoice_id' => $invoice->id,
            'status' => 'pdf_generating',
        ]);
    }
}
