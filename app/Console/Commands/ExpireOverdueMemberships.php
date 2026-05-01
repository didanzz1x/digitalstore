<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

class ExpireOverdueMemberships extends Command
{
    protected $signature = 'membership:expire-overdue';

    protected $description = 'Tandai membership yang sudah lewat expires_at sebagai expired dan turunkan flag is_member.';

    public function handle(MembershipService $service): int
    {
        $count = $service->expireOverdue();
        $this->info("Expired {$count} overdue membership(s).");

        return self::SUCCESS;
    }
}
