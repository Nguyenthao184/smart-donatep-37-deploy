<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignImportantUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int, string> $changedFields
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly int $ownerUserId,
        public readonly array $changedFields = [],
    ) {}
}

