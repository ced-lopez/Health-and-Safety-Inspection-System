<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Certification\StoreIssuanceRequest;
use App\Http\Requests\Certification\UpdateIssuanceRequest;
use App\Http\Resources\CertificationResource;
use App\Http\Resources\ClearanceResource;
use App\Http\Resources\EstablishmentResource;
use App\Models\Certification;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $kind = $request->input('document_kind', 'all');
        $documents = collect();

        if ($kind === 'all' || $kind === 'certification') {
            $documents = $documents->merge(
                Certification::query()
                    ->with(['establishment', 'inspection', 'issuer.role', 'qrCode'])
                    ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('status', $request->input('status')))
                    ->get()
                    ->map(fn ($certification) => (new CertificationResource($certification))->resolve())
            );
        }

        if ($kind === 'all' || $kind === 'clearance') {
            $documents = $documents->merge(
                Clearance::query()
                    ->with(['establishment', 'inspection', 'issuer.role', 'qrCode'])
                    ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('status', $request->input('status')))
                    ->get()
                    ->map(fn ($clearance) => (new ClearanceResource($clearance))->resolve())
            );
        }

        if ($request->filled('search')) {
            $search = strtolower($request->string('search')->trim()->toString());
            $documents = $documents->filter(function ($document) use ($search) {
                return str_contains(strtolower($document['number'] ?? ''), $search)
                    || str_contains(strtolower($document['document_type'] ?? ''), $search)
                    || str_contains(strtolower($document['establishment']['name'] ?? ''), $search)
                    || str_contains(strtolower($document['establishment']['registration_number'] ?? ''), $search);
            });
        }

        $documents = $documents
            ->sortByDesc(fn ($document) => $document['issue_date'] ?? $document['created_at'])
            ->values();

        return $this->success([
            'documents' => $documents,
            'meta' => [
                'total' => $documents->count(),
            ],
        ], 'Certification and clearance documents listed successfully');
    }

    public function options(): JsonResponse
    {
        $establishments = Establishment::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $inspections = Inspection::query()
            ->with('establishment')
            ->whereIn('status', ['completed', 'ongoing'])
            ->orderByDesc('inspection_date')
            ->limit(100)
            ->get()
            ->map(fn (Inspection $inspection) => [
                'id' => $inspection->id,
                'inspection_date' => $inspection->inspection_date?->toDateString(),
                'status' => $inspection->status,
                'establishment_id' => $inspection->establishment_id,
                'establishment' => [
                    'id' => $inspection->establishment?->id,
                    'name' => $inspection->establishment?->name,
                    'registration_number' => $inspection->establishment?->registration_number,
                ],
            ]);

        return $this->success([
            'establishments' => EstablishmentResource::collection($establishments),
            'inspections' => $inspections,
        ], 'Certification options retrieved successfully');
    }

    public function store(StoreIssuanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $document = $validated['document_kind'] === 'certification'
            ? Certification::query()->create([
                'establishment_id' => $validated['establishment_id'],
                'inspection_id' => $validated['inspection_id'] ?? null,
                'issued_by' => $request->user()->id,
                'certificate_number' => $this->nextNumber('CERT'),
                'certificate_type' => $validated['document_type'],
                'issue_date' => $validated['issue_date'],
                'expiration_date' => $validated['expiration_date'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ])
            : Clearance::query()->create([
                'establishment_id' => $validated['establishment_id'],
                'inspection_id' => $validated['inspection_id'] ?? null,
                'issued_by' => $request->user()->id,
                'clearance_number' => $this->nextNumber('CLR'),
                'clearance_type' => $validated['document_type'],
                'purpose' => $validated['purpose'] ?? null,
                'issue_date' => $validated['issue_date'],
                'expiration_date' => $validated['expiration_date'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

        $this->ensureQrCode($document, strtoupper($validated['document_kind']));

        return $this->success(
            $this->resourceFor($document),
            'Document issued successfully',
            201
        );
    }

    public function update(UpdateIssuanceRequest $request, string $kind, int $id): JsonResponse
    {
        $document = $this->findDocument($kind, $id);
        $validated = $request->validated();

        $document->update($kind === 'certification' ? [
            'establishment_id' => $validated['establishment_id'],
            'inspection_id' => $validated['inspection_id'] ?? null,
            'certificate_type' => $validated['document_type'],
            'issue_date' => $validated['issue_date'],
            'expiration_date' => $validated['expiration_date'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ] : [
            'establishment_id' => $validated['establishment_id'],
            'inspection_id' => $validated['inspection_id'] ?? null,
            'clearance_type' => $validated['document_type'],
            'purpose' => $validated['purpose'] ?? null,
            'issue_date' => $validated['issue_date'],
            'expiration_date' => $validated['expiration_date'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->ensureQrCode($document, strtoupper($kind));

        return $this->success(
            $this->resourceFor($document),
            'Document updated successfully'
        );
    }

    public function destroy(string $kind, int $id): JsonResponse
    {
        $this->findDocument($kind, $id)->delete();

        return $this->success(null, 'Document archived successfully');
    }

    public function verify(string $code): JsonResponse
    {
        $qrCode = QrCode::query()
            ->where('code', $code)
            ->with('qrable')
            ->first();

        if (! $qrCode || ! $qrCode->is_active || ! $qrCode->qrable) {
            return $this->error('Document verification failed', 404);
        }

        $qrCode->increment('verification_count');
        $qrCode->update(['last_verified_at' => now()]);

        return $this->success([
            'qr_code' => new \App\Http\Resources\QrCodeResource($qrCode->refresh()),
            'document' => $this->resourceFor($qrCode->qrable),
        ], 'Document verified successfully');
    }

    private function findDocument(string $kind, int $id): Certification|Clearance
    {
        abort_unless(in_array($kind, ['certification', 'clearance'], true), 404);

        return $kind === 'certification'
            ? Certification::query()->findOrFail($id)
            : Clearance::query()->findOrFail($id);
    }

    private function resourceFor(Model $document): CertificationResource|ClearanceResource
    {
        $document->load(['establishment', 'inspection', 'issuer.role', 'qrCode']);

        return $document instanceof Certification
            ? new CertificationResource($document)
            : new ClearanceResource($document);
    }

    private function ensureQrCode(Model $document, string $prefix): void
    {
        if ($document->qrCode) {
            return;
        }

        $document->qrCode()->create([
            'code' => $prefix . '-' . Str::upper(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function nextNumber(string $prefix): string
    {
        return sprintf('%s-%s-%04d', $prefix, now()->format('Ymd'), random_int(1, 9999));
    }
}
