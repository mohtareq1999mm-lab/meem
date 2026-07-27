<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipment\CreateShipmentRequest;
use App\Http\Requests\Shipment\UpdateShipmentRequest;
use App\Http\Requests\Shipment\UpdateShipmentStatusRequest;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Traits\ApiResponse;

class ShipmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ShipmentService $shipmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shipments = $this->shipmentService->list(
            $request->only(['order_id', 'status', 'courier', 'tracking_number', 'from', 'to']),
            (int) $request->get('limit', 15),
        );

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $shipments);
    }

    public function show(int $id): JsonResponse
    {
        $shipment = $this->shipmentService->find($id);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $shipment);
    }

    public function showByUuid(string $uuid): JsonResponse
    {
        $shipment = $this->shipmentService->findByUuid($uuid);

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $shipment);
    }

    public function store(CreateShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipmentService->create($request->validated());

        return $this->apiResponse('Shipment created successfully', 201, true, $shipment);
    }

    public function updateStatus(UpdateShipmentStatusRequest $request, int $id): JsonResponse
    {
        try {
            $shipment = $this->shipmentService->updateStatus(
                $id,
                $request->validated('status'),
                $request->validated('notes'),
            );

            return $this->apiResponse('Shipment status updated', 200, true, $shipment);
        } catch (\RuntimeException $e) {
            return $this->apiResponse($e->getMessage(), 422, false);
        }
    }

    public function update(UpdateShipmentRequest $request, int $id): JsonResponse
    {
        $shipment = $this->shipmentService->update($id, $request->validated());

        return $this->apiResponse('Shipment updated successfully', 200, true, $shipment);
    }
}
