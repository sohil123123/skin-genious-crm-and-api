<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchases Report</title>
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
            padding: 8px;
            border: 1px solid #ddd;
        }
        .text-right { text-align: right; }
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
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-name">{{ $clinic->name ?? 'Skin Genious CRM' }}</div>
        <div class="report-title">Purchases Report</div>
        <div style="margin-top: 5px;">Generated on: {{ $generated_at }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="15%">Date</th>
                <th width="20%">Supplier</th>
                <th width="10%">Payment</th>
                <th width="10%">Status</th>
                <th width="12%" class="text-right">Subtotal</th>
                <th width="10%" class="text-right">GST</th>
                <th width="10%" class="text-right">Discount</th>
                <th width="12%" class="text-right">Grand Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalAmt = 0;
                $totalGst = 0;
                $totalDisc = 0;
            @endphp
            @foreach($purchases as $purchase)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $purchase->purchase_date ? $purchase->purchase_date->format('d-m-Y') : 'N/A' }}</td>
                    <td>{{ $purchase->supplier_name }}</td>
                    <td>{{ ucfirst($purchase->payment_mode) }}</td>
                    <td>{{ ucfirst($purchase->status) }}</td>
                    <td class="text-right">₹{{ number_format($purchase->subtotal, 2) }}</td>
                    <td class="text-right">₹{{ number_format($purchase->total_gst, 2) }}</td>
                    <td class="text-right">₹{{ number_format($purchase->total_discount, 2) }}</td>
                    <td class="text-right"><strong>₹{{ number_format($purchase->total_amount, 2) }}</strong></td>
                </tr>
                @php
                    $totalAmt += $purchase->total_amount;
                    $totalGst += $purchase->total_gst;
                    $totalDisc += $purchase->total_discount;
                @endphp
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9fafb;">
                <td colspan="6" class="text-right"><strong>TOTALS</strong></td>
                <td class="text-right"><strong>₹{{ number_format($totalGst, 2) }}</strong></td>
                <td class="text-right"><strong>₹{{ number_format($totalDisc, 2) }}</strong></td>
                <td class="text-right" style="background-color: #f3f4f6;"><strong>₹{{ number_format($totalAmt, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Page {PAGENO} of {nbpg} | This report reflects the records based on the filters applied during export.
    </div>
</body>
</html>
