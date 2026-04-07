<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
        h2    { font-size: 14px; margin-bottom: 4px; }
        .meta { font-size: 9px; color: #666; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th    { background-color: #2c3e50; color: #fff; padding: 4px 6px; text-align: left; }
        td    { padding: 3px 6px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) td { background-color: #f9f9f9; }
        .summary { margin-bottom: 12px; padding: 8px; background: #f0f4f8; border-left: 3px solid #2c3e50; }
        .summary-label { font-weight: bold; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <div class="meta">Generated: {{ now()->format('d M Y, H:i') }}</div>

    @if(!empty($summary))
    <div class="summary">
        @foreach($summary as $key => $value)
            @if(!is_array($value))
            <span class="summary-label">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
            {{ $value }}&nbsp;&nbsp;&nbsp;
            @endif
        @endforeach
    </div>
    @endif

    @if(!empty($rows))
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                @foreach(array_values($row) as $cell)
                <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p>No data available for the selected filters.</p>
    @endif
</body>
</html>
