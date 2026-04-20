<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private function httpClient()
    {
        // Nominatim có thể chậm/giới hạn; set timeout ngắn để không treo request.
        return Http::withHeaders([
            'User-Agent' => $this->userAgent(),
            'Accept-Language' => 'vi,en;q=0.8',
        ])->connectTimeout((int) env('GEOCODING_CONNECT_TIMEOUT', 3))
            ->timeout((int) env('GEOCODING_TIMEOUT', 6));
    }

    private function normalizeQuery(string $query): string
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return '';
        }

        // Bỏ số nhà ở đầu chuỗi: theo spec user chỉ nhập phường/xã, quận/huyện, tỉnh/thành.
        $q = preg_replace('/^\s*\d+[\w\/-]*\s+/', '', $q) ?? $q;
        $q = preg_replace('/\s+/', ' ', trim($q)) ?? $q;

        // Auto append "Vietnam" để tăng độ chính xác.
        if (!str_contains($q, 'vietnam') && !str_contains($q, 'việt nam') && !str_contains($q, 'viet nam')) {
            $q .= ', Vietnam';
        }

        return $q;
    }

    private function userAgent(): string
    {
        // Nominatim cần User-Agent hợp lệ; không được để rỗng (dễ bị 403).
        $userAgent = trim((string) env('GEOCODING_USER_AGENT', 'smart-donate-37/1.0'));
        return $userAgent !== '' ? $userAgent : 'smart-donate-37/1.0';
    }
   
    public function geocode(string $address): ?array
    {
        $clean = $this->normalizeQuery($address);
        if ($clean === '') {
            return null;
        }

        // 1) cache lookup
        $cached = DB::table('geocode_cache')->where('address', $clean)->first();
        if ($cached && isset($cached->lat, $cached->lng)) {
            return [
                'lat' => (float)$cached->lat,
                'lng' => (float)$cached->lng,
            ];
        }

        // 2) mapbox
        $coords = $this->callMapbox($clean);

        // 3) fallback nominatim
        if ($coords === null) {
            $coords = $this->callNominatim($clean);
        }

        if ($coords === null) {
            return null;
        }

        // 4) save cache
        DB::table('geocode_cache')->updateOrInsert(
            ['address' => $clean],
            [
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ]
        );

        return $coords;
    }

    public function makeRegion(float $lat, float $lng): string
    {
        return number_format(round($lat, 2), 2, '.', '') . '_' . number_format(round($lng, 2), 2, '.', '');
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $baseUrl = trim((string) env('GEOCODING_NOMINATIM_REVERSE_URL', 'https://nominatim.openstreetmap.org/reverse'));
        if ($baseUrl === '') {
            $baseUrl = 'https://nominatim.openstreetmap.org/reverse';
        }

        $res = $this->httpClient()->get($baseUrl, [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'zoom' => 18,
        ]);

        if (!$res->successful()) {
            return null;
        }

        $json = $res->json();
        if (!is_array($json)) {
            return null;
        }

        $displayName = $json['display_name'] ?? null;
        if (!is_string($displayName) || trim($displayName) === '') {
            return null;
        }

        return [
            'display_name' => trim($displayName),
        ];
    }

    private function callMapbox(string $query): ?array
    {
        $token = trim((string) env('MAPBOX_ACCESS_TOKEN', ''));
        if ($token === '') {
            return null;
        }

        $baseUrl = trim((string) env('MAPBOX_GEOCODING_URL', 'https://api.mapbox.com/geocoding/v5/mapbox.places'));
        if ($baseUrl === '') {
            $baseUrl = 'https://api.mapbox.com/geocoding/v5/mapbox.places';
        }

        $res = $this->httpClient()->get($baseUrl . '/' . rawurlencode($query) . '.json', [
            'access_token' => $token,
            'country' => 'vn',
            'limit' => 1,
            'language' => 'vi',
        ]);

        if (!$res->successful()) {
            return null;
        }

        $json = $res->json();
        if (!is_array($json) || !isset($json['features'][0]['center']) || !is_array($json['features'][0]['center'])) {
            return null;
        }

        $center = $json['features'][0]['center'];
        if (count($center) < 2) {
            return null;
        }

        // mapbox center = [lng, lat]
        return [
            'lat' => (float)$center[1],
            'lng' => (float)$center[0],
        ];
    }

    private function callNominatim(string $query): ?array
    {
        $baseUrl = trim((string) env('GEOCODING_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'));
        if ($baseUrl === '') {
            $baseUrl = 'https://nominatim.openstreetmap.org/search';
        }

        $res = $this->httpClient()->get($baseUrl, [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'vn',
            'addressdetails' => 1,
        ]);

        if (!$res->successful()) {
            return null;
        }

        $json = $res->json();
        if (!is_array($json) || count($json) === 0) {
            return null;
        }

        $item = $json[0];
        if (!isset($item['lat'], $item['lon'])) {
            return null;
        }

        return [
            'lat' => (float)$item['lat'],
            'lng' => (float)$item['lon'],
        ];
    }
}

