<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { margin: 0 0 4px; font-size: 17px; }
        .meta { margin-bottom: 14px; color: #596579; }
        .filters { margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #e8edf3; color: #253044; font-size: 7px; text-align: left; }
        th, td { border: 1px solid #cbd3dd; padding: 4px; overflow-wrap: break-word; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f7f9fb; }
        .empty { padding: 24px; text-align: center; }
        .footer { margin-top: 8px; color: #667085; font-size: 7px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generated {{ $generatedAt }}
        @if ($filterSummary !== '')
            <div class="filters">Filters: {{ $filterSummary }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($headers) }}">No records matched the selected scope.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">PointERP operational report. Figures are restricted to the viewer's authorised tenant and branch scope.</div>
</body>
</html>
