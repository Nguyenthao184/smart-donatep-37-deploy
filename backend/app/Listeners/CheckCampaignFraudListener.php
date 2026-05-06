<?php

namespace App\Listeners;

use App\Events\DonationCreated;
use App\Jobs\CheckCampaignFraudJob;

class CheckCampaignFraudListener
{
    public function handle(DonationCreated $event): void
    {
        if ($event->campaignId <= 0) {
            return;
        }

        CheckCampaignFraudJob::dispatch($event->campaignId, 'donation_created');
    }
}

