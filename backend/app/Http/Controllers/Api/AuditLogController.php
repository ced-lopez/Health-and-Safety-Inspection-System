<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filtered = $request->hasAny(['event', 'module', 'action', 'user_id', 'from', 'to', 'archived']);

        if ($user->role?->slug === 'barangay_staff' && $filtered) {
            return $this->error('Only administrators can filter audit logs.', 403);
        }

        $query = $this->baseQuery($request);

        $perPage = min($request->integer('per_page', 20), 50);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'logs' => collect($logs->items())->map(fn ($log) => $this->serialize($log))->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'Audit logs retrieved successfully');
    }

    public function filters(Request $request): JsonResponse
    {
        return $this->success([
            'modules' => AuditLog::query()
                ->whereNull('archived_at')
                ->whereNotNull('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => AuditLog::query()
                ->whereNull('archived_at')
                ->whereNotNull('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'users' => User::query()
                ->with('role')
                ->whereHas('auditLogs', fn ($query) => $query->whereNull('archived_at'))
                ->orderBy('name')
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role?->name,
                ]),
        ], 'Audit log filter options retrieved successfully');
    }

    public function archive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'before' => ['nullable', 'date'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $before = ! empty($validated['before'])
            ? Carbon::parse($validated['before'])->endOfDay()
            : Carbon::now()->subDays(30);

        $query = AuditLog::query()
            ->whereNull('archived_at')
            ->where('created_at', '<', $before);

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $count = $query->count();

        if ($count > 0) {
            $query->update(['archived_at' => now()]);
        }

        return $this->success([
            'archived_count' => $count,
            'cutoff' => $before,
        ], "{$count} audit log(s) archived. Archived logs are preserved and never deleted.");
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->input('format', 'csv');
        abort_unless(in_array($format, ['csv', 'excel', 'pdf'], true), 422);

        $logs = $this->baseQuery($request)->orderByDesc('created_at')->get();

        return match ($format) {
            'excel' => $this->exportExcel($logs),
            'pdf' => $this->exportPdf($logs),
            default => $this->exportCsv($logs),
        };
    }

    private function baseQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('user.role');

        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        if ($request->filled('event')) {
            $query->where('event', 'like', $request->input('event').'%');
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->input('action').'%');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        return $query;
    }

    private function exportCsv($logs): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Timestamp', 'Event', 'Module', 'Action', 'Description',
                'User', 'Role', 'Auditable Type', 'Auditable ID',
                'Old Values', 'New Values', 'IP Address', 'Archived At',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, $this->row($log));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function exportExcel($logs): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Logs');

        $headers = [
            'ID', 'Timestamp', 'Event', 'Module', 'Action', 'Description',
            'User', 'Role', 'Auditable Type', 'Auditable ID',
            'Old Values', 'New Values', 'IP Address', 'Archived At',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(array_map(fn ($log) => $this->row($log), $logs->all()), null, 'A2');

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.xlsx';

        return Response::streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function exportPdf($logs): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.pdf';

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs' => $logs,
            'generated_at' => now()->format('F j, Y g:i A'),
        ])->setPaper('a4', 'landscape');

        return Response::streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function row(AuditLog $log): array
    {
        return [
            $log->id,
            $log->created_at?->toIso8601String(),
            $log->event,
            $log->module,
            $log->action,
            $log->description,
            $log->user?->name,
            $log->user?->role?->name,
            class_basename($log->auditable_type),
            $log->auditable_id,
            $log->old_values ? json_encode($log->old_values) : null,
            $log->new_values ? json_encode($log->new_values) : null,
            $log->ip_address,
            $log->archived_at?->toIso8601String(),
        ];
    }

    private function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event,
            'module' => $log->module,
            'action' => $log->action,
            'description' => $log->description,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'role' => $log->user->role?->name,
            ] : null,
            'auditable_type' => class_basename($log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'archived_at' => $log->archived_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
