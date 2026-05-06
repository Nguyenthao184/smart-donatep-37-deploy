<?php

namespace App\Providers;

use App\Events\CampaignCreated;
use App\Events\CampaignImportantUpdated;
use App\Events\DonationCreated;
use App\Listeners\CheckCampaignFraudListener;
use App\Listeners\CheckNewCampaignFraudListener;
use App\Listeners\CheckUpdatedCampaignFraudListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DonationCreated::class => [
            CheckCampaignFraudListener::class,
        ],
        CampaignCreated::class => [
            CheckNewCampaignFraudListener::class,
        ],
        CampaignImportantUpdated::class => [
            CheckUpdatedCampaignFraudListener::class,
        ],
    ];
}

