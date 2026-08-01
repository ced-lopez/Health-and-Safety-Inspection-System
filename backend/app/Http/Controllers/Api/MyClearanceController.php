<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ClearanceResource;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Services\AuditLogger;
use App\Services\DocumentPdfService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyClearanceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentPdfService $pdfService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $kind = $request->input('document_kind', 'all');
        if (! in_array($kind, ['all', 'clearance'], true)) {
            return $this->success([
                'documents' => [],
                'meta' => ['total' => 0],
            ], 'Clearance documents listed successfully');
        }

        $perPage = min($request->integer('per_page', 25), 50);
        $search = $request->filled('search')
            ? strtolower($request->string('search')->trim()->toString())
            : null;
        $like = $search ? "%{$search}%" : null;

        $establishmentIds = Establishment::query()
            ->where('resident_id', $user->id)
            ->where('ownership_status', 'linked')
            ->pluck('id');

        $query = Clearance::query()
            ->with(['establishment', 'inspection', 'issuer.role', 'qrCode'])
            ->whereIn('establishment_id', $establishmentIds)
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', $request->input('status')))
            ->when($like, function ($builder) use ($like) {
                $builder->where(function ($clearanceQuery) use ($like) {
                    $clearanceQuery
                        ->whereRaw('LOWER(clearance_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(clearance_type) LIKE ?', [$like])
                        ->orWhereHas('establishment', function ($establishmentQuery) use ($like) {
                            $establishmentQuery->whereRaw('LOWER(name) LIKE ?', [$like]);
                        });
                });
            });

        $paginator = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $documents = $paginator
            ->getCollection()
            ->map(fn (Clearance $clearance) => (new ClearanceResource($clearance))->resolve());

        return $this->success([
            'documents' => $documents,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Clearance documents listed successfully');
    }

    public function pdf(Request $request, Clearance $clearance): Response
    {
        $this->authorize('downloadPdf', $clearance);

        AuditLogger::log(
            $request->user(),
            'Clearance',
            'Downloaded',
            "Downloaded clearance {$clearance->clearance_number}",
            $clearance,
            $request,
            event: 'clearance.downloaded',
        );

        return $this->pdfService
            ->pdf($clearance)
            ->stream('clearance-'.$clearance->clearance_number.'.pdf');
    }
}
