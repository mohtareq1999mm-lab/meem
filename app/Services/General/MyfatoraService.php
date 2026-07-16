<?php

namespace App\Services\General;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyfatoraService
{
    private array $header;

    private ?string $baseUrl;

    public function __construct()
    {
        $this->header = [
            'authorization' => 'Bearer ' . config('services.myfatoorah.api_key'),
        ];
        $this->baseUrl = config('services.myfatoorah.base_url');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handelRequest(string $url, array $data = []): ?array
    {
        if ($data === []) {
            return null;
        }

        $response = Http::withHeaders($this->header)
            ->acceptJson()
            ->timeout(30)
            ->withoutVerifying()
            ->post($this->baseUrl . $url, $data);

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();

        if (!is_array($body) || !($body['IsSuccess'] ?? false)) {
            return null;
        }

        return $body;
    }


    public function createInvoice($data)
    {
        return $this->handelRequest('SendPayment', $data);
    }

    public function checkInvoice($data)
    {
        return $this->handelRequest('GetPaymentStatus', $data);
    }

    public function makeRefund(array $data): ?array
    {
        return $this->handelRequest('MakeRefund', $data);
    }
}
