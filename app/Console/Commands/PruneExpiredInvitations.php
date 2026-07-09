<?php

namespace App\Console\Commands;

use App\Models\UserInvitation;
use Illuminate\Console\Command;

class PruneExpiredInvitations extends Command
{
    protected $signature = 'invitations:prune {--days=30 : Số ngày sau expires_at để xoá}';
    protected $description = 'Xoá user_invitations đã hết hạn và không được accept sau N ngày.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = UserInvitation::whereNull('accepted_at')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$count} expired invitations (older than {$days} days).");
        return self::SUCCESS;
    }
}
