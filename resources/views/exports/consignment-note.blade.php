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
        .info-col-left { width: 55%; vertical-align: top; padding-right: 16px; }
        .info-col-right { width: 45%; vertical-align: top; padding-left: 12px; border-left: 1.5px solid #111; }

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

        .field-row { margin-bottom: 6px; width: 100%; }
        .field-row td { vertical-align: bottom; }
        .field-label { font-weight: bold; font-size: 9px; width: 90px; padding-right: 6px; white-space: nowrap; }
        .field-colon { width: 6px; }
        .field-value {
            font-size: 9px;
            border-bottom: 1px dashed #555;
            min-width: 160px;
            padding-bottom: 1px;
        }

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

        .th-desc { text-align: left !important; }
        .th-sub { font-size: 7px; font-weight: normal; font-style: italic; }

        .empty-row td {
            text-align: center;
            color: #888;
            padding: 16px;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 20px;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    {{-- ═══ HEADER ═══ --}}
    <div class="header">
        <div>
            <div class="company-name">Tremed Surgical Solution Sdn. Bhd.</div>
            <div class="company-reg">(202301013019 / 1506941-M)</div>
            <div class="company-address">
                No 6-1, Block A, Zenith Corporate Park, Jalan SS 7/26, Kelana Jaya, 47301 Petaling Jaya, Selangor.<br>
                Tel : +603-7886 1704 / +6012-633 8787 &nbsp;&nbsp; Email : tremedsurgical@gmail.com
            </div>
        </div>
    </div>

    {{-- ═══ INFO BOX ═══ --}}
    <div class="info-box">
        <table class="info-box-inner">
            <tr>
                {{-- Left column: Hospital, Surgeon, Set No., Case --}}
                <td class="info-col-left">
                    <table>
                        <tr class="field-row">
                            <td class="field-label">Hospital</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $consignment->client?->client_name ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Surgeon</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">&nbsp;</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Set No.</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">&nbsp;</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Case</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">&nbsp;</td>
                        </tr>
                    </table>
                </td>

                {{-- Right column: Badge + dates + prepared by + ref --}}
                <td class="info-col-right">
                    <div class="doc-type-badge">Consignment / Template</div>
                    <table>
                        <tr class="field-row">
                            <td class="field-label">Date Prepared</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $consignment->consignment_at?->format('d/m/Y') ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Date Case</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">&nbsp;</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Prepared by</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $consignment->picUser?->full_name ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Ref</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $consignment->consignment_no }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══ ITEMS TABLE ═══ --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:24px;">No</th>
                <th style="width:80px;">Product Code</th>
                <th class="th-desc">
                    Description<br>
                    <span class="th-sub">Set / Instrument list</span>
                </th>
                <th style="width:42px;">Proposed<br>Qty</th>
                <th style="width:70px;">Lot No</th>
                <th style="width:36px;">Qty<br>Out</th>
                <th style="width:36px;">Qty<br>In</th>
                <th style="width:70px;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consignment->consignmentItems as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item->lot?->product?->ref_num ?? '-' }}</td>
                <td>{{ $item->lot?->product?->product_name ?? '-' }}</td>
                <td class="center">1</td>
                <td>{{ $item->lot?->lot_number ?? '-' }}</td>
                <td class="center">&nbsp;</td>
                <td class="center">&nbsp;</td>
                <td>{{ $item->remarks ?? '' }}</td>
            </tr>
            @empty
            <tr class="empty-row">
                <td colspan="8">No items in this consignment.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ═══ FOOTER ═══ --}}
    <div class="footer">
        Printed at: {{ now()->format('d M Y H:i') }}
    </div>

</body>
</html>
