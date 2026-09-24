<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\InspectionRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public const TYPE_APPLICATION = 'application_fee';

    public const TYPE_CLEARANCE = 'clearance_fee';

    public function __construct(private readonly ClearanceIssuanceService $clearances) {}

    public function amountFor(?string $categorySlug, string $type): float
    {
        $categories = config('fees.categories', []);
        $defaults = config('fees.default', []);

        $schedule = $categories[$categorySlug] ?? $defaults;

        return (float) ($schedule[$type] ?? $defaults[$type] ?? 0);
    }

    public function scheduleFor(?string $categorySlug): array
    {
        return [
            self::TYPE_APPLICATION => $this->amountFor($categorySlug, self::TYPE_APPLICATION),
            self::TYPE_CLEARANCE => $this->amountFor($categorySlug, self::TYPE_CLEARANCE),
        ];
    }

    public function isPaid(InspectionRequest $request, string $type): bool
    {
        return $request->payments()
            ->where('type', $type)
            ->where('status', 'paid')
            ->exists();
    }

    public function ensurePending(InspectionRequest $request, string $type, ?int $inspectionId = null): Payment
    {
        $existing = $request->payments()
            ->where('type', $type)
            ->latest('id')
            ->first();

        if ($existing) {
            if ($inspectionId && ! $existing->inspection_id) {
                $existing->update(['inspection_id' => $inspectionId]);
            }

            return $existing;
        }

        $request->loadMissing('inspectionCategory');

        return $request->payments()->create([
            'inspection_id' => $inspectionId,
            'type' => $type,
            'amount' => $this->amountFor($request->inspectionCategory?->slug, $type),
            'method' => 'manual',
            'status' => 'pending',
        ]);
    }

    public function record(InspectionRequest $request, array $data, User $staff): Payment
    {
        $type = $data['type'];
        $method = $data['method'] ?? 'manual';

        if ($method === 'online') {
            throw new InvalidArgumentException('Online payment is coming soon. Record an over-the-counter payment instead.');
        }

        if ($this->isPaid($request, $type)) {
            throw new InvalidArgumentException('This fee has already been confirmed paid.');
        }

        if ($type === self::TYPE_CLEARANCE && $request->status !== 'inspection_completed') {
            throw new InvalidArgumentException('A clearance fee can only be recorded after a compliant inspection is completed.');
        }

        $request->loadMissing('inspectionCategory');

        $amount = isset($data['amount'])
            ? (float) $data['amount']
            : $this->amountFor($request->inspectionCategory?->slug, $type);

        $payment = $request->payments()
            ->where('type', $type)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $attributes = [
            'inspection_id' => $data['inspection_id'] ?? $payment?->inspection_id
                ?? ($type === self::TYPE_CLEARANCE
                    ? $request->inspections()->where('status', 'completed')->latest('id')->value('id')
                    : null),
            'type' => $type,
            'amount' => $amount,
            'method' => 'manual',
            'status' => 'pending',
            'reference_number' => null,
            'paid_at' => null,
            'confirmed_by' => null,
        ];

        if ($payment) {
            $payment->update($attributes);

            return $payment->fresh(['confirmedBy.role', 'inspectionRequest.inspectionCategory']);
        }

        return $request->payments()->create($attributes)->load(['confirmedBy.role', 'inspectionRequest.inspectionCategory']);
    }

    public function confirm(Payment $payment, array $data, User $staff): Payment
    {
        if ($payment->status === 'paid') {
            throw new InvalidArgumentException('This payment has already been confirmed and cannot be changed.');
        }

        if ($payment->type === self::TYPE_CLEARANCE && ! $payment->inspection_id) {
            throw new InvalidArgumentException('This clearance payment is not linked to a completed inspection.');
        }

        DB::transaction(function () use ($payment, $data, $staff) {
            $payment->update([
                'amount' => (float) $data['amount'],
                'reference_number' => $data['or_number'] ?? $this->nextOrNumber(),
                'status' => 'paid',
                'paid_at' => now(),
                'confirmed_by' => $staff->id,
            ]);

            $this->clearances->issueFor($payment->fresh());
        });

        return $payment->fresh(['confirmedBy.role', 'inspectionRequest.inspectionCategory']);
    }

    public function requestFromInspection(?int $inspectionId): ?InspectionRequest
    {
        if (! $inspectionId) {
            return null;
        }

        return Inspection::query()
            ->with('inspectionRequest')
            ->find($inspectionId)
            ?->inspectionRequest;
    }

    private function nextOrNumber(): string
    {
        $prefix = 'OR-'.now()->format('Y').'-';
        $last = Payment::query()
            ->where('reference_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference_number')
            ->value('reference_number');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
