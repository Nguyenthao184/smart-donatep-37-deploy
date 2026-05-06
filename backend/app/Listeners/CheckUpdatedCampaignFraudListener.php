<?php

namespace App\Listeners;

use App\Events\CampaignImportantUpdated;
use App\Jobs\CheckCampaignFraudJob;

class CheckUpdatedCampaignFraudListener
{
    public function handle(CampaignImportantUpdated $event): void
    {
        if ($event->campaignId <= 0) {
            return;
        }

        CheckCampaignFraudJob::dispatch($event->campaignId, 'campaign_important_updated');
    }
}

