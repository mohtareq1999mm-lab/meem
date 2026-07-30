<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Repositories\CountryRepository;
use Marvel\Http\Resources\CountryResource;
use Marvel\Traits\ApiResponse;

class CountryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CountryRepository $countryRepository
    ) {}

    public function index(): JsonResponse
    {
        $countries = $this->countryRepository->allActive();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CountryResource::collection($countries));
    }

    public function show(int $id): JsonResponse
    {
        $country = $this->countryRepository->findById($id, ['governorates']);
        if (!$country) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, CountryResource::make($country));
    }
}
