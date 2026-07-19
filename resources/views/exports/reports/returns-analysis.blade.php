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

        /* ── Info Box ── */
        .info-box {
            border: 1.5px solid #111;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 14px;
            background-color: #fcfcfc;
        }
        .info-box-inner {
            width: 100%;
            table-layout: fixed;
        }
        .field-row { margin-bottom: 8px; }
        .field-row td { vertical-align: top; padding-bottom: 6px; }
        .field-label { font-weight: bold; font-size: 9px; color: #444; }
        .field-colon { width: 10px; font-weight: bold; color: #444; }
        .field-value {
            font-size: 9px;
            color: #111;
            font-weight: 500;
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
                    <div class="doc-type-badge" style="margin-bottom: 0;">{{ $title }}</div>
                    <div style="font-size: 8px; color: #555; margin-top: 5px;">Generated: {{ now()->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ═══ SUMMARY BOX ═══ --}}
    @if(!empty($summary))
    <div class="info-box">
        <table class="info-box-inner">
            @php
                // Only take scalar values or simple stringable values for the summary box
                $summaryKeys = collect($summary)->filter(fn($v) => is_scalar($v))->keys()->toArray();
                $chunks = array_chunk($summaryKeys, 3);
            @endphp
            @foreach($chunks as $chunk)
            <tr>
                @foreach($chunk as $key)
                <td style="width: 33.3%; vertical-align: top; padding-bottom: 6px;">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td class="field-label" style="width: 120px; white-space: nowrap;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                            <td class="field-colon" style="width: 10px;">:</td>
                            <td class="field-value">{{ $summary[$key] }}</td>
                        </tr>
                    </table>
                </td>
                @endforeach
                @for($i = count($chunk); $i < 3; $i++)
                <td style="width: 33.3%;"></td>
                @endfor
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    {{-- ═══ ITEMS TABLE ═══ --}}
    @if(!empty($rows))
    <table class="items">
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
    <div style="text-align: center; padding: 20px; font-style: italic; color: #888;">
        No data available for the selected filters.
    </div>
    @endif

</body>
</html>
