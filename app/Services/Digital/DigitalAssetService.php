<?php

namespace App\Services\Digital;

use App\Models\DigitalAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Marvel\Database\Models\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DigitalAssetService
{
    /**
     * Store an uploaded PDF on the PRIVATE disk and register it as a
     * product asset. Stored filenames are randomized; the original name
     * is metadata only.
     */
    public function store(Product $product, UploadedFile $file, array $data = []): DigitalAsset
    {
        $this->assertUploadAllowed($file);

        return DB::transaction(function () use ($product, $file, $data) {
            $storedName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
            $path = "digital-assets/{$product->id}/{$storedName}";

            $stored = Storage::disk('private')->putFileAs(
                "digital-assets/{$product->id}",
                $file,
                $storedName
            );

            if (!$stored) {
                throw new HttpException(500, __('message.ERROR.DIGITAL_ASSET_UPLOAD_FAILED'));
            }

            $nextSort = (int) DigitalAsset::where('product_id', $product->id)->max('sort_order') + 1;

            return DigitalAsset::create([
                'product_id' => $product->id,
                'type' => DigitalAsset::TYPE_FILE,
                'disk' => 'private',
                'path' => $path,
                'original_name' => $data['original_name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'mime' => $file->getClientMimeType() ?: 'application/pdf',
                'size' => $file->getSize(),
                'sort_order' => (int) ($data['sort_order'] ?? $nextSort),
            ]);
        });
    }

    public function update(DigitalAsset $asset, array $data): DigitalAsset
    {
        $asset->update(array_intersect_key($data, array_flip(['original_name', 'sort_order'])));

        return $asset->refresh();
    }

    public function delete(DigitalAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->delete();
        });
    }

    private function assertUploadAllowed(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new HttpException(422, __('message.ERROR.DIGITAL_ASSET_INVALID_FILE'));
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, config('digital.allowed_mimes'), true)) {
            throw new HttpException(422, __('message.ERROR.DIGITAL_ASSET_INVALID_FILE'));
        }

        $mime = strtolower((string) $file->getMimeType());
        if (!in_array($mime, config('digital.allowed_mime_types'), true)) {
            throw new HttpException(422, __('message.ERROR.DIGITAL_ASSET_INVALID_MIME'));
        }

        $maxKb = (int) config('digital.max_upload_kb');
        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw new HttpException(422, __('message.ERROR.DIGITAL_ASSET_TOO_LARGE', ['max' => $maxKb]));
        }
    }
}
