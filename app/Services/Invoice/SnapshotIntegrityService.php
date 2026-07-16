<?php

namespace App\Services\Invoice;

class SnapshotIntegrityService
{
    public function computeHash(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_SORT_KEYS);
        return hash('sha256', $json);
    }

    public function verify(array $data, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->computeHash($data));
    }
}
