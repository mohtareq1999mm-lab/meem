<?php

namespace Marvel\Http\Controllers;

use App\Models\DigitalAsset;
use App\Services\Digital\DigitalAssetService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Product;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\DigitalAssetCreateRequest;
use Marvel\Http\Requests\DigitalAssetUpdateRequest;
use Marvel\Traits\ApiResponse;

class DigitalAssetController extends CoreController
{
    use ApiResponse;

    private DigitalAssetService $digitalAssetService;

    public function __construct(DigitalAssetService $digitalAssetService)
    {
        $this->digitalAssetService = $digitalAssetService;
        $this->middleware("permission:" . Permission::VIEW_PRODUCTS, ["only" => ["index"]]);
        $this->middleware("permission:" . Permission::CREATE_PRODUCT, ["only" => ["store"]]);
        $this->middleware("permission:" . Permission::UPDATE_PRODUCT, ["only" => ["update", "destroy"]]);
    }

    public function index(Request $request, int $product): JsonResponse
    {
        $assets = DigitalAsset::query()
            ->where('product_id', $product)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, \Marvel\Http\Resources\DigitalAssetResource::collection($assets));
    }

    public function store(DigitalAssetCreateRequest $request, int $product): JsonResponse
    {
        try {
            $productModel = Product::findOrFail($product);

            if ($productModel->item_type !== \Marvel\Enums\ItemType::DIGITAL) {
                return $this->apiResponse(__('message.ERROR.DIGITAL_ASSET_INVALID_FILE'), 422, false);
            }

            $asset = $this->digitalAssetService->store(
                $productModel,
                $request->file('file'),
                $request->only(['original_name', 'sort_order'])
            );

            return $this->apiResponse(CREATE_DATA_SUCCESSFULLY, 201, true, new \Marvel\Http\Resources\DigitalAssetResource($asset));
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function update(DigitalAssetUpdateRequest $request, string $uuid): JsonResponse
    {
        $asset = DigitalAsset::where('uuid', $uuid)->firstOrFail();

        return $this->apiResponse(UPDATE_DATA_SUCCESSFULLY, 200, true, new \Marvel\Http\Resources\DigitalAssetResource(
            $this->digitalAssetService->update($asset, $request->validated())
        ));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $asset = DigitalAsset::where('uuid', $uuid)->firstOrFail();
        $this->digitalAssetService->delete($asset);

        return $this->apiResponse(DELETE_DATA_SUCCESSFULLY, 200, true);
    }
}
