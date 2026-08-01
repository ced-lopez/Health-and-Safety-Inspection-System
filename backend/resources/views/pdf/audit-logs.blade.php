<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Log Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:nth-child(even) td { background: #fafafa; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Audit Log Report</h1>
    <div class="subtitle">Generated {{ $generated_at }} &middot; {{ count($logs) }} record(s)</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Timestamp</th>
                <th>Event</th>
                <th>Module</th>
                <th>Action</th>
                <th>Description</th>
                <th>User</th>
                <th>Role</th>
                <th>Auditable</th>
                <th>Old Values</th>
                <th>New Values</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->event }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->user?->name }}</td>
                    <td>{{ $log->user?->role?->name }}</td>
                    <td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                    <td class="muted">{{ $log->old_values ? json_encode($log->old_values) : '—' }}</td>
                    <td class="muted">{{ $log->new_values ? json_encode($log->new_values) : '—' }}</td>
                    <td>{{ $log->ip_address }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
