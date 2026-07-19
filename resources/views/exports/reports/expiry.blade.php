<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            padding: 20px 24px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-reg { font-size: 8px; color: #555; margin-top: 1px; }
        .company-address { font-size: 8px; color: #333; margin-top: 4px; line-height: 1.5; }

        /* ── Info Box ── */
        .info-box {
            border: 1.5px solid #111;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 14px;
        }
        .info-box-inner {
            width: 100%;
        }

        .doc-type-badge {
            border: 1.5px solid #111;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 10px;
        }

        /* Summary Fields */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            vertical-align: top;
            padding: 4px;
        }
        .field-label { font-weight: bold; font-size: 9px; white-space: nowrap; color: #444; }
        .field-value {
            font-size: 10px;
            font-weight: bold;
            border-bottom: 1px dashed #999;
            padding-bottom: 1px;
            padding-left: 5px;
        }

        /* Grouped Box Styles */
        .group-box {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
        }
        .group-title {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }
        .group-item {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            margin-bottom: 4px;
        }
        .group-item-name { color: #333; }
        .group-item-val { font-weight: bold; }

        /* ── Items Table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 8.5px;
        }
        table.items th {
            background-color: #e8e8e8;
            border: 1px solid #111;
            padding: 5px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 8px;
        }
        table.items td {
            border: 1px solid #555;
            padding: 4px;
            vertical-align: top;
        }
        table.items td.center { text-align: center; }
        table.items tbody tr:nth-child(even) td { background-color: #fafafa; }
        
        .empty-row td {
            text-align: center;
            color: #888;
            padding: 16px;
            font-style: italic;
        }

    </style>
</head>
<body>

    {{-- ═══ HEADER ═══ --}}
    <div class="header" style="width: 100%; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 14px;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 90px; vertical-align: middle; border: none; padding: 0;">
                    @php
                        $logoPath = public_path('tremed_logo.png');
                        $logoData = '';
                        if (file_exists($logoPath)) {
                            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                            $data = file_get_contents($logoPath);
                            $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        }
                    @endphp
                    @if($logoData)
                        <img src="{{ $logoData }}" alt="Logo" style="width: 130px; height: auto;">
                    @endif
                </td>
                <td style="vertical-align: top; border: none; padding: 10px;">
                    <div class="company-name">Tremed Surgical Solution Sdn. Bhd.</div>
                    <div class="company-reg">(202301013019 / 1506941-M)</div>
                    <div class="company-address">
                        No 6-1, Block A, Zenith Corporate Park, Jalan SS 7/26, Kelana Jaya, 47301 Petaling Jaya, Selangor.<br>
                        Tel : +603-7886 1704 / +6012-633 8787 &nbsp;&nbsp; Email : tremedsurgical@gmail.com
                    </div>
                </td>
                <td style="vertical-align: middle; border: none; padding: 0; text-align: right;">
                    <div class="doc-type-badge" style="margin-bottom: 0;">{{ $title ?? 'Expiry Report' }}</div>
                    <div style="font-size: 8px; color: #555; margin-top: 5px;">Generated: {{ now()->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══ SUMMARY BOX ═══ --}}
    @if(!empty($summary))
    <div class="info-box">
        <table class="summary-table">
            <tr>
                {{-- Left Column: Scalar values --}}
                <td style="width: 30%; border-right: 1px solid #ccc; padding-right: 15px;">
                    <div class="group-title">Overview</div>
                    <table style="width:100%; border:none;">
                        @foreach($summary as $key => $val)
                            @if(is_scalar($val))
                            <tr>
                                <td class="field-label" style="width: 110px;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                <td style="width: 10px;">:</td>
                                <td class="field-value">{{ $val }}</td>
                            </tr>
                            @endif
                        @endforeach
                    </table>
                </td>

                {{-- Middle Column: First Group (e.g., By Supplier) --}}
                <td style="width: 35%; border-right: 1px solid #ccc; padding: 0 15px;">
                    @if(!empty($summary['by_supplier']))
                    <div class="group-title">By Supplier</div>
                    <table style="width:100%; border:none;">
                        @forelse($summary['by_supplier'] as $item)
                        <tr>
                            <td class="group-item-name" style="padding-bottom: 4px;">{{ $item['supplier_name'] ?? 'Unknown' }}</td>
                            <td class="group-item-val" style="text-align: right; padding-bottom: 4px;">{{ $item['lot_count'] ?? $item['count'] ?? 0 }} lots</td>
                        </tr>
                        @empty
                        <tr><td style="color:#888; font-style:italic;">No supplier data</td></tr>
                        @endforelse
                    </table>
                    @endif
                </td>

                {{-- Right Column: Second Group (e.g., By Status) --}}
                <td style="width: 35%; padding-left: 15px;">
                    @if(!empty($summary['by_status']))
                    <div class="group-title">By Status</div>
                    <table style="width:100%; border:none;">
                        @forelse($summary['by_status'] as $status => $count)
                        <tr>
                            <td class="group-item-name" style="padding-bottom: 4px; text-transform: uppercase;">{{ $status }}</td>
                            <td class="group-item-val" style="text-align: right; padding-bottom: 4px;">{{ $count }} lots</td>
                        </tr>
                        @empty
                        <tr><td style="color:#888; font-style:italic;">No status data</td></tr>
                        @endforelse
                    </table>
                    @endif
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- ═══ ITEMS TABLE ═══ --}}
    <table class="items">
        <thead>
            <tr>
                @if(!empty($headers))
                    @foreach($headers as $header)
                    <th>{{ $header }}</th>
                    @endforeach
                @else
                    <th>Lot Number</th>
                    <th>Batch Code</th>
                    <th>Product Ref</th>
                    <th>Product Name</th>
                    <th>Supplier</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Days Left</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @if(!empty($rows))
                @foreach($rows as $row)
                <tr>
                    @foreach(array_values($row) as $idx => $cell)
                        <td class="{{ in_array($idx, [6, 7]) ? 'center' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
                @endforeach
            @else
                <tr class="empty-row">
                    <td colspan="{{ count($headers ?? []) ?: 8 }}">No expiring lots match the selected filters.</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
