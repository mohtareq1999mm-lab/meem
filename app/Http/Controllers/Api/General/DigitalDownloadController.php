<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use App\Models\DigitalDownloadLog;
use App\Models\DigitalEntitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Marvel\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class DigitalDownloadController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's digital entitlements together with
     * ready-to-use signed download URLs per asset.
     */
    public function index(Request $request): JsonResponse
    {
        $entitlements = DigitalEntitlement::query()
            ->where('user_id', $request->user()->id)
            ->with(['assets' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'), 'orderItem'])
            ->orderByDesc('created_at')
            ->get();

        $data = $entitlements->map(function (DigitalEntitlement $entitlement) {
            return [
                'uuid' => $entitlement->uuid,
                'status' => $entitlement->status,
                'download_limit' => (int) $entitlement->download_limit,
                'download_count' => (int) $entitlement->download_count,
                'delivered_at' => $entitlement->delivered_at?->toIso8601String(),
                'revoked_at' => $entitlement->revoked_at?->toIso8601String(),
                'product' => [
                    'id' => $entitlement->orderItem?->product_id,
                    'name' => $entitlement->orderItem?->product_name,
                ],
                'assets' => $entitlement->assets->map(fn (DigitalAsset $asset) => [
                    'uuid' => $asset->uuid,
                    'type' => $asset->type,
                    'original_name' => $asset->original_name,
                    'mime' => $asset->mime,
                    'size' => (int) $asset->size,
                    'download_url' => $this->signedUrl($entitlement, $asset),
                ])->values()->all(),
            ];
        })->values()->all();

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $data);
    }

    /**
     * Stream a purchased digital asset.
     *
     * SECURITY MODEL
     * - Ownership is enforced when the signed URL is ISSUED (authenticated
     *   endpoints only ever issue URLs for the caller's own entitlements).
     * - The signature alone is NOT authorization: this controller re-checks
     *   entitlement status, asset ownership, and the download limit.
     */
    public function download(string $entitlementUuid, string $assetUuid): Response|JsonResponse
    {
        if (!config('digital.enabled', true)) {
            return $this->apiResponse(__('message.ERROR.DIGITAL_ENTITLEMENT_NOT_ACCESSIBLE'), 404, false);
        }

        $entitlement = DigitalEntitlement::query()
            ->where('uuid', $entitlementUuid)
            ->first();

        if (!$entitlement || !$entitlement->relationLoaded('orderItem')) {
            $entitlement?->loadMissing('orderItem');
        }

        if (!$entitlement) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        // Status gate — revoked/pending entitlements lose access instantly,
        // regardless of signature validity (D7/D8).
        if ($entitlement->status !== DigitalEntitlement::STATUS_DELIVERED) {
            return $this->apiResponse(__('message.ERROR.DIGITAL_ENTITLEMENT_NOT_ACCESSIBLE'), 403, false);
        }

        /** @var DigitalAsset|null $asset */
        $asset = DigitalAsset::query()->where('uuid', $assetUuid)->first();

        // Asset must belong to the purchased product of this entitlement.
        if (!$asset || (int) $asset->product_id !== (int) $entitlement->orderItem?->product_id) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        // Race-safe limit enforcement: only an atomic conditional increment
        // grants the stream. Concurrent requests can never exceed the limit.
        $affected = DB::update(
            'UPDATE digital_entitlements SET download_count = download_count + 1 WHERE id = ? AND status = ? AND download_count < download_limit',
            [$entitlement->id, DigitalEntitlement::STATUS_DELIVERED]
        );

        if ($affected === 0) {
            return $this->apiResponse(__('message.ERROR.DIGITAL_DOWNLOAD_LIMIT_REACHED'), 403, false);
        }

        DB::table('digital_download_logs')->insert([
            'entitlement_id' => $entitlement->id,
            'asset_id' => $asset->id,
            'ip_hash' => hash('sha256', request()->ip() . '|' . config('app.key')),
            'ua_hash' => hash('sha256', substr((string) request()->userAgent(), 0, 512) . '|' . config('app.key')),
            'downloaded_at' => now(),
        ]);

        $disk = \Illuminate\Support\Facades\Storage::disk($asset->disk);

        if (!$disk->exists($asset->path)) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        $filename = $this->sanitizeFilename($asset->original_name) . '.' . pathinfo($asset->path, PATHINFO_EXTENSION);

        return $disk->response($asset->path, $filename, [
            'Content-Type' => $asset->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function signedUrl(DigitalEntitlement $entitlement, DigitalAsset $asset): ?string
    {
        if ($entitlement->status !== DigitalEntitlement::STATUS_DELIVERED) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'general.digital.download',
            now()->addMinutes((int) config('digital.signed_url_ttl_minutes', 30)),
            ['entitlement' => $entitlement->uuid, 'asset' => $asset->uuid]
        );
    }

    /**
     * Strip any path information and unsafe characters from the original
     * filename before it reaches a Content-Disposition header.
     */
    private function sanitizeFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $clean = preg_replace('/[^A-Za-z0-9\-_ ]+/', '-', $base) ?? 'download';

        return trim(mb_substr($clean, 0, 120)) ?: 'download';
    }
}
