<?php

namespace App\Jobs;

use App\Models\BaiDang;
use App\Services\FraudDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectFraudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $postId;
    protected int $userId;

    public function __construct(int $postId, int $userId)
    {
        $this->postId = $postId;
        $this->userId = $userId;
    }

    public function handle(FraudDetectionService $fraudService)
    {
        $post = BaiDang::find($this->postId);
        if (!$post) {
            Log::warning('DetectFraudJob: Post not found', ['post_id' => $this->postId]);
            return;
        }

        try {
            $fraudService->checkPost(
                $this->userId,
                $this->postId,
                (string) $post->tieu_de,
                (string) $post->mo_ta
            );
            Log::info('DetectFraudJob: Fraud check completed', ['post_id' => $this->postId]);
        } catch (\Throwable $e) {
            Log::error('DetectFraudJob: Fraud check failed', [
                'post_id' => $this->postId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
