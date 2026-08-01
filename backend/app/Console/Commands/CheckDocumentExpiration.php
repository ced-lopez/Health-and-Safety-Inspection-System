<?php

namespace App\Console\Commands;

use App\Models\Certification;
use App\Models\Clearance;
use Illuminate\Console\Command;

class CheckDocumentExpiration extends Command
{
    protected $signature = 'documents:check-expiration';

    protected $description = 'Mark active clearances and certifications as expired when their expiration date has passed';

    public function handle(): int
    {
        $today = now()->toDateString();

        $clearances = Clearance::query()
            ->where('status', 'active')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', $today)
            ->update(['status' => 'expired']);

        $certifications = Certification::query()
            ->where('status', 'active')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', $today)
            ->update(['status' => 'expired']);

        $this->info("Expired clearances: {$clearances}, expired certifications: {$certifications}");

        return self::SUCCESS;
    }
}
