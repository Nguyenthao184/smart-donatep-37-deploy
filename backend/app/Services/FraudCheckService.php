<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FraudCheckService
{
    /**
     * @param array<int, array<string, int|float>> $usersPayload
     * @return array<int, array<string, int|string>>
     */
    public function check(array $usersPayload): array
    {
        $baseUrl = rtrim((string) env('AI_MATCHING_URL', 'http://127.0.0.1:8001'), '/');
        $response = Http::timeout(12)->post($baseUrl . '/fraud-check', [
            'users' => $usersPayload,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Lỗi dịch vụ AI phát hiện gian lận: ' . $response->status() . ' ' . $response->body());
        }

        $json = $response->json();
        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<int, array<string, int|float>>  $campaignsPayload  Key theo schema AI (campaign_id, …)
     * @return array<int, array<string, int|string>>
     */
    public function checkCampaigns(array $campaignsPayload): array
    {
        $baseUrl = rtrim((string) env('AI_MATCHING_URL', 'http://127.0.0.1:8001'), '/');
        $response = Http::timeout(12)->post($baseUrl . '/campaign-fraud-check', [
            'campaigns' => $campaignsPayload,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Lỗi dịch vụ AI phát hiện gian lận chiến dịch: ' . $response->status() . ' ' . $response->body()
            );
        }

        $json = $response->json();
        return is_array($json) ? $json : [];
    }
}

