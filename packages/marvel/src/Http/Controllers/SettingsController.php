<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Repositories\SettingsRepository;
use Marvel\Enums\Permission;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Models\Settings;
use Marvel\Http\Requests\SettingsRequest;
use Marvel\Http\Resources\SettingResource;
use Marvel\Traits\ApiResponse;
use Marvel\Traits\MediaManager;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SettingsController extends CoreController
{
    use ApiResponse,  MediaManager;
    public $repository;

    public function __construct(SettingsRepository $repository)
    {
        $this->repository = $repository;
        // $this->middleware("permission:" . Permission::VIEW_SETTINGS, ["only" => ["index", "show"]]);
        $this->middleware("permission:" . Permission::UPDATE_SETTINGS, ["only" => ["store", "update"]]);
    }

    public function index(Request $request)
    {
        $settings = Settings::first();

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, SettingResource::make($settings));
    }

    /**
     * @OA\Post(
     *     path="/settings",
     *     operationId="updateSettings",
     *     tags={"Platform Configuration"},
     *     summary="Update Platform Settings",
     *     description="Create or update platform-wide settings (currency, language, SEO, etc.). Requires SUPER_ADMIN permission.",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"options"},
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="options", type="object", description="Platform settings object",
     *                 @OA\Property(property="siteTitle", type="string", example="ChawkBazar"),
     *                 @OA\Property(property="siteSubtitle", type="string"),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="logo", type="object"),
     *                 @OA\Property(property="seo", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Settings updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - requires SUPER_ADMIN")
     * )
     */
    public function store(SettingsRequest $request)
    {
        $settings = Settings::first();

        if ($settings) {
            $data = $request->only(['options']);
            if ($request->has('minimumOrderAmount')) {
                $data['minimum_order_amount'] = $request->input('minimumOrderAmount');
            }
            $settings->update($data);
        } else {
            $settings = Settings::create([
                'options' => $request->options ?? [],
                'minimum_order_amount' => $request->input('minimumOrderAmount', 0),
            ]);
        }

        return $settings;
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show()
    {
        $settings = Settings::first();

        if (!$settings) {
            return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, []);
        }

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, SettingResource::make($settings));
    }


    public function update(SettingsRequest $request)
    {
        $settings = Settings::first();


        $data = $request->only([
            'site_name',
            'site_desc',
            'meta_desc',
            'site_copy_right',
            'site_email',
            'email_support',
            'facebook',
            'instagram',
            'linkedin',
            'promotion_video_url',
            'youtube',
            'phone',
            'fast_shipping_page_publish',
            'options',
            "minimum_order_amount"
        ]);



        $settings->update($data);


        if ($request->has('logo')) {
            if (!$this->updateSingleImage($request, 'logo', $settings, 'logo-setting', 'settings')) {
                throw new HttpException(422, __('message.ERROR.LOGO_UPLOAD_FAILED'));
            }
        }
        if ($request->has('footer_logo')) {
            if (!$this->updateSingleImage($request, 'footer_logo', $settings, 'footer_logo-setting', 'settings')) {
                throw new HttpException(422, __('message.ERROR.FOOTER_LOGO_UPLOAD_FAILED'));
            }
        }
        if ($request->has('favicon')) {
            if (!$this->updateSingleImage($request, 'favicon', $settings, 'favicon-setting', 'settings')) {
                throw new HttpException(422, __('message.ERROR.FAVICON_UPLOAD_FAILED'));
            }
        }
        $settings = Settings::first();
        return $this->apiResponse(SETTINGS_UPDATED_SUCCESSFULLY, 200, true, SettingResource::make($settings));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        throw new MarvelException(ACTION_NOT_VALID);
    }
}
