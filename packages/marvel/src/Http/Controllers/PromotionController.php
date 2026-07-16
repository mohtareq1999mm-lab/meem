<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Repositories\PromotionRepository;
use Marvel\Enums\Permission;
use Marvel\Http\Requests\PromotionRequest;
use Marvel\Http\Requests\UpdatePromotionRequest;
use Marvel\Http\Resources\PromotionResource;
use Marvel\Traits\ApiResponse;
use Throwable;

class PromotionController extends CoreController
{
    use ApiResponse;

    public $repository;

    public function __construct(PromotionRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware("permission:" . Permission::VIEW_PROMOTION, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::CREATE_PROMOTION, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_PROMOTION, ["only" => ["update"]]);
        $this->middleware("permission:" . Permission::DELETE_PROMOTION, ["only" => ["destroy"]]);
    }

    public function index(Request $request)
    {
        $limit = $request->limit ?? 15;
        $query = $this->repository;

        if ($search = $request->query('search')) {
            $query = $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('type', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query = $query->where('status', $request->query('status') === 'true');
        }

        if ($request->has('type')) {
            $query = $query->where('type', $request->query('type'));
        }

        if ($request->has('type_amount')) {
            $query = $query->where('type_amount', $request->query('type_amount'));
        }

        $orderBy = $request->query('order_by', 'created_at');
        $sort = $request->query('sort', 'desc');
        $query = $query->orderBy($orderBy, $sort);

        $promotions = $query->paginate($limit)->withQueryString();
        $promotionData = PromotionResource::collection($promotions)->response()->getData(true);
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [
            "data" => $promotionData['data'] ?? [],
            "page" => $promotionData['meta']['current_page'] ?? 0,
            "current_page" => $promotionData['meta']['current_page'] ?? 0,
            "from" => $promotionData['meta']['from'] ?? 0,
            "to" => $promotionData['meta']['to'] ?? 0,
            "last_page" => $promotionData['meta']['last_page'] ?? 0,
            "path" => $promotionData['meta']['path'] ?? "",
            "per_page" => $promotionData['meta']['per_page'] ?? 0,
            "total" => $promotionData['meta']['total'] ?? 0,
            "next_page_url" => $promotionData['links']['next'] ?? "",
            "prev_page_url" => $promotionData['links']['prev'] ?? "",
            "last_page_url" => $promotionData['links']['last'] ?? "",
            "first_page_url" => $promotionData['links']['first'] ?? "",
        ]);
    }

    public function store(PromotionRequest $request)
    {
        try {
            $promotion = $this->repository->storePromotion($request);
            return $this->apiResponse(CREATED_PROMOTION_SUCCESSFULLY, 201, true, PromotionResource::make($promotion));
        } catch (MarvelException $e) {
            return $this->apiResponse(COULD_NOT_CREATE_THE_RESOURCE, 400, false);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $promotion = $this->repository->findOrFail($id);
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, PromotionResource::make($promotion));
        } catch (Throwable $e) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }
    }

    public function update(UpdatePromotionRequest $request, $id)
    {
        try {
            $promotion = $this->repository->updatePromotion($id, $request);
            return $this->apiResponse(UPDATED_PROMOTION_SUCCESSFULLY, 200, true, PromotionResource::make($promotion));
        } catch (MarvelException $e) {
            return $this->apiResponse(COULD_NOT_UPDATE_THE_RESOURCE, 400, false);
        }
    }

    public function destroy($id)
    {
        try {
            $promotion = $this->repository->findOrFail($id);
            $promotion->delete();
            return $this->apiResponse(DELETED_PROMOTION_SUCCESSFULLY, 200, true);
        } catch (Throwable $e) {
            return $this->apiResponse(COULD_NOT_DELETE_THE_RESOURCE, 400, false);
        }
    }
}
