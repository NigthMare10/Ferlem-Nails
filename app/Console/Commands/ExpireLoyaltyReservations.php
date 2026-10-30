<?php

namespace App\Console\Commands;

use App\Actions\Loyalty\LoyaltyService;
use App\Models\LoyaltyRedemption;
use Illuminate\Console\Command;

class ExpireLoyaltyReservations extends Command
{
    protected $signature = 'loyalty:expire';

    protected $description = 'Release expired reward reservations and their points/stock';

    public function handle(LoyaltyService $service): int
    {
        LoyaltyRedemption::where('status', 'reserved')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($rows) use ($service) {
            foreach ($rows as $row) {
                $service->release($row, 'expired');
            }
        });

        return self::SUCCESS;
    }
}
