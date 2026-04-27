<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Consumable Transfers Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        td {
            padding: 7px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .clinic-name {
            font-size: 16pt;
            font-weight: bold;
        }
        .report-title {
            font-size: 14pt;
            color: #666;
            margin-top: 5px;
        }
        .footer {
            margin-top: 20px;
            font-size: 8pt;
            color: #777;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            font-size: 8pt;
            color: #374151;
        }
        .products-list {
            font-size: 8pt;
            color: #555;
        }
        tr:nth-child(even) td {
            background-color: #fafafa;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-name">{{ $clinic->name ?? 'Skin Genious CRM' }}</div>
        <div class="report-title">Consumable Transfers / Stock Usage Report</div>
        <div style="margin-top: 5px;">Generated on: {{ $generated_at }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="4%">#</th>
                @if(auth()->user()->hasRole('super_admin'))
                <th width="13%">Clinic</th>
                @endif
                <th width="10%">Date</th>
                <th width="5%" class="text-center">Items</th>
                <th width="40%">Products Used</th>
                <th width="13%">Created By</th>
                <th width="15%">Notes</th>
            </tr>
        </thead>
        <tbody>
            @php $totalItems = 0; @endphp
            @foreach($transfers as $transfer)
                @php
                    $totalItems += $transfer->items->count();
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    @if(auth()->user()->hasRole('super_admin'))
                    <td>{{ $transfer->clinic->name ?? '—' }}</td>
                    @endif
                    <td>{{ $transfer->transfer_date ? $transfer->transfer_date->format('d-m-Y') : 'N/A' }}</td>
                    <td class="text-center">
                        <span class="badge">{{ $transfer->items->count() }}</span>
                    </td>
                    <td class="products-list">
                        @foreach($transfer->items as $item)
                            {{ $item->product->name ?? '—' }} <strong>(×{{ $item->quantity_used }})</strong>{{ !$loop->last ? ' &bull; ' : '' }}
                        @endforeach
                    </td>
                    <td>{{ $transfer->creator->first_name ?? '—' }}</td>
                    <td>{{ $transfer->notes ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f3f4f6;">
                <td colspan="{{ auth()->user()->hasRole('super_admin') ? 3 : 2 }}" class="text-right"><strong>TOTALS</strong></td>
                <td class="text-center"><strong>{{ $totalItems }}</strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Page {PAGENO} of {nbpg} | This report reflects records based on the filters applied during export.
    </div>
</body>
</html>
