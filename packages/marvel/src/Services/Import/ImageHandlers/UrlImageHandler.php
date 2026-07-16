<?php

namespace Marvel\Services\Import\ImageHandlers;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class UrlImageHandler
{
    protected array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    protected int $maxFileSize = 5 * 1024 * 1024;

    protected int $timeout = 30;

    public function download(string $url): ?string
    {
        $url = $this->normalizeGoogleDriveUrl($url);

        if (!$this->isValidUrl($url)) {
            Log::warning("Invalid image URL skipped: {$url}");
            return null;
        }

        try {
            $this->ensureTempDirectoryExists();

            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("Failed to download image from {$url}: HTTP {$response->status()}");
                return null;
            }

            $body = $response->body();
            $bodySize = strlen($body);

            if ($bodySize > $this->maxFileSize) {
                Log::warning("Image too large from {$url}: {$bodySize} bytes");
                return null;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $body);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedMimes)) {
                Log::warning("Invalid MIME type for {$url}: {$mimeType}");
                return null;
            }

            $extension = $this->mimeToExtension($mimeType);
            $tempPath = storage_path('app/temp/import_url_' . Str::random(16) . '.' . $extension);

            file_put_contents($tempPath, $body);

            Log::info("Downloaded image from {$url} ({$mimeType}, {$bodySize} bytes)");

            return $tempPath;
        } catch (Exception $e) {
            Log::error("Failed to download image from {$url}: " . $e->getMessage());
            return null;
        }
    }

    public function attachToModel(HasMedia $model, string $filePath, string $collection = 'products'): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $model->addMedia($filePath)
            ->toMediaCollection($collection);
    }

    public function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    protected function normalizeGoogleDriveUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !str_contains($host, 'drive.google.com')) {
            return $url;
        }

        $fileId = null;

        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];
        }

        if (!$fileId) {
            $query = parse_url($url, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                if (!empty($params['id'])) {
                    $fileId = $params['id'];
                }
            }
        }

        if ($fileId) {
            return 'https://drive.google.com/uc?export=download&confirm=t&id=' . $fileId;
        }

        return $url;
    }

    protected function ensureTempDirectoryExists(): void
    {
        $path = storage_path('app/temp');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    protected function mimeToExtension(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        ];

        return $map[$mime] ?? 'jpg';
    }
}
