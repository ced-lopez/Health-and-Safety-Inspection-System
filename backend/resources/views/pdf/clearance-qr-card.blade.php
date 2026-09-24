<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QR Card - {{ $clearance_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, sans-serif; }
        .card { width: 100%; height: 100%; padding: {{ $format === 'wallet' ? '9px' : '28px' }}; text-align: center; border: 2px solid #153e75; }
        .logo { width: {{ $format === 'wallet' ? '23px' : '55px' }}; height: {{ $format === 'wallet' ? '23px' : '55px' }}; vertical-align: middle; object-fit: contain; }
        .brand { display: inline-block; margin-left: 4px; vertical-align: middle; color: #153e75; font-size: {{ $format === 'wallet' ? '6px' : '13px' }}; font-weight: bold; }
        .title { margin: {{ $format === 'wallet' ? '6px' : '12px' }} 0 4px; color: #153e75; font-size: {{ $format === 'wallet' ? '8px' : '17px' }}; font-weight: bold; }
        .qr { width: {{ $format === 'wallet' ? '88px' : '245px' }}; height: {{ $format === 'wallet' ? '88px' : '245px' }}; }
        .number { margin-top: 3px; font-size: {{ $format === 'wallet' ? '7px' : '14px' }}; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        @if ($logo_data_uri ?? null)<img class="logo" src="{{ $logo_data_uri }}" alt="Barangay 178 logo">@endif
        <span class="brand">BARANGAY 178</span>
        <div class="title">HEALTH &amp; SAFETY CLEARANCE &mdash; SCAN TO VERIFY</div>
        @if ($qr_data_uri)<img class="qr" src="{{ $qr_data_uri }}" alt="QR Code">@endif
        <div class="number">{{ $clearance_number }}</div>
    </div>
</body>
</html>
