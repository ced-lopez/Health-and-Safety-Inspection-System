<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
            color: #111;
            line-height: 1.5;
        }
        .page {
            width: 100%;
            padding: 30px 40px;
            position: relative;
            min-height: 700px;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #111;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .header .republic { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .header .brgy { font-size: 16px; font-weight: bold; margin-top: 2px; }
        .header .city { font-size: 13px; margin-top: 2px; }
        .header .office { font-size: 12px; font-style: italic; margin-top: 2px; }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            text-decoration: underline;
            margin: 16px 0 4px;
        }
        .docno { text-align: center; font-size: 12px; margin-bottom: 16px; }
        table.fields {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }
        table.fields td {
            padding: 6px 8px;
            border: 1px solid #444;
            vertical-align: top;
        }
        table.fields td.label {
            width: 30%;
            background: #f2f2f2;
            font-weight: bold;
        }
        .note {
            text-align: justify;
            margin: 14px 0;
            font-size: 12.5px;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
        }
        .qr-section img { width: 130px; height: 130px; }
        .qr-section .code { font-family: 'Courier New', monospace; font-size: 11px; margin-top: 4px; }
        .verify-url { font-size: 11px; color: #333; }
        .signatory {
            margin-top: 50px;
            text-align: center;
        }
        .signatory .line {
            display: inline-block;
            width: 260px;
            border-bottom: 1px solid #111;
            margin-top: 42px;
        }
        .signatory .name { font-weight: bold; margin-top: 4px; }
        .signatory .role { font-size: 12px; }
        .footer {
            position: absolute;
            bottom: 24px;
            left: 40px;
            right: 40px;
            border-top: 1px solid #999;
            padding-top: 6px;
            font-size: 9.5px;
            color: #555;
            text-align: center;
        }
        .status-box {
            display: inline-block;
            border: 1px solid #111;
            padding: 2px 12px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="republic">Republic of the Philippines</div>
            <div class="brgy">Barangay 178</div>
            <div class="city">North Caloocan City</div>
            <div class="office">Office of the Barangay Chairman</div>
        </div>

        <div class="title">{{ $title }}</div>
        <div class="docno">Clearance No. / Certificate No.: <strong>{{ $number }}</strong></div>

        <div style="text-align: center; margin-bottom: 10px;">
            Status: <span class="status-box">{{ ucfirst($status) }}</span>
        </div>

        <table class="fields">
            <tr>
                <td class="label">Establishment / Facility</td>
                <td>{{ $establishment ? $establishment->name : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Owner / Applicant</td>
                <td>{{ $establishment ? $establishment->owner_name : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Business Address</td>
                <td>{{ $establishment && $establishment->address ? $establishment->address : 'N/A' }}</td>
            </tr>
            @if ($type)
            <tr>
                <td class="label">Document Type</td>
                <td>{{ $type }}</td>
            </tr>
            @endif
            @if ($purpose)
            <tr>
                <td class="label">Purpose</td>
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
                <td class="label">Issued By</td>
                <td>{{ $issuer ?? 'N/A' }}</td>
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
            <div class="code">{{ $code }}</div>
            <div class="verify-url">Verify this document at: {{ $verification_url }}</div>
        </div>
        @endif

        <div class="signatory">
            <span class="line"></span>
            <div class="name">[[Authorized Signatory]]</div>
            <div class="role">Punong Barangay</div>
        </div>

        <div class="footer">
            This is a system-generated document. Please verify authenticity via the QR code above.
            Generated on {{ $generated_at }}.
        </div>
    </div>
</body>
</html>
