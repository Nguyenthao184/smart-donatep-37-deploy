<?php

namespace App\Listeners;

use App\Events\CampaignCreated;
use App\Jobs\CheckCampaignFraudJob;

class CheckNewCampaignFraudListener
{
    public function handle(CampaignCreated $event): void
    {
        if ($event->campaignId <= 0) {
            return;
        }

        CheckCampaignFraudJob::dispatch($event->campaignId, 'campaign_created');
    }
}

