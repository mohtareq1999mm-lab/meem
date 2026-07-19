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

class SettingsController extends CoreController
{
    use ApiResponse;
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
            $settings->update($request->only([
                'options',
            ]));
        } else {
            $settings = Settings::create([
                'options' => $request->options ?? [],
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

    /**
     * Update the specified resource in storage.
     *
     * @param SettingsRequest $request
     * @param int $id
     * @return JsonResponse
     * @throws ValidatorException
     */
    public function update(SettingsRequest $request)
    {
        $settings = Settings::first();

        if (!$settings) {
            $settings = new Settings();
        }

        $settings->fill($request->only([
            'site_name', 'site_desc', 'meta_desc', 'site_copy_right',
            'site_email', 'email_support', 'facebook', 'instagram',
            'linkedin', 'promotion_video_url', 'youtube', 'phone',
            'fast_shipping_page_publish', 'options',
        ]));
        $settings->save();

        if ($request->hasFile('logo')) {
            $settings->addMedia($request->file('logo'))->toMediaCollection('logo-setting');
        }

        if ($request->hasFile('favicon')) {
            $settings->addMedia($request->file('favicon'))->toMediaCollection('favicon-setting');
        }

        return $this->apiResponse(SETTINGS_UPDATED_SUCCESSFULLY, 200, true, SettingResource::make($settings->fresh()));
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
