<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { margin-bottom: 12px; font-size: 10px; color: #555; }
        .meta-row { margin-bottom: 4px; }
        .label { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d5d5d5; padding: 6px; vertical-align: top; }
        th { background: #f3f5f7; text-align: left; font-size: 10px; }
        td { font-size: 10px; }
        .right { text-align: right; }
        .empty { margin-top: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>Stock-In Session Printout</h1>

    <div class="meta">
        <div class="meta-row"><span class="label">Session No:</span> {{ $stockIn->session_no }}</div>
        <div class="meta-row"><span class="label">Supplier:</span> {{ $stockIn->supplier?->supplier_name ?? '-' }}</div>
        <div class="meta-row"><span class="label">DO Number:</span> {{ $stockIn->do_number ?? '-' }}</div>
        <div class="meta-row"><span class="label">Stock-In Date:</span> {{ $stockIn->stock_in_at?->format('d M Y H:i') ?? '-' }}</div>
        <div class="meta-row"><span class="label">PIC:</span> {{ $stockIn->picUser?->full_name ?? '-' }}</div>
        <div class="meta-row"><span class="label">Status:</span> {{ strtoupper((string) $stockIn->status) }}</div>
        <div class="meta-row"><span class="label">Confirmed At:</span> {{ $stockIn->confirmed_at?->format('d M Y H:i') ?? '-' }}</div>
        <div class="meta-row"><span class="label">Confirmed By:</span> {{ $stockIn->confirmedByUser?->full_name ?? '-' }}</div>
        <div class="meta-row"><span class="label">Printed At:</span> {{ now()->format('d M Y H:i') }}</div>
    </div>

    @if($stockIn->stockInItems->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th class="right">#</th>
                <th>Ref Num</th>
                <th>Product Name</th>
                <th>Scanned Lot</th>
                <th>System Lot</th>
                <th>Supplier Batch</th>
                <th>Expiry Date</th>
                <th>Missing Lot</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockIn->stockInItems as $index => $item)
            <tr>
                <td class="right">{{ $index + 1 }}</td>
                <td>{{ $item->product?->ref_num ?? '-' }}</td>
                <td>{{ $item->product?->product_name ?? '-' }}</td>
                <td>{{ $item->scanned_lot_number ?? '-' }}</td>
                <td>{{ $item->lot?->lot_number ?? '-' }}</td>
                <td>{{ $item->manufacturing_date ?? '-' }}</td>
                <td>{{ $item->expiry_date?->format('d M Y') ?? '-' }}</td>
                <td>{{ $item->missing_lot_flag ? 'Yes' : 'No' }}</td>
                <td>{{ $item->remarks ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty">No items in this stock-in session.</p>
    @endif
</body>
</html>
