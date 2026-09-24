<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    <div class="page">
        @include('pdf.partials.header')

        <div class="title">{{ $title }}</div>
        <div class="docno">Inspection Date: <strong>{{ $inspection->inspection_date?->format('F j, Y') ?? 'N/A' }}</strong></div>

        <table class="fields">
            <tr>
                <td class="label">Establishment / Facility</td>
                <td>{{ $establishment?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Owner / Applicant</td>
                <td>{{ $establishment?->owner_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Address</td>
                <td>{{ $establishment?->address ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Inspector</td>
                <td>{{ $inspector?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Overall Assessment</td>
                <td>{{ $inspection->overall_assessment ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Recommendations</td>
                <td>{{ $inspection->recommendations ?: 'N/A' }}</td>
            </tr>
        </table>

        <p class="note">
            Summary: {{ $summary['total'] }} items inspected —
            {{ $summary['compliant'] }} compliant,
            {{ $summary['needs_correction'] }} needs correction,
            {{ $summary['non_compliant'] }} non-compliant.
        </p>

        <table class="items">
            <thead>
                <tr>
                    <th>Requirement</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($results as $result)
                    <tr>
                        <td>{{ $result->checklistItem?->title ?? 'Item' }}</td>
                        <td>{{ $result->checklistItem?->category ?? 'N/A' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $result->compliance_status)) }}</td>
                        <td>{{ $result->remarks ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No checklist results recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($violations->count())
            <p class="note"><strong>Violations</strong></p>
            <table class="items">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Severity</th>
                        <th>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($violations as $violation)
                        <tr>
                            <td>{{ $violation->title }}</td>
                            <td>{{ ucfirst($violation->severity) }}</td>
                            <td>{{ $violation->correction_deadline?->format('F j, Y') ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @include('pdf.partials.signatories')

        <div class="footer">
            This is a system-generated inspection report. Generated on {{ $generated_at }}.
        </div>
    </div>
</body>
</html>
