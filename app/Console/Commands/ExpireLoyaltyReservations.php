<?php

namespace App\Console\Commands;

use App\Actions\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class ExpireLoyaltyReservations extends Command
{
    protected $signature = 'loyalty:expire';

    protected $description = 'Release expired reward reservations and their points/stock';

    public function handle(LoyaltyService $service): int
    {
        $service->expireDue();
        app(\App\Actions\Loyalty\GiftService::class)->expireDue();

        return self::SUCCESS;
    }
}
