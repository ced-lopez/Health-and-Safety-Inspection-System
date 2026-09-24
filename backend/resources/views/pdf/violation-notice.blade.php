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
        <div class="docno">Correction deadline: <strong>{{ $deadline }}</strong></div>

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
                <td class="label">Inspection Date</td>
                <td>{{ $inspection?->inspection_date?->format('F j, Y') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Violation</td>
                <td>{{ $violation->title }}</td>
            </tr>
            <tr>
                <td class="label">Severity</td>
                <td>{{ ucfirst($violation->severity) }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td>{{ ucwords(str_replace('_', ' ', $violation->status)) }}</td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td>{{ $violation->description }}</td>
            </tr>
        </table>

        <p class="note">
            You are hereby notified to correct the cited violation within seven (7) days from this notice
            or not later than the stated deadline. Failure to comply may result in withholding or revocation
            of barangay health and safety clearance.
        </p>

        @include('pdf.partials.signatories')

        <div class="footer">
            This is a system-generated violation notice. Generated on {{ $generated_at }}.
        </div>
    </div>
</body>
</html>
