<?php

namespace Marvel\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Address;
use Marvel\Database\Repositories\SettingsRepository;
use Marvel\Events\Maintenance;
use Marvel\Exceptions\MarvelException;
use Illuminate\Support\Facades\Cache;
use Marvel\Http\Requests\SettingsRequest;
use Prettus\Validator\Exceptions\ValidatorException;

class SettingsController extends CoreController
{
    public $repository;

    public function __construct(SettingsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Address[]
     */
    public function index(Request $request)
    {
        $language = $request->language ?? DEFAULT_LANGUAGE;

        $data = Cache::rememberForever(
            'cached_settings_' . $language,
            function () use ($request) {
                return $this->repository->getData($request->language);
            }
        );

        // Safely handle maintenance data
        $maintenanceStart = $maintenanceUntil = null;

        if (!empty($data['options']['maintenance']) && is_array($data['options']['maintenance'])) {
            $maintenanceStart = isset($data['options']['maintenance']['start'])
                ? Carbon::parse($data['options']['maintenance']['start'])->format('F j, Y h:i A')
                : null;

            $maintenanceUntil = isset($data['options']['maintenance']['until'])
                ? Carbon::parse($data['options']['maintenance']['until'])->format('F j, Y h:i A')
                : null;
        }

        $formattedMaintenance = [
            "start" => $maintenanceStart,
            "until" => $maintenanceUntil,
        ];

        $data['maintenance'] = $formattedMaintenance;

        return $data;
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
        $language = $request->language ?? DEFAULT_LANGUAGE;

        $request->merge([
            'options' => [
                ...$request->options,
                ...$this->repository->getApplicationSettings(),
                'server_info' => server_environment_info(),
            ]
        ]);

        $data = $this->repository->where('language', $language)->first();

        if ($data) {
            if (Cache::has('cached_settings_' . $language)) {
                Cache::forget('cached_settings_' . $language);
            }
            $settings = tap($data)->update($request->only(['options']));
        } else {
            $settings = $this->repository->create([
                'options' => $request['options'],
                'language' => $language
            ]);
        }

        event(new Maintenance($language));

        return $settings;
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->repository->first();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SettingsRequest $request
     * @param int $id
     * @return JsonResponse
     * @throws ValidatorException
     */
    public function update(SettingsRequest $request, $id)
    {
        $settings = $this->repository->first();

        if (isset($settings->id)) {
            return $this->repository->update($request->only(['options']), $settings->id);
        } else {
            return $this->repository->create(['options' => $request['options']]);
        }
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
