<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Repositories\GovernorateRepository;
use Marvel\Http\Resources\GovernorateResource;
use Marvel\Traits\ApiResponse;

class GovernorateController extends Controller
{
    use ApiResponse;

    public function __construct(
        private GovernorateRepository $governorateRepository
    ) {}

    public function index(): JsonResponse
    {
        $governorates = $this->governorateRepository->allActive();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, GovernorateResource::collection($governorates));
    }
}
