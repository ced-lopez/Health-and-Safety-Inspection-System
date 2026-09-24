<?php

namespace App\Services;

use App\Models\Clearance;
use App\Models\Payment;
use App\Notifications\ClearanceApproved;
use Illuminate\Support\Str;

/** Issues the one-year QR clearance unlocked by a confirmed clearance fee. */
class ClearanceIssuanceService
{
    public function issueFor(Payment $payment): ?Clearance
    {
        if ($payment->type !== PaymentService::TYPE_CLEARANCE || $payment->status !== 'paid' || ! $payment->inspection_id) {
            return null;
        }

        $payment->loadMissing('inspection.inspectionRequest.resident');
        $inspection = $payment->inspection;
        $inspectionRequest = $inspection?->inspectionRequest;

        if (! $inspection || ! $inspectionRequest || $inspectionRequest->status !== 'inspection_completed') {
            return null;
        }

        $clearance = Clearance::query()->firstOrCreate(
            ['inspection_id' => $inspection->id],
            [
                'establishment_id' => $inspection->establishment_id,
                'issued_by' => $payment->confirmed_by,
                'clearance_number' => $this->nextNumber(),
                'clearance_type' => 'Health and Safety Clearance',
                'purpose' => 'Health and safety inspection clearance',
                'issue_date' => today(),
                'expiration_date' => today()->addYear(),
                'status' => 'active',
                'notes' => 'Automatically issued after clearance-fee confirmation.',
            ]
        );

        $clearance->qrCode()->firstOrCreate([], [
            'code' => 'CLEARANCE-'.Str::upper(Str::random(12)),
            'is_active' => true,
        ]);

        if ($inspectionRequest->status === 'inspection_completed') {
            $inspectionRequest->update(['status' => 'clearance_approved']);
            $inspectionRequest->resident?->notify(new ClearanceApproved(
                $clearance->clearance_number,
                $inspectionRequest->applicant_name,
                $clearance->expiration_date->format('F j, Y'),
            ));
        }

        return $clearance;
    }

    private function nextNumber(): string
    {
        do {
            $number = sprintf('CLR-%s-%04d', now()->format('Ymd'), random_int(1, 9999));
        } while (Clearance::query()->where('clearance_number', $number)->exists());

        return $number;
    }
}
