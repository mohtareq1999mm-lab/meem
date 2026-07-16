<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Repositories\FastShippingRepository;
use Marvel\Enums\Permission;
use Marvel\Traits\ApiResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Fast Shipping",
 *     description="Fast shipping configuration and management"
 * )
 */
class FastShippingController extends CoreController
{
    use ApiResponse;

    public function __construct(private readonly FastShippingRepository $repository)
    {
        $this->middleware("permission:" . Permission::VIEW_FAST_SHIPPING, ["only" => ["getSettings"]]);
        $this->middleware("permission:" . Permission::UPDATE_FAST_SHIPPING, ["only" => ["updateSettings"]]);
    }

    /**
     * @OA\Get(
     *     path="/api/fast-shipping/settings",
     *     tags={"Fast Shipping"},
     *     summary="Get fast shipping settings",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="enabled", type="boolean", example=true),
     *             @OA\Property(property="duration_minutes", type="integer", example=120),
     *             @OA\Property(property="fee", type="number", format="float", example=30),
     *             @OA\Property(property="start_hour", type="string", example="08:00"),
     *             @OA\Property(property="end_hour", type="string", example="22:00")
     *         )
     *     )
     * )
     */
    public function getSettings(): JsonResponse
    {
        $settings = $this->repository->getSettings();

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $settings);
    }

    /**
     * @OA\Put(
     *     path="/api/fast-shipping/settings",
     *     tags={"Fast Shipping"},
     *     summary="Update fast shipping settings",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="enabled", type="boolean"),
     *             @OA\Property(property="duration_minutes", type="integer"),
     *             @OA\Property(property="fee", type="number", format="float"),
     *             @OA\Property(property="start_hour", type="string"),
     *             @OA\Property(property="end_hour", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Settings updated successfully")
     * )
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'fee' => ['sometimes', 'numeric', 'min:0'],
            'start_hour' => ['sometimes', 'string', 'date_format:H:i'],
            'end_hour' => ['sometimes', 'string', 'date_format:H:i'],
        ]);

        $this->repository->updateSettings($validated);

        return $this->apiResponse('Fast shipping settings updated successfully', 200, true);
    }
}
