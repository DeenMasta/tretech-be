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

        /* ── Signatures ── */
        .signatures {
            width: 100%;
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            border-top: 1.5px solid #111;
            width: 220px;
            margin: 0 auto;
            padding-top: 6px;
            font-weight: bold;
            font-size: 10px;
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
                    <div class="doc-type-badge" style="margin-bottom: 0;">Reconciliation / Template</div>
                </td>
            </tr>
        </table>
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
                            <td class="field-value">{{ $reconciliation->consignment?->client?->client_name ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Surgeon</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{!! $reconciliation->consignment?->surgeon_name ?: '&nbsp;' !!}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Date Case</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $reconciliation->consignment?->case_date?->format('d/m/Y') ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Set No.</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">
                                @php
                                    $sets = $reconciliation->consignment?->consignmentItems->filter(fn($i) => $i->entry_kind === 'set');
                                    $setNames = $sets ? $sets->map(fn($i) => $i->instrumentSet?->set_name)->filter()->join(', ') : '';
                                @endphp
                                {!! $setNames ?: '&nbsp;' !!}
                            </td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Case</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{!! $reconciliation->consignment?->case_name ?: '&nbsp;' !!}</td>
                        </tr>
                    </table>
                </td>

                {{-- Right column: dates + prepared by + ref --}}
                <td class="info-col-right">
                    <table>
                        <tr class="field-row">
                            <td class="field-label">Date Prepared</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $reconciliation->created_at?->format('d/m/Y') ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Consignment Ref</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $reconciliation->consignment?->consignment_no ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Prepared by</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $reconciliation->picUser?->full_name ?? '' }}</td>
                        </tr>
                        <tr class="field-row">
                            <td class="field-label">Ref</td>
                            <td class="field-colon">:</td>
                            <td class="field-value">{{ $reconciliation->reconciliation_no ?? $reconciliation->id }}</td>
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
                </th>
                <th style="width:70px;">Lot No</th>
                <th style="width:36px;">Qty<br>Out</th>
                <th style="width:36px;">Qty<br>Used</th>
                <th style="width:36px;">Qty<br>In</th>
                <th style="width:70px;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php
                $items = $reconciliation->reconciliationItems;
                $setItems = $items->filter(
                    fn($item) => $item->instrument_set_id || $item->setInstrumentResults->isNotEmpty()
                );
                $standaloneItems = $items->reject(
                    fn($item) => $item->instrument_set_id || $item->setInstrumentResults->isNotEmpty()
                );
                $instrumentItems = $standaloneItems->filter(
                    fn($item) => strtolower((string) ($item->lot?->product?->product_type ?? $item->product?->product_type ?? '')) === 'instrument'
                );
                $implantItems = $standaloneItems->reject(
                    fn($item) => strtolower((string) ($item->lot?->product?->product_type ?? $item->product?->product_type ?? '')) === 'instrument'
                );
            @endphp

            @if($items->isEmpty())
                <tr class="empty-row">
                    <td colspan="8">No items in this reconciliation.</td>
                </tr>
            @else
                {{-- 1. Instrument sets --}}
                @foreach($setItems as $setItem)
                    <tr>
                        <td colspan="8" style="background-color: #f0f0f0; font-weight: bold; text-align: left; padding-left: 10px;">
                            {{ $setItem->instrumentSet?->set_name ?? $setItem->lot?->instrumentSet?->set_name ?? 'Instrument Set' }}
                        </td>
                    </tr>
                    @php $setNo = 1; @endphp
                    @forelse($setItem->setInstrumentResults as $result)
                        <tr>
                            <td class="center">{{ $setNo++ }}</td>
                            <td>{{ $result->product?->ref_num ?? '-' }}</td>
                            <td>{{ $result->product?->product_name ?? '-' }}</td>
                            <td>-</td>
                            <td class="center">{{ $result->expected_quantity }}</td>
                            <td class="center">{{ $result->used_quantity }}</td>
                            <td class="center">{{ $result->returned_quantity }}</td>
                            <td>{{ $setItem->remarks ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="center" style="color: #888; font-style: italic;">No instruments listed in this set.</td>
                        </tr>
                    @endforelse
                @endforeach

                {{-- 2. A la carte instruments --}}
                @if($instrumentItems->isNotEmpty())
                    <tr>
                        <td colspan="8" style="background-color: #f0f0f0; font-weight: bold; text-align: left; padding-left: 10px;">
                            Instruments
                        </td>
                    </tr>
                    @php $instrumentNo = 1; @endphp
                    @foreach($instrumentItems as $item)
                        <tr>
                            <td class="center">{{ $instrumentNo++ }}</td>
                            <td>{{ $item->lot?->product?->ref_num ?? $item->product?->ref_num ?? '-' }}</td>
                            <td>{{ $item->lot?->product?->product_name ?? $item->product?->product_name ?? '-' }}</td>
                            <td>{{ $item->lot?->lot_number ?? '-' }}</td>
                            <td class="center">{{ $item->quantity ?? 1 }}</td>
                            <td class="center">{{ $item->used_quantity ?? 0 }}</td>
                            <td class="center">{{ $item->returned_quantity ?? 0 }}</td>
                            <td>{{ $item->remarks ?? '' }}</td>
                        </tr>
                    @endforeach
                @endif

                {{-- 3. Implants --}}
                @if($implantItems->isNotEmpty())
                    <tr>
                        <td colspan="8" style="background-color: #f0f0f0; font-weight: bold; text-align: left; padding-left: 10px;">
                            Implants
                        </td>
                    </tr>
                    @php $implantNo = 1; @endphp
                    @foreach($implantItems as $item)
                    <tr>
                        <td class="center">{{ $implantNo++ }}</td>
                        <td>{{ $item->lot?->product?->ref_num ?? $item->product?->ref_num ?? '-' }}</td>
                        <td>{{ $item->lot?->product?->product_name ?? $item->product?->product_name ?? '-' }}</td>
                        <td>{{ $item->lot?->lot_number ?? '-' }}</td>
                        <td class="center">{{ $item->quantity ?? 1 }}</td>
                        <td class="center">{{ $item->used_quantity ?? 0 }}</td>
                        <td class="center">{{ $item->returned_quantity ?? 0 }}</td>
                        <td>{{ $item->remarks ?? '' }}</td>
                    </tr>
                    @endforeach
                @endif
            @endif
        </tbody>
    </table>

    {{-- ═══ SIGNATURES ═══ --}}
    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">
                    TREMED SURGICAL SOLUTION
                </div>
            </td>
            <td>
                <div class="signature-line">
                    CUSTOMER SIGNATURE & STAMP
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
