<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    <div class="page">
        @include('pdf.partials.header')

        @if ($logo_data_uri)
            <img class="document-logo" src="{{ $logo_data_uri }}" alt="Barangay 178 logo">
        @endif
        <div class="title">{{ $title }}</div>
        <div class="docno">Clearance Number: <strong>{{ $number }}</strong></div>

        <table class="fields">
            <tr>
                <td class="label">Applicant Name</td>
                <td>{{ $establishment ? $establishment->owner_name : 'N/A' }}</td>
            </tr>
            @if ($establishment?->name)
                <tr>
                    <td class="label">Business / Establishment Name</td>
                    <td>{{ $establishment->name }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Business Address</td>
                <td>{{ $establishment && $establishment->address ? $establishment->address : 'N/A' }}</td>
            </tr>
            @if ($type)
            <tr>
                <td class="label">Inspection Category</td>
                <td>{{ $type }}</td>
            </tr>
            @endif
            @if ($purpose)
            <tr>
                <td class="label">Category Detail</td>
                <td>{{ $purpose }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Date Issued</td>
                <td>{{ $issue_date ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Expiration Date</td>
                <td>{{ $expiration_date }}</td>
            </tr>
            @if ($inspection)
            <tr>
                <td class="label">Inspection Date</td>
                <td>{{ $inspection->inspection_date ? $inspection->inspection_date->format('F j, Y') : 'N/A' }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Issuing Authority</td>
                <td>{{ $barangay_name }}, {{ $barangay_city }}</td>
            </tr>
        </table>

        <p class="note">
            This certifies that the above-named establishment has undergone the required health and safety
            inspection and has been found compliant with the barangay's health, sanitation, and safety standards.
            This document is valid for one (1) year from the date of issue unless otherwise revoked by the
            authorized barangay office.
        </p>

        @if ($qr_data_uri)
        <div class="qr-section">
            <img src="{{ $qr_data_uri }}" alt="QR Code">
            <div class="qr-caption">Scan QR Code to Verify Authenticity of this Clearance</div>
        </div>
        @endif

        @include('pdf.partials.signatories')

        <div class="footer">
            Issued through the Barangay 178 Health &amp; Safety Inspection System
        </div>
    </div>
</body>
</html>
