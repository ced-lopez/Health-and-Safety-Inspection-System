<?php

namespace App\Http\Controllers\Api;

use App\Models\Clearance;
use App\Models\Inspection;
use App\Models\InspectionRequest;
use App\Models\InspectionResult;
use App\Models\Violation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends BaseApiController
{
    public function inspections(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $monthly = Inspection::query()
            ->selectRaw($this->monthExpression('inspection_date').' as month')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->whereYear('inspection_date', $year)
            ->groupByRaw($this->monthExpression('inspection_date'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'total' => (int) ($monthly->get($m)?->total ?? 0),
            'completed' => (int) ($monthly->get($m)?->completed ?? 0),
        ]);

        return $this->success([
            'year' => $year,
            'monthly' => $months,
            'total_inspections' => $months->sum('total'),
            'total_completed' => $months->sum('completed'),
        ], 'Inspection report retrieved successfully');
    }

    public function violations(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $bySeverity = Violation::query()
            ->selectRaw('severity, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $byStatus = Violation::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->success([
            'year' => $year,
            'by_severity' => [
                'minor' => (int) ($bySeverity->get('minor', 0)),
                'moderate' => (int) ($bySeverity->get('moderate', 0)),
                'major' => (int) ($bySeverity->get('major', 0)),
            ],
            'by_status' => [
                'open' => (int) ($byStatus->get('open', 0)),
                'resolved' => (int) ($byStatus->get('resolved', 0)),
            ],
            'total' => $bySeverity->sum(),
        ], 'Violation report retrieved successfully');
    }

    public function clearances(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $monthly = Clearance::query()
            ->selectRaw($this->monthExpression('issue_date').' as month')
            ->selectRaw('COUNT(*) as issued')
            ->whereYear('issue_date', $year)
            ->groupByRaw($this->monthExpression('issue_date'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'issued' => (int) ($monthly->get($m)?->issued ?? 0),
        ]);

        $expiring = Clearance::query()
            ->where('status', 'active')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $expired = Clearance::query()
            ->where('status', 'active')
            ->where('expiration_date', '<', now())
            ->count();

        return $this->success([
            'year' => $year,
            'monthly' => $months,
            'total_issued' => $months->sum('issued'),
            'expiring_soon' => $expiring,
            'expired' => $expired,
        ], 'Clearance report retrieved successfully');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->dashboardData();

        $recentRequests = InspectionRequest::query()
            ->with(['inspectionCategory', 'resident.role'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'request_number' => $r->request_number,
                'applicant_name' => $r->applicant_name,
                'category' => $r->inspectionCategory?->name,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $this->success([
            ...$data,
            'recent_requests' => $recentRequests,
        ], 'Dashboard analytics retrieved successfully');
    }

    private function dashboardData(): array
    {
        return [
            'pending_requests' => InspectionRequest::query()
                ->whereIn('status', ['submitted', 'under_review'])->count(),
            'active_violations' => Violation::query()
                ->whereIn('status', ['open', 'under_review'])->count(),
            'completed_inspections_ytd' => Inspection::query()
                ->where('status', 'completed')
                ->whereYear('inspection_date', now()->year)->count(),
            'expiring_clearances' => Clearance::query()
                ->where('status', 'active')
                ->whereBetween('expiration_date', [now(), now()->addDays(30)])->count(),
        ];
    }

    public function soba(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $semester = $request->integer('semester', now()->month >= 7 ? 2 : 1);

        abort_unless(in_array($semester, [1, 2], true), 422, 'Semester must be 1 or 2.');

        $startMonth = $semester === 1 ? 1 : 7;
        $endMonth = $semester === 1 ? 6 : 12;

        $start = now()->create($year, $startMonth, 1)->startOfDay();
        $end = now()->create($year, $endMonth, 1)->endOfMonth()->endOfDay();

        $monthNumbers = range($startMonth, $endMonth);

        $monthlyRequests = $this->monthlyCounts(
            InspectionRequest::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw($this->monthExpression('created_at').' as month')
                ->selectRaw('COUNT(*) as total'),
            'created_at'
        );

        $monthlyInspections = $this->monthlyCounts(
            Inspection::query()
                ->whereBetween('inspection_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'completed')
                ->selectRaw($this->monthExpression('inspection_date').' as month')
                ->selectRaw('COUNT(*) as total'),
            'inspection_date'
        );

        $monthlyClearances = $this->monthlyCounts(
            Clearance::query()
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw($this->monthExpression('issue_date').' as month')
                ->selectRaw('COUNT(*) as total'),
            'issue_date'
        );

        $monthlyViolations = $this->monthlyCounts(
            Violation::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw($this->monthExpression('created_at').' as month')
                ->selectRaw('COUNT(*) as total'),
            'created_at'
        );

        $months = collect($monthNumbers)->map(fn ($month) => [
            'month' => $month,
            'requests' => $monthlyRequests->get($month, 0),
            'inspections' => $monthlyInspections->get($month, 0),
            'clearances' => $monthlyClearances->get($month, 0),
            'violations' => $monthlyViolations->get($month, 0),
        ]);

        $requestsByStatus = InspectionRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requestsByCategory = InspectionRequest::query()
            ->whereBetween('inspection_requests.created_at', [$start, $end])
            ->join('inspection_categories', 'inspection_categories.id', '=', 'inspection_requests.inspection_category_id')
            ->selectRaw('inspection_categories.name as category, COUNT(*) as total')
            ->groupBy('inspection_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total]);

        $violationsBySeverity = Violation::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $violationsByStatus = Violation::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $compliance = InspectionResult::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('compliance_status, COUNT(*) as total')
            ->groupBy('compliance_status')
            ->pluck('total', 'compliance_status');

        $complianceTotal = $compliance->sum();
        $compliant = (int) $compliance->get('compliant', 0);

        $clearancesByStatus = Clearance::query()
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $expiring = Clearance::query()
            ->where('status', 'active')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $expired = Clearance::query()
            ->where('status', 'active')
            ->where('expiration_date', '<', now())
            ->count();

        return $this->success([
            'year' => $year,
            'semester' => $semester,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => ($semester === 1 ? '1st' : '2nd').' Semester '.$year,
            ],
            'months' => $months,
            'requests' => [
                'total' => $months->sum('requests'),
                'by_status' => $requestsByStatus,
                'by_category' => $requestsByCategory,
            ],
            'inspections' => [
                'completed' => $months->sum('inspections'),
            ],
            'violations' => [
                'total' => $months->sum('violations'),
                'by_severity' => [
                    'minor' => (int) $violationsBySeverity->get('minor', 0),
                    'moderate' => (int) $violationsBySeverity->get('moderate', 0),
                    'major' => (int) $violationsBySeverity->get('major', 0),
                ],
                'by_status' => $violationsByStatus,
            ],
            'compliance' => [
                'total_checks' => $complianceTotal,
                'compliant' => $compliant,
                'non_compliant' => (int) $compliance->get('non_compliant', 0),
                'needs_correction' => (int) $compliance->get('needs_correction', 0),
                'compliance_rate' => $complianceTotal > 0 ? round(($compliant / $complianceTotal) * 100, 1) : 0,
            ],
            'clearances' => [
                'issued' => $months->sum('clearances'),
                'by_status' => $clearancesByStatus,
                'expiring_soon' => $expiring,
                'expired' => $expired,
            ],
        ], 'State of the Barangay Address (SOBA) report retrieved successfully');
    }

    // ---- Export (deploy-ready: CSV / Excel / PDF) ----

    public function export(Request $request, string $type): StreamedResponse
    {
        $type = strtolower($type);
        abort_unless(in_array($type, ['inspections', 'violations', 'clearances', 'dashboard', 'soba'], true), 404, 'Unknown report type.');

        $format = strtolower($request->input('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'excel', 'pdf'], true), 422, 'Format must be csv, excel or pdf.');

        return match ($type) {
            'inspections' => $this->exportInspections($request, $format),
            'violations' => $this->exportViolations($request, $format),
            'clearances' => $this->exportClearances($request, $format),
            'dashboard' => $this->exportDashboard($request, $format),
            'soba' => $this->exportSoba($request, $format),
        };
    }

    private function exportInspections(Request $request, string $format): StreamedResponse
    {
        $year = $request->integer('year', now()->year);
        $data = $this->inspectionData($year);
        $monthly = $data['monthly'];
        $filename = "inspections-{$year}";

        $headers = ['Month', 'Total Inspections', 'Completed'];
        $rows = $monthly->map(fn ($r) => [$this->monthName($r['month']), $r['total'], $r['completed']])->all();
        $rows[] = ['TOTAL', $data['total_inspections'], $data['total_completed']];

        return $this->streamExport($filename, $format, $headers, $rows, 'Inspection Report', "Year {$year}");
    }

    private function exportViolations(Request $request, string $format): StreamedResponse
    {
        $year = $request->integer('year', now()->year);
        $data = $this->violationData($year);
        $filename = "violations-{$year}";

        $headers = ['Category', 'Type', 'Count'];
        $rows = [
            ['Minor', 'Severity', $data['by_severity']['minor']],
            ['Moderate', 'Severity', $data['by_severity']['moderate']],
            ['Major', 'Severity', $data['by_severity']['major']],
            ['Open', 'Status', $data['by_status']['open']],
            ['Resolved', 'Status', $data['by_status']['resolved']],
            ['TOTAL', '', $data['total']],
        ];

        return $this->streamExport($filename, $format, $headers, $rows, 'Violation Report', "Year {$year}");
    }

    private function exportClearances(Request $request, string $format): StreamedResponse
    {
        $year = $request->integer('year', now()->year);
        $data = $this->clearanceData($year);
        $filename = "clearances-{$year}";

        $headers = ['Month', 'Issued'];
        $rows = $data['monthly']->map(fn ($r) => [$this->monthName($r['month']), $r['issued']])->all();
        $rows[] = [];
        $rows[] = ['Total Issued', $data['total_issued']];
        $rows[] = ['Expiring Soon (30d)', $data['expiring_soon']];
        $rows[] = ['Expired', $data['expired']];

        return $this->streamExport($filename, $format, $headers, $rows, 'Clearance Report', "Year {$year}");
    }

    private function exportDashboard(Request $request, string $format): StreamedResponse
    {
        $data = $this->dashboardData();
        $filename = 'dashboard-'.now()->format('Y-m-d');

        $headers = ['Metric', 'Value'];
        $rows = [
            ['Pending Requests', $data['pending_requests']],
            ['Active Violations', $data['active_violations']],
            ['Completed Inspections (YTD)', $data['completed_inspections_ytd']],
            ['Expiring Clearances (30d)', $data['expiring_clearances']],
        ];

        return $this->streamExport($filename, $format, $headers, $rows, 'Dashboard Overview', 'As of '.now()->format('Y-m-d H:i'));
    }

    private function exportSoba(Request $request, string $format): StreamedResponse
    {
        $year = $request->integer('year', now()->year);
        $semester = $request->integer('semester', now()->month >= 7 ? 2 : 1);
        abort_unless(in_array($semester, [1, 2], true), 422, 'Semester must be 1 or 2.');

        $data = $this->sobaData($year, $semester);
        $filename = "soba-{$year}-S{$semester}";

        // Flatten SOBA into one sheet with sections
        $headers = ['Section', 'Key', 'Value'];
        $rows = [];
        $rows[] = ['Period', $data['period']['label'], $data['period']['start'].' to '.$data['period']['end']];
        $rows[] = [];
        $rows[] = ['MONTHLY', 'Month', 'Requests / Inspections / Clearances / Violations'];
        foreach ($data['months'] as $m) {
            $rows[] = ['Monthly', $this->monthName($m['month']), "{$m['requests']} / {$m['inspections']} / {$m['clearances']} / {$m['violations']}"];
        }
        $rows[] = [];
        $rows[] = ['Requests', 'Total', $data['requests']['total']];
        foreach ($data['requests']['by_category'] as $cat) {
            $rows[] = ['Requests by Category', $cat['category'], $cat['total']];
        }
        foreach ($data['requests']['by_status'] as $status => $count) {
            $rows[] = ['Requests by Status', $status, $count];
        }
        $rows[] = ['Inspections', 'Completed', $data['inspections']['completed']];
        $rows[] = ['Violations', 'Total', $data['violations']['total']];
        foreach ($data['violations']['by_severity'] as $sev => $count) {
            $rows[] = ['Violations by Severity', $sev, $count];
        }
        foreach ($data['violations']['by_status'] as $status => $count) {
            $rows[] = ['Violations by Status', $status, $count];
        }
        $rows[] = ['Compliance', 'Total Checks', $data['compliance']['total_checks']];
        $rows[] = ['Compliance', 'Compliant', $data['compliance']['compliant']];
        $rows[] = ['Compliance', 'Non-Compliant', $data['compliance']['non_compliant']];
        $rows[] = ['Compliance', 'Needs Correction', $data['compliance']['needs_correction']];
        $rows[] = ['Compliance', 'Compliance Rate %', $data['compliance']['compliance_rate']];
        $rows[] = ['Clearances', 'Issued', $data['clearances']['issued']];
        foreach ($data['clearances']['by_status'] as $status => $count) {
            $rows[] = ['Clearances by Status', $status, $count];
        }
        $rows[] = ['Clearances', 'Expiring Soon (30d)', $data['clearances']['expiring_soon']];
        $rows[] = ['Clearances', 'Expired', $data['clearances']['expired']];

        return $this->streamExport($filename, $format, $headers, $rows, 'State of the Barangay Address (SOBA) Report', $data['period']['label'], $data);
    }

    private function streamExport(string $filename, string $format, array $headers, array $rows, string $title, string $subtitle, ?array $sobaData = null): StreamedResponse
    {
        return match ($format) {
            'excel' => $this->streamExcel($filename, $headers, $rows, $title, $subtitle),
            'pdf' => $this->streamPdf($filename, $headers, $rows, $title, $subtitle, $sobaData),
            default => $this->streamCsv($filename, $headers, $rows, $title, $subtitle),
        };
    }

    private function streamCsv(string $filename, array $headers, array $rows, string $title, string $subtitle): StreamedResponse
    {
        $filename .= '.csv';

        return Response::streamDownload(function () use ($headers, $rows, $title, $subtitle) {
            $handle = fopen('php://output', 'w');
            // Title rows for context when opened in Excel/Sheets
            fputcsv($handle, [$title]);
            fputcsv($handle, [$subtitle]);
            fputcsv($handle, ['Generated at', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function streamExcel(string $filename, array $headers, array $rows, string $title, string $subtitle): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->setCellValue('A3', 'Generated at: '.now()->format('Y-m-d H:i:s'));
        $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('6B7280');

        $sheet->fromArray($headers, null, 'A5');
        $sheet->getStyle('A5:'.chr(64 + count($headers)).'5')->getFont()->setBold(true);
        $sheet->getStyle('A5:'.chr(64 + count($headers)).'5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $sheet->fromArray($rows, null, 'A6');

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimension(chr(64 + $col))->setAutoSize(true);
        }

        $filename .= '.xlsx';

        return Response::streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function streamPdf(string $filename, array $headers, array $rows, string $title, string $subtitle, ?array $sobaData): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.report', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->format('F j, Y g:i A'),
            'soba' => $sobaData,
        ])->setPaper('a4', $sobaData ? 'landscape' : 'portrait');

        $filename .= '.pdf';

        return Response::streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    private function inspectionData(int $year): array
    {
        $monthly = Inspection::query()
            ->selectRaw($this->monthExpression('inspection_date').' as month')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->whereYear('inspection_date', $year)
            ->groupByRaw($this->monthExpression('inspection_date'))
            ->orderBy('month')->get()->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'total' => (int) ($monthly->get($m)?->total ?? 0),
            'completed' => (int) ($monthly->get($m)?->completed ?? 0),
        ]);

        return [
            'year' => $year,
            'monthly' => $months,
            'total_inspections' => $months->sum('total'),
            'total_completed' => $months->sum('completed'),
        ];
    }

    private function violationData(int $year): array
    {
        $bySeverity = Violation::query()->selectRaw('severity, COUNT(*) as total')->whereYear('created_at', $year)->groupBy('severity')->pluck('total', 'severity');
        $byStatus = Violation::query()->selectRaw('status, COUNT(*) as total')->whereYear('created_at', $year)->groupBy('status')->pluck('total', 'status');

        return [
            'year' => $year,
            'by_severity' => [
                'minor' => (int) ($bySeverity->get('minor', 0)),
                'moderate' => (int) ($bySeverity->get('moderate', 0)),
                'major' => (int) ($bySeverity->get('major', 0)),
            ],
            'by_status' => [
                'open' => (int) ($byStatus->get('open', 0)),
                'resolved' => (int) ($byStatus->get('resolved', 0)),
            ],
            'total' => $bySeverity->sum(),
        ];
    }

    private function clearanceData(int $year): array
    {
        $monthly = Clearance::query()
            ->selectRaw($this->monthExpression('issue_date').' as month')
            ->selectRaw('COUNT(*) as issued')
            ->whereYear('issue_date', $year)->groupByRaw($this->monthExpression('issue_date'))->orderBy('month')->get()->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m,
            'issued' => (int) ($monthly->get($m)?->issued ?? 0),
        ]);

        return [
            'year' => $year,
            'monthly' => $months,
            'total_issued' => $months->sum('issued'),
            'expiring_soon' => Clearance::query()->where('status', 'active')->whereBetween('expiration_date', [now(), now()->addDays(30)])->count(),
            'expired' => Clearance::query()->where('status', 'active')->where('expiration_date', '<', now())->count(),
        ];
    }

    private function sobaData(int $year, int $semester): array
    {
        $startMonth = $semester === 1 ? 1 : 7;
        $endMonth = $semester === 1 ? 6 : 12;
        $start = now()->create($year, $startMonth, 1)->startOfDay();
        $end = now()->create($year, $endMonth, 1)->endOfMonth()->endOfDay();
        $monthNumbers = range($startMonth, $endMonth);

        $monthlyRequests = $this->monthlyCounts(InspectionRequest::query()->whereBetween('created_at', [$start, $end])->selectRaw($this->monthExpression('created_at').' as month')->selectRaw('COUNT(*) as total'), 'created_at');
        $monthlyInspections = $this->monthlyCounts(Inspection::query()->whereBetween('inspection_date', [$start->toDateString(), $end->toDateString()])->where('status', 'completed')->selectRaw($this->monthExpression('inspection_date').' as month')->selectRaw('COUNT(*) as total'), 'inspection_date');
        $monthlyClearances = $this->monthlyCounts(Clearance::query()->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])->selectRaw($this->monthExpression('issue_date').' as month')->selectRaw('COUNT(*) as total'), 'issue_date');
        $monthlyViolations = $this->monthlyCounts(Violation::query()->whereBetween('created_at', [$start, $end])->selectRaw($this->monthExpression('created_at').' as month')->selectRaw('COUNT(*) as total'), 'created_at');

        $months = collect($monthNumbers)->map(fn ($month) => [
            'month' => $month,
            'requests' => $monthlyRequests->get($month, 0),
            'inspections' => $monthlyInspections->get($month, 0),
            'clearances' => $monthlyClearances->get($month, 0),
            'violations' => $monthlyViolations->get($month, 0),
        ]);

        $requestsByStatus = InspectionRequest::query()->whereBetween('created_at', [$start, $end])->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $requestsByCategory = InspectionRequest::query()->whereBetween('inspection_requests.created_at', [$start, $end])->join('inspection_categories', 'inspection_categories.id', '=', 'inspection_requests.inspection_category_id')->selectRaw('inspection_categories.name as category, COUNT(*) as total')->groupBy('inspection_categories.name')->orderByDesc('total')->get()->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total]);
        $violationsBySeverity = Violation::query()->whereBetween('created_at', [$start, $end])->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity');
        $violationsByStatus = Violation::query()->whereBetween('created_at', [$start, $end])->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $compliance = InspectionResult::query()->whereBetween('created_at', [$start, $end])->selectRaw('compliance_status, COUNT(*) as total')->groupBy('compliance_status')->pluck('total', 'compliance_status');
        $complianceTotal = $compliance->sum();
        $compliant = (int) $compliance->get('compliant', 0);
        $clearancesByStatus = Clearance::query()->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'year' => $year,
            'semester' => $semester,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => ($semester === 1 ? '1st' : '2nd').' Semester '.$year],
            'months' => $months,
            'requests' => ['total' => $months->sum('requests'), 'by_status' => $requestsByStatus, 'by_category' => $requestsByCategory],
            'inspections' => ['completed' => $months->sum('inspections')],
            'violations' => ['total' => $months->sum('violations'), 'by_severity' => ['minor' => (int) $violationsBySeverity->get('minor', 0), 'moderate' => (int) $violationsBySeverity->get('moderate', 0), 'major' => (int) $violationsBySeverity->get('major', 0)], 'by_status' => $violationsByStatus],
            'compliance' => ['total_checks' => $complianceTotal, 'compliant' => $compliant, 'non_compliant' => (int) $compliance->get('non_compliant', 0), 'needs_correction' => (int) $compliance->get('needs_correction', 0), 'compliance_rate' => $complianceTotal > 0 ? round(($compliant / $complianceTotal) * 100, 1) : 0],
            'clearances' => ['issued' => $months->sum('clearances'), 'by_status' => $clearancesByStatus, 'expiring_soon' => Clearance::query()->where('status', 'active')->whereBetween('expiration_date', [now(), now()->addDays(30)])->count(), 'expired' => Clearance::query()->where('status', 'active')->where('expiration_date', '<', now())->count()],
        ];
    }

    private function monthName(int $month): string
    {
        return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][$month - 1] ?? (string) $month;
    }

    private function monthlyCounts($baseQuery, string $dateColumn): \Illuminate\Support\Collection
    {
        $rows = $baseQuery
            ->groupByRaw($this->monthExpression($dateColumn))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return $rows->map(fn ($row) => (int) $row->total);
    }

    private function monthExpression(string $column): string
{
    return match (DB::connection()->getDriverName()) {
        'pgsql' => "EXTRACT(MONTH FROM {$column})",
        'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
        default => "MONTH({$column})", // mysql, mariadb
    };
}
}
