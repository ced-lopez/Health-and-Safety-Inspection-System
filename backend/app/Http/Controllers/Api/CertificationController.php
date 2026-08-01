<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Certification\StoreIssuanceRequest;
use App\Http\Requests\Certification\UpdateIssuanceRequest;
use App\Http\Resources\CertificationResource;
use App\Http\Resources\ClearanceResource;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\QrCodeResource;
use App\Models\Certification;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\QrCode;
use App\Services\AuditLogger;
use App\Services\DocumentPdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CertificationController extends BaseApiController
{
    public function __construct(private readonly DocumentPdfService $pdfService) {}

    public function index(Request $request): JsonResponse
    {
        $kind = $request->input('document_kind', 'all');
        $perKindLimit = min($request->integer('per_page', 25), 50);
        $search = $request->filled('search')
            ? strtolower($request->string('search')->trim()->toString())
            : null;
        $documents = collect();

        if ($kind === 'all' || $kind === 'certification') {
            $documents = $documents->merge(
                Certification::query()
                    ->with(['establishment', 'inspection', 'issuer.role', 'qrCode'])
                    ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('status', $request->input('status')))
                    ->when($search, fn ($query) => $this->applyCertificationSearch($query, $search))
                    ->orderByDesc('issue_date')
                    ->orderByDesc('id')
                    ->limit($perKindLimit)
                    ->get()
                    ->map(fn ($certification) => (new CertificationResource($certification))->resolve())
            );
        }

        if ($kind === 'all' || $kind === 'clearance') {
            $documents = $documents->merge(
                Clearance::query()
                    ->with(['establishment', 'inspection', 'issuer.role', 'qrCode'])
                    ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('status', $request->input('status')))
                    ->when($search, fn ($query) => $this->applyClearanceSearch($query, $search))
                    ->orderByDesc('issue_date')
                    ->orderByDesc('id')
                    ->limit($perKindLimit)
                    ->get()
                    ->map(fn ($clearance) => (new ClearanceResource($clearance))->resolve())
            );
        }

        $documents = $documents
            ->sortByDesc(fn ($document) => $document['issue_date'] ?? $document['created_at'])
            ->take($perKindLimit)
            ->values();

        return $this->success([
            'documents' => $documents,
            'meta' => [
                'total' => $documents->count(),
            ],
        ], 'Certification and clearance documents listed successfully');
    }

    private function applyCertificationSearch($query, string $search): void
    {
        $like = "%{$search}%";

        $query->where(function ($documentQuery) use ($like) {
            $documentQuery
                ->whereRaw('LOWER(certificate_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(certificate_type) LIKE ?', [$like])
                ->orWhereHas('establishment', function ($establishmentQuery) use ($like) {
                    $establishmentQuery
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(registration_number) LIKE ?', [$like]);
                });
        });
    }

    private function applyClearanceSearch($query, string $search): void
    {
        $like = "%{$search}%";

        $query->where(function ($documentQuery) use ($like) {
            $documentQuery
                ->whereRaw('LOWER(clearance_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(clearance_type) LIKE ?', [$like])
                ->orWhereHas('establishment', function ($establishmentQuery) use ($like) {
                    $establishmentQuery
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(registration_number) LIKE ?', [$like]);
                });
        });
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

        $document = DB::transaction(function () use ($request, $validated) {
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

            return $document;
        });

        $this->logDocumentEvent(
            $document,
            $this->moduleFor($document),
            'Issued',
            'Issued '.$this->numberFor($document),
            [],
            $request,
        );

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

        $this->logDocumentEvent(
            $document,
            $this->moduleFor($document),
            'Updated',
            'Updated '.$this->numberFor($document),
            [],
            $request,
        );

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

    public function downloadPdf(Request $request, string $kind, int $id): Response
    {
        $document = $this->findDocument($kind, $id);

        $filename = $document instanceof Clearance
            ? 'clearance-'.$document->clearance_number.'.pdf'
            : 'certificate-'.$document->certificate_number.'.pdf';

        $this->logDocumentEvent(
            $document,
            $this->moduleFor($document),
            'Downloaded',
            'Downloaded '.$this->numberFor($document),
            [],
            $request,
            "{$kind}.downloaded",
        );

        return $this->pdfService->pdf($document)->stream($filename);
    }

    public function approve(Request $request, string $kind, int $id): JsonResponse
    {
        $document = $this->findDocument($kind, $id);

        if (! in_array($document->status, ['pending', 'expired'], true)) {
            return $this->error('Only pending or expired documents can be approved.', 422);
        }

        $document->update(['status' => 'active']);
        $document->qrCode?->update(['is_active' => true]);

        $this->logDocumentEvent(
            $document,
            $this->moduleFor($document),
            'Approved',
            'Approved '.$this->numberFor($document),
            ['status' => 'active'],
            $request,
            $kind.'.approved',
        );

        return $this->success(
            $this->resourceFor($document),
            'Document approved successfully'
        );
    }

    public function revoke(Request $request, string $kind, int $id): JsonResponse
    {
        $document = $this->findDocument($kind, $id);

        if ($document->status !== 'active') {
            return $this->error('Only active documents can be revoked.', 422);
        }

        $document->update(['status' => 'revoked']);
        $document->qrCode?->update(['is_active' => false]);

        $this->logDocumentEvent(
            $document,
            $this->moduleFor($document),
            'Revoked',
            'Revoked '.$this->numberFor($document),
            ['status' => 'revoked'],
            $request,
            $kind.'.revoked',
        );

        return $this->success(
            $this->resourceFor($document),
            'Document revoked successfully'
        );
    }

    public function renew(Request $request, string $kind, int $id): JsonResponse
    {
        $document = $this->findDocument($kind, $id);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,active'],
        ]);

        $renewed = DB::transaction(function () use ($document, $kind, $request, $validated) {
            if (in_array($document->status, ['active', 'expired'], true)) {
                $document->update(['status' => 'expired']);
                $document->qrCode?->update(['is_active' => false]);
            }

            $issueDate = now();

            $renewed = $document instanceof Clearance
                ? Clearance::query()->create([
                    'establishment_id' => $document->establishment_id,
                    'inspection_id' => $document->inspection_id,
                    'issued_by' => $request->user()->id,
                    'clearance_number' => $this->nextNumber('CLR'),
                    'clearance_type' => $document->clearance_type,
                    'purpose' => $document->purpose,
                    'issue_date' => $issueDate->toDateString(),
                    'expiration_date' => $issueDate->copy()->addYear()->toDateString(),
                    'status' => $validated['status'] ?? 'active',
                    'notes' => 'Renewed from '.$document->clearance_number,
                ])
                : Certification::query()->create([
                    'establishment_id' => $document->establishment_id,
                    'inspection_id' => $document->inspection_id,
                    'issued_by' => $request->user()->id,
                    'certificate_number' => $this->nextNumber('CERT'),
                    'certificate_type' => $document->certificate_type,
                    'issue_date' => $issueDate->toDateString(),
                    'expiration_date' => $issueDate->copy()->addYear()->toDateString(),
                    'status' => $validated['status'] ?? 'active',
                    'notes' => 'Renewed from '.$document->certificate_number,
                ]);

            $this->ensureQrCode($renewed, strtoupper($kind));

            return $renewed;
        });

        $this->logDocumentEvent(
            $renewed,
            $this->moduleFor($renewed),
            'Renewed',
            'Renewed '.$this->numberFor($document).' -> '.$this->numberFor($renewed),
            [
                'new_id' => $renewed->id,
                'new_number' => $renewed instanceof Clearance ? $renewed->clearance_number : $renewed->certificate_number,
            ],
            $request,
            $kind.'.renewed',
        );

        return $this->success(
            $this->resourceFor($renewed),
            'Document renewed successfully',
            201
        );
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

        AuditLogger::log(
            null,
            'QR Verification',
            'Verified',
            "QR code {$qrCode->code} verified for ".$this->numberFor($qrCode->qrable),
            $qrCode->qrable,
            request(),
            event: 'qr.verified',
        );

        return $this->success([
            'qr_code' => new QrCodeResource($qrCode->refresh()),
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
            'code' => $prefix.'-'.Str::upper(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function nextNumber(string $prefix): string
    {
        return sprintf('%s-%s-%04d', $prefix, now()->format('Ymd'), random_int(1, 9999));
    }

    private function moduleFor(Model $document): string
    {
        return $document instanceof Clearance ? 'Clearance' : 'Certification';
    }

    private function numberFor(Model $document): string
    {
        return $document instanceof Clearance
            ? "clearance {$document->clearance_number}"
            : "certificate {$document->certificate_number}";
    }

    private function logDocumentEvent(
        Model $document,
        string $module,
        string $action,
        string $description,
        array $newValues = [],
        ?Request $request = null,
        ?string $event = null,
    ): void {
        AuditLogger::log(
            $request?->user(),
            $module,
            $action,
            $description,
            $document,
            $request,
            newValues: $newValues,
            event: $event,
        );
    }
}
