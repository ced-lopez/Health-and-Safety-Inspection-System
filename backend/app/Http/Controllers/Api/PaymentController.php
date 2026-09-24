<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\InspectionRequest;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\DocumentPdfService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends BaseApiController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly DocumentPdfService $pdfService,
    ) {}

    public function globalIndex(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('role');
        $slug = $user?->role?->slug;

        $query = Payment::query()
            ->with(['inspectionRequest.inspectionCategory', 'inspectionRequest.resident', 'confirmedBy.role', 'inspection'])
            ->orderByDesc('id');

        if ($slug === 'resident') {
            $query->whereHas('inspectionRequest', fn ($q) => $q->where('resident_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = strtolower(trim($request->input('search')));
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', [$like])
                    ->orWhereHas('inspectionRequest', function ($rq) use ($like) {
                        $rq->whereRaw('LOWER(request_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(business_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(applicant_name) LIKE ?', [$like]);
                    });
            });
        }

        $perPage = min($request->integer('per_page', 15), 50);
        $payments = $query->paginate($perPage);

        return $this->success([
            'payments' => PaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ], 'Payments retrieved successfully');
    }

    public function index(InspectionRequest $inspectionRequest): JsonResponse
    {
        if ($denied = $this->authorizeView($inspectionRequest, request())) {
            return $denied;
        }

        $inspectionRequest->loadMissing('inspectionCategory');

        $payments = $inspectionRequest->payments()
            ->with('confirmedBy.role')
            ->orderByDesc('id')
            ->get();

        return $this->success([
            'payments' => PaymentResource::collection($payments),
            'fee_schedule' => $this->payments->scheduleFor($inspectionRequest->inspectionCategory?->slug),
            'payment_status' => [
                'application_fee_paid' => $this->payments->isPaid($inspectionRequest, PaymentService::TYPE_APPLICATION),
                'clearance_fee_paid' => $this->payments->isPaid($inspectionRequest, PaymentService::TYPE_CLEARANCE),
            ],
        ], 'Payments retrieved successfully');
    }

    public function store(StorePaymentRequest $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        try {
            $payment = $this->payments->record($inspectionRequest, $request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        AuditLogger::log(
            $request->user(),
            'Payments',
            'Awaiting Confirmation',
            "Recorded pending {$payment->type} payment for request {$inspectionRequest->request_number}",
            $payment,
            $request,
            newValues: [
                'type' => $payment->type,
                'amount' => $payment->amount,
                'method' => $payment->method,
            ],
            event: 'payment.recorded',
        );

        return $this->success(
            new PaymentResource($payment),
            'Payment recorded and awaiting confirmation',
            201
        );
    }

    public function confirm(\Illuminate\Http\Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'or_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $payment = $this->payments->confirm($payment, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        AuditLogger::log(
            $request->user(), 'Payments', 'Confirmed',
            "Confirmed {$payment->type} payment for request {$payment->inspectionRequest->request_number}",
            $payment, $request,
            newValues: ['amount' => $payment->amount, 'or_number' => $payment->reference_number],
            event: 'payment.confirmed',
        );

        return $this->success(new PaymentResource($payment), 'Payment confirmed successfully');
    }

    public function receipt(InspectionRequest $inspectionRequest, Payment $payment): Response
    {
        if ($denied = $this->authorizeView($inspectionRequest, request())) {
            return $denied;
        }

        abort_unless($payment->inspection_request_id === $inspectionRequest->id, 404);
        abort_unless($payment->status === 'paid', 422, 'A receipt is available only for confirmed payments.');

        $payment->load([
            'confirmedBy.role',
            'inspectionRequest.inspectionCategory',
            'inspectionRequest.resident',
        ]);

        $filename = 'receipt-'.$inspectionRequest->request_number.'-'.$payment->type.'.pdf';

        return $this->pdfService->receiptPdf($payment)->stream($filename);
    }

    public function receiptGlobal(\Illuminate\Http\Request $request, Payment $payment): Response
    {
        $payment->loadMissing('inspectionRequest');
        $inspectionRequest = $payment->inspectionRequest;

        if (! $inspectionRequest) {
            abort(404);
        }

        if ($denied = $this->authorizeView($inspectionRequest, $request)) {
            return $denied;
        }

        abort_unless($payment->status === 'paid', 422, 'A receipt is available only for confirmed payments.');

        $payment->load([
            'confirmedBy.role',
            'inspectionRequest.inspectionCategory',
            'inspectionRequest.resident',
        ]);

        $filename = 'receipt-'.$inspectionRequest->request_number.'-'.$payment->type.'.pdf';

        return $this->pdfService->receiptPdf($payment)->stream($filename);
    }

    private function authorizeView(InspectionRequest $inspectionRequest, $request): ?JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('role');
        $slug = $user?->role?->slug;

        if (in_array($slug, ['administrator', 'barangay_staff'], true)) {
            return null;
        }

        if ($slug === 'resident' && $inspectionRequest->resident_id === $user->id) {
            return null;
        }

        return $this->error('You are not allowed to view these payments.', 403);
    }
}
