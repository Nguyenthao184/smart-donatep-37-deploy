<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiMatchingService
{
    public function __construct(
        private readonly string $baseUrl = ''
    ) {}

    public function match(array $payload): array
    {
        $url = $this->baseUrl;
        if (empty($url)) {
            $url = rtrim(env('AI_MATCHING_URL', 'http://127.0.0.1:8001'), '/');
        }

        $response = Http::connectTimeout(3)
            ->timeout(30)
            ->post($url . '/matches', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('AI service error: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json();
    }
}

