<?php

namespace App\Jobs;

use App\Services\CampaignFraudDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCampaignFraudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $trigger,
    ) {}

    public function handle(CampaignFraudDetectionService $service): void
    {
        $service->detectCampaign($this->campaignId, $this->trigger);
    }
}

