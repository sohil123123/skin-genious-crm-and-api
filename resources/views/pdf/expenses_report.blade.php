<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Expenses Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        th {
            background-color: #f3f4f6;
            padding: 7px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        td {
            padding: 7px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header {
            text-align: center;
            margin-bottom: 16px;
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
        .summary {
            margin-top: 12px;
            padding: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
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
        <div class="report-title">Expenses Report</div>
        <div style="margin-top: 5px;">Generated on: {{ $generated_at }}</div>
    </div>

    <div class="summary">
        <strong>Total Records:</strong> {{ number_format($expenses->count()) }}
        &nbsp; | &nbsp;
        <strong>Total Expenses:</strong> Rs. {{ number_format($total_amount, 2) }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="9%">Date</th>
                <th width="12%">Clinic</th>
                <th width="14%">Category</th>
                <th width="10%" class="text-right">Amount</th>
                <th width="10%">Payment</th>
                <th width="11%">Reference</th>
                <th width="12%">Vendor</th>
                <th width="9%">Approval</th>
                <th width="9%">Created By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $expense->expense_date ? $expense->expense_date->format('d-m-Y') : 'N/A' }}</td>
                    <td>{{ $expense->clinic?->name ?? '-' }}</td>
                    <td>{{ $expense->category?->name ?? '-' }}</td>
                    <td class="text-right"><strong>Rs. {{ number_format((float) $expense->amount, 2) }}</strong></td>
                    <td>{{ $expense->payment_method?->label() ?? ucfirst((string) $expense->payment_method) }}</td>
                    <td>
                        {{ $expense->referenceType()->label() }}
                        @if($expense->reference_id)
                            #{{ $expense->reference_id }}
                        @endif
                    </td>
                    <td>{{ $expense->vendor_name ?: '-' }}</td>
                    <td>{{ $expense->approvalStatus()->label() }}</td>
                    <td>{{ $expense->creator?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No expenses found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f9fafb;">
                <td colspan="4" class="text-right"><strong>TOTAL</strong></td>
                <td class="text-right"><strong>Rs. {{ number_format($total_amount, 2) }}</strong></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Page {PAGENO} of {nbpg} | This report reflects the records based on the filters applied during export.
    </div>
</body>
</html>
