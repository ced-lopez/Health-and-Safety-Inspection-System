<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $request->request_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    <div class="page">
        @include('pdf.partials.header')

        <div class="title">{{ $title }}</div>
        <div class="docno">OR / Reference No.: <strong>{{ $payment->reference_number }}</strong></div>

        <table class="fields">
            <tr>
                <td class="label">Request Number</td>
                <td>{{ $request->request_number }}</td>
            </tr>
            <tr>
                <td class="label">Applicant</td>
                <td>{{ $request->applicant_name }}</td>
            </tr>
            <tr>
                <td class="label">Establishment / Business</td>
                <td>{{ $request->business_name ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Category</td>
                <td>{{ $request->inspectionCategory?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Fee Type</td>
                <td>{{ $type_label }}</td>
            </tr>
            <tr>
                <td class="label">Amount Paid</td>
                <td>PHP {{ $amount }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method</td>
                <td>{{ $payment->method === 'manual' ? 'Over-the-counter (Manual)' : ucfirst($payment->method) }}</td>
            </tr>
            <tr>
                <td class="label">Date / Time Paid</td>
                <td>{{ $paid_at ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Confirmed By</td>
                <td>{{ $payment->confirmedBy?->name ?? 'N/A' }}</td>
            </tr>
        </table>

        <p class="note">
            This receipt confirms that the stated fee was received over the counter by Barangay 178.
            Please keep this copy for your records. Online payment is not yet available.
        </p>

        @include('pdf.partials.signatories')

        <div class="footer">
            This is a system-generated receipt. Generated on {{ $generated_at }}.
        </div>
    </div>
</body>
</html>
