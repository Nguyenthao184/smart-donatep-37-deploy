<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiMatchingService
{
    private string $aiUrl;

    public function __construct()
    {
        $this->aiUrl = rtrim(env('AI_MATCHING_URL', 'http://127.0.0.1:8001'), '/');
    }

    /**
     * Gọi AI endpoint
     * 
     * @param array $payload
     * @param string $endpoint 'matches' | 'related'
     * @return array
     */
    public function match(array $payload, string $endpoint = 'matches'): array
    {
        // 🔒 Chỉ cho phép 2 endpoint
        if (!in_array($endpoint, ['matches', 'related'], true)) {
            $endpoint = 'matches';
        }

        $url = "{$this->aiUrl}/{$endpoint}";

        Log::info('AI CALL', [
            'url' => $url,
            'endpoint' => $endpoint,
            'posts_count' => count($payload['posts'] ?? []),
        ]);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(30)
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('AI ERROR', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $result = $response->json();

            Log::info('AI RESPONSE', [
                'endpoint' => $endpoint,
                'result_count' => count($result ?? []),
            ]);

            return is_array($result) ? $result : [];

        } catch (\Exception $e) {
            Log::error('AI EXCEPTION', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Gọi /matches (strict matching)
     */
    public function matchPosts(array $payload): array
    {
        return $this->match($payload, 'matches');
    }

    /**
     * Gọi /related (semantic gợi ý)
     */
    public function matchRelated(array $payload): array
    {
        return $this->match($payload, 'related');
    }
}