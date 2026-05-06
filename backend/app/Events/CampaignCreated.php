<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $ownerUserId,
    ) {}
}

