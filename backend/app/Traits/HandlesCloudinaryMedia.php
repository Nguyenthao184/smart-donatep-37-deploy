<?php

namespace App\Traits;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Storage;

trait HandlesCloudinaryMedia
{
    protected function resolveMediaUrl(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $raw = trim($value);
        return preg_match('/^https?:\/\//i', $raw) === 1
            ? $raw
            : secure_asset('storage/' . ltrim($raw, '/'));
    }

    protected function uploadToCloudinary($file, string $folder, array $options = []): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }
        $uploaded = Cloudinary::uploadApi()->upload(
            $file->getRealPath(),
            array_merge(['folder' => $folder, 'resource_type' => 'auto'], $options)
        );
        return $uploaded['secure_url'] ?? null;
    }

    protected function uploadRawToCloudinary(string $data, string $folder, string $publicId): ?string
    {
        $dataUri = 'data:image/png;base64,' . base64_encode($data);
        $uploaded = Cloudinary::uploadApi()->upload($dataUri, [
            'folder' => $folder,
            'public_id' => $publicId,
        ]);
        return $uploaded['secure_url'] ?? null;
    }

    protected function deleteCloudinaryAsset(?string $urlOrPath): void
    {
        if (!$urlOrPath || trim($urlOrPath) === '') {
            return;
        }

        if (str_contains($urlOrPath, 'cloudinary.com')) {
            if (preg_match('/\/upload\/(?:v\d+\/)?(.+?)(?:\.[a-zA-Z0-9]+)?$/', $urlOrPath, $m)) {
                try {
                    Cloudinary::uploadApi()->destroy($m[1], ['resource_type' => 'auto']);
                } catch (\Throwable $e) {
                    // Silently fail — asset may already be deleted
                }
            }
            return;
        }

        // Legacy local path fallback
        if (!str_starts_with($urlOrPath, 'http') && Storage::disk('public')->exists($urlOrPath)) {
            Storage::disk('public')->delete($urlOrPath);
        }
    }
}
