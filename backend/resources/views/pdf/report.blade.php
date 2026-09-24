<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; margin: 0; padding: 16px; }
        .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #1f2937; padding-bottom: 10px; }
        .header .republic { font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase; color: #374151; }
        .header .brgy { font-size: 16px; font-weight: 700; margin: 2px 0; }
        .header .city { font-size: 11px; color: #374151; }
        .header .office { font-size: 9px; letter-spacing: 0.12em; text-transform: uppercase; color: #6b7280; margin-top: 4px; }
        h1 { font-size: 15px; margin: 12px 0 2px; text-align: center; }
        .subtitle { text-align: center; color: #6b7280; font-size: 10px; margin-bottom: 6px; }
        .meta { text-align: center; color: #9ca3af; font-size: 8px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:nth-child(even) td { background: #fafafa; }
        .footer { text-align: center; font-size: 7px; color: #9ca3af; margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="republic">Republic of the Philippines — City of Caloocan</div>
        <div class="brgy">Barangay 178</div>
        <div class="city">Caloocan City, Metro Manila</div>
        <div class="office">Health &amp; Safety Inspection System — Barangay 178 Only</div>
    </div>

    <h1>{{ $title }}</h1>
    <div class="subtitle">{{ $subtitle }}</div>
    <div class="meta">Generated {{ $generated_at }} &middot; Barangay 178 records only</div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @if (empty(array_filter($row, fn($v) => $v !== null && $v !== '')))
                    <tr><td colspan="{{ count($headers) }}" style="border: none; height: 8px; background: #fff;"></td></tr>
                @else
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                        @for ($i = count($row); $i < count($headers); $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center; color:#6b7280;">No data available</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($soba)
        <div style="font-size: 7px; color: #6b7280; margin-top: 8px;">
            SOBA Compliance Rate: {{ $soba['compliance']['compliance_rate'] }}% ({{ $soba['compliance']['compliant'] }} / {{ $soba['compliance']['total_checks'] }} checks)
            &middot; Period {{ $soba['period']['start'] }} to {{ $soba['period']['end'] }}
        </div>
    @endif

    <div class="footer">
        HSIS — Barangay 178 Health &amp; Safety Inspection System &middot; This report is system-generated and scope-limited to Barangay 178.
    </div>
</body>
</html>
