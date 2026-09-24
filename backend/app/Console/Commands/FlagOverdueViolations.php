<?php

namespace App\Console\Commands;

use App\Models\Violation;
use Illuminate\Console\Command;

class FlagOverdueViolations extends Command
{
    protected $signature = 'violations:flag-overdue';

    protected $description = 'Flag unresolved violations past their correction deadline for staff visibility';

    public function handle(): int
    {
        $count = Violation::query()
            ->where('status', 'open')
            ->whereNotNull('correction_deadline')
            ->where('correction_deadline', '<', today())
            ->whereDoesntHave('inspection.inspectionRequest', fn ($query) => $query->where('status', 'follow_up_requested'))
            ->update(['status' => 'overdue']);

        $this->info("Overdue violations flagged: {$count}");

        return self::SUCCESS;
    }
}
