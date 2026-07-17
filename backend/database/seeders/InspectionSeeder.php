<?php

namespace Database\Seeders;

use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InspectionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@barangay178.gov.ph')->first();
        $inspector = User::query()->where('email', 'inspector@barangay178.gov.ph')->first();
        $establishments = Establishment::all();

        if ($establishments->isEmpty() || !$admin || !$inspector) {
            return;
        }

        // 1. Seed completed inspections (historical)
        $completedRecords = [
            [
                'establishment' => $establishments->firstWhere('registration_number', 'B178-RET-001'), // Sunrise Mini Mart
                'date' => Carbon::now()->subDays(2),
                'assessment' => 'Premises are clean and compliant with basic sanitary protocols. Minor layout adjustment suggested.',
            ],
            [
                'establishment' => $establishments->firstWhere('registration_number', 'B178-FNB-002'), // Green Valley Restaurant
                'date' => Carbon::now()->subDays(1),
                'assessment' => 'Sanitary standards met, but fire extinguisher pressure tag needs immediate replacement.',
            ],
        ];

        foreach ($completedRecords as $record) {
            if (!$record['establishment']) {
                continue;
            }

            // Create Schedule
            $schedule = InspectionSchedule::query()->create([
                'establishment_id' => $record['establishment']->id,
                'inspector_id' => $inspector->id,
                'scheduled_by' => $admin->id,
                'scheduled_date' => $record['date']->toDateString(),
                'scheduled_time' => '10:00:00',
                'status' => 'completed',
                'notes' => 'Routine monthly safety check',
            ]);

            // Create Inspection
            Inspection::query()->create([
                'inspection_schedule_id' => $schedule->id,
                'establishment_id' => $record['establishment']->id,
                'inspector_id' => $inspector->id,
                'inspection_date' => $record['date']->toDateString(),
                'status' => 'completed',
                'overall_assessment' => $record['assessment'],
                'recommendations' => 'Keep maintaining current standards.',
                'started_at' => $record['date']->copy()->setTime(10, 0, 0),
                'completed_at' => $record['date']->copy()->setTime(11, 0, 0),
            ]);
        }

        // 2. Seed ongoing inspections
        $ongoingEstablishment = $establishments->firstWhere('registration_number', 'B178-PHR-003'); // Pharmacy
        if ($ongoingEstablishment) {
            $schedule = InspectionSchedule::query()->create([
                'establishment_id' => $ongoingEstablishment->id,
                'inspector_id' => $inspector->id,
                'scheduled_by' => $admin->id,
                'scheduled_date' => Carbon::now()->toDateString(),
                'scheduled_time' => '14:00:00',
                'status' => 'ongoing',
            ]);

            Inspection::query()->create([
                'inspection_schedule_id' => $schedule->id,
                'establishment_id' => $ongoingEstablishment->id,
                'inspector_id' => $inspector->id,
                'inspection_date' => Carbon::now()->toDateString(),
                'status' => 'ongoing',
                'started_at' => Carbon::now()->setTime(14, 0, 0),
            ]);
        }

        // 3. Seed scheduled inspections (upcoming)
        $upcomingEstablishments = [
            $establishments->firstWhere('registration_number', 'B178-AUT-004'),
            $establishments->firstWhere('registration_number', 'B178-EDU-005'),
        ];

        foreach ($upcomingEstablishments as $index => $est) {
            if (!$est) {
                continue;
            }

            $date = Carbon::now()->addDays($index + 1);

            InspectionSchedule::query()->create([
                'establishment_id' => $est->id,
                'inspector_id' => $inspector->id,
                'scheduled_by' => $admin->id,
                'scheduled_date' => $date->toDateString(),
                'scheduled_time' => '09:00:00',
                'status' => 'scheduled',
            ]);

            Inspection::query()->create([
                'establishment_id' => $est->id,
                'inspector_id' => $inspector->id,
                'inspection_date' => $date->toDateString(),
                'status' => 'scheduled',
            ]);
        }

        // 4. Seed violations
        $violator = $establishments->firstWhere('registration_number', 'B178-FNB-002'); // Green Valley Restaurant
        $inspection = Inspection::query()->where('establishment_id', $violator?->id)->where('status', 'completed')->first();

        if ($violator && $inspection) {
            Violation::query()->create([
                'inspection_id' => $inspection->id,
                'establishment_id' => $violator->id,
                'reported_by' => $inspector->id,
                'title' => 'Expired Fire Extinguisher tag',
                'description' => 'The primary dry chemical fire extinguisher near the cooking range was last inspected in 2024. Active tag is expired.',
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => Carbon::now()->addDays(7)->toDateString(),
            ]);

            Violation::query()->create([
                'inspection_id' => $inspection->id,
                'establishment_id' => $violator->id,
                'reported_by' => $inspector->id,
                'title' => 'Improper kitchen waste segregation',
                'description' => 'Organic kitchen waste was mixed with plastic and aluminum packaging in the preparation area bin.',
                'severity' => 'minor',
                'status' => 'open',
                'correction_deadline' => Carbon::now()->addDays(3)->toDateString(),
            ]);
        }
    }
}
