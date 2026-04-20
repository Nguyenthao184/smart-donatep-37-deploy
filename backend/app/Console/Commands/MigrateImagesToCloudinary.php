<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaiDang;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MigrateImagesToCloudinary extends Command
{
    protected $signature = 'migrate:images-to-cloudinary {--from=0 : Start migration from this post ID} {--timeout=30 : Timeout in seconds for each upload}';
    protected $description = 'Migrate existing local images to Cloudinary and update database records';

    public function handle()
    {
        $this->info('Starting image migration to Cloudinary...');
        
        $startFrom = (int)$this->option('from');
        $timeout = (int)$this->option('timeout');

        try {
            $query = BaiDang::query();
            
            if ($startFrom > 0) {
                $query->where('id', '>=', $startFrom);
                $this->info("Resuming from post ID: {$startFrom}");
            }
            
            $totalPosts = $query->count();
            $this->info("Found {$totalPosts} posts to process.");

            $migratedCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            $query->chunk(10, function ($posts) use (&$migratedCount, &$failedCount, &$skippedCount, $timeout) {
                foreach ($posts as $post) {
                    if (!$post->hinh_anh) {
                        $skippedCount++;
                        continue;
                    }

                    $images = is_array($post->hinh_anh) ? $post->hinh_anh : json_decode($post->hinh_anh, true);
                    
                    if (!is_array($images) || empty($images)) {
                        $skippedCount++;
                        continue;
                    }

                    $updatedImages = [];
                    $hasLocalImages = false;

                    foreach ($images as $imagePath) {
                        // Check if it's already a Cloudinary URL
                        if (str_starts_with($imagePath, 'http')) {
                            $updatedImages[] = $imagePath;
                            continue;
                        }

                        $hasLocalImages = true;

                        try {
                            // Get full path to local image
                            $fullPath = storage_path("app/public/{$imagePath}");

                            if (!file_exists($fullPath)) {
                                $this->warn("Image not found: {$imagePath} (Post ID: {$post->id})");
                                $failedCount++;
                                $updatedImages[] = $imagePath;
                                continue;
                            }

                            // Upload to Cloudinary with timeout
                            $uploaded = Cloudinary::uploadApi()->upload($fullPath, [
                                'folder' => 'posts',
                                'resource_type' => 'auto',
                                'timeout' => $timeout * 1000,
                            ]);

                            $updatedImages[] = $uploaded['secure_url'];
                            $this->line("✓ Migrated: {$imagePath} → {$uploaded['secure_url']}");

                        } catch (\Exception $e) {
                            $errorMsg = $e->getMessage();
                            $this->error("Failed to upload {$imagePath} (Post {$post->id}): {$errorMsg}");
                            $failedCount++;
                            // Keep original path if upload failed
                            $updatedImages[] = $imagePath;
                        }
                    }

                    // Update the post with new image URLs if any local images were found
                    if ($hasLocalImages) {
                        try {
                            $post->hinh_anh = $updatedImages;
                            $post->save();
                            $migratedCount++;
                            $this->line("✓ Updated post {$post->id}");
                        } catch (\Exception $e) {
                            $this->error("Failed to update post {$post->id}: {$e->getMessage()}");
                            $failedCount++;
                        }
                    } else {
                        $skippedCount++;
                    }
                }
            });

            $this->info("\n========== Migration Summary ==========");
            $this->line("Posts migrated: {$migratedCount}");
            $this->line("Posts skipped (already using Cloudinary): {$skippedCount}");
            $this->line("Failed operations: {$failedCount}");
            
            if ($migratedCount + $skippedCount + $failedCount < $totalPosts) {
                $remaining = $totalPosts - ($migratedCount + $skippedCount + $failedCount);
                $lastPostId = BaiDang::where('id', '>=', (int)$this->option('from'))
                    ->latest('id')
                    ->limit(1)
                    ->value('id');
                $this->info("\nTo continue migration, run:");
                $this->line("php artisan migrate:images-to-cloudinary --from=" . ($lastPostId + 1));
            }
            
            $this->info("=======================================\n");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            Log::error("Image migration failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
