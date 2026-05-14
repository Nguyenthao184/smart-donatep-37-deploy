<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private function httpClient()
    {
        return Http::withHeaders([
            'User-Agent' => $this->userAgent(),
            'Accept-Language' => 'vi,en;q=0.8',
        ])
            ->connectTimeout((int) env('GEOCODING_CONNECT_TIMEOUT', 3))
            ->timeout((int) env('GEOCODING_TIMEOUT', 6));
    }

    private function normalizeQuery(string $query): string
    {
        $q = mb_strtolower(trim($query), 'UTF-8');

        if ($q === '') {
            return '';
        }

        // bỏ khoảng trắng dư
        $q = preg_replace('/\s+/', ' ', trim($q)) ?? $q;

        // bỏ số nhà đầu chuỗi
        $q = preg_replace('/^\s*\d+[\w\/-]*\s+/', '', $q) ?? $q;

        // chuẩn hóa tên hành chính
        $replaceMap = [
            'tp.' => 'thành phố',
            'tp ' => 'thành phố ',
            'q.' => 'quận',
            'p.' => 'phường',
        ];

        $q = str_replace(
            array_keys($replaceMap),
            array_values($replaceMap),
            $q
        );

        // append Vietnam để tăng độ chính xác
        if (
            !str_contains($q, 'việt nam')
            && !str_contains($q, 'viet nam')
            && !str_contains($q, 'vietnam')
        ) {
            $q .= ', Việt Nam';
        }

        return $q;
    }

    private function userAgent(): string
    {
        $userAgent = trim((string) env(
            'GEOCODING_USER_AGENT',
            'smart-donate-37/1.0'
        ));

        return $userAgent !== ''
            ? $userAgent
            : 'smart-donate-37/1.0';
    }

    public function geocode(string $address): ?array
    {
        $clean = $this->normalizeQuery($address);

        if ($clean === '') {
            return null;
        }

        // ========= CACHE =========
        $cached = DB::table('geocode_cache')
            ->where('address', $clean)
            ->first();

        if ($cached && isset($cached->lat, $cached->lng)) {
            return [
                'lat' => (float) $cached->lat,
                'lng' => (float) $cached->lng,
            ];
        }

        // ========= MAPBOX =========
        $coords = $this->callMapbox($clean);

        // ========= FALLBACK NOMINATIM =========
        if ($coords === null) {
            $coords = $this->callNominatim($clean);
        }

        if ($coords === null) {
            return null;
        }

        // ========= SAVE CACHE =========
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
        return number_format(round($lat, 2), 2, '.', '')
            . '_'
            . number_format(round($lng, 2), 2, '.', '');
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $baseUrl = trim((string) env(
            'GEOCODING_NOMINATIM_REVERSE_URL',
            'https://nominatim.openstreetmap.org/reverse'
        ));

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

        if (
            !is_string($displayName)
            || trim($displayName) === ''
        ) {
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

        $baseUrl = trim((string) env(
            'MAPBOX_GEOCODING_URL',
            'https://api.mapbox.com/geocoding/v5/mapbox.places'
        ));

        if ($baseUrl === '') {
            $baseUrl = 'https://api.mapbox.com/geocoding/v5/mapbox.places';
        }

        $res = $this->httpClient()->get(
            $baseUrl . '/' . rawurlencode($query) . '.json',
            [
                'access_token' => $token,
                'country' => 'vn',
                'limit' => 1,
                'language' => 'vi',

                // chỉ lấy địa giới hành chính
                'types' => 'place,locality,district,region',
            ]
        );

        if (!$res->successful()) {
            return null;
        }

        $json = $res->json();

        if (
            !is_array($json)
            || !isset($json['features'][0]['center'])
            || !is_array($json['features'][0]['center'])
        ) {
            return null;
        }

        $center = $json['features'][0]['center'];

        if (count($center) < 2) {
            return null;
        }

        // Mapbox trả [lng, lat]
        return [
            'lat' => (float) $center[1],
            'lng' => (float) $center[0],
        ];
    }

    private function callNominatim(string $query): ?array
    {
        $baseUrl = trim((string) env(
            'GEOCODING_NOMINATIM_URL',
            'https://nominatim.openstreetmap.org/search'
        ));

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
            'lat' => (float) $item['lat'],
            'lng' => (float) $item['lon'],
        ];
    }
}

