<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            line-height: 1.5;
        }

        @page {
            /* margin-top: 35mm;
            margin-bottom: 20mm; */
            margin-left: 15mm;
            margin-right: 15mm;
            /* header: mainHeader; */
            footer: mainFooter;
        }
        
        /* Layout Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        /* Header Section */
        .header-section {
            margin-bottom: 30px;
        }
        
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .company-address {
            font-size: 9pt;
            color: #555;
            line-height: 1.4;
        }
        
        .invoice-title {
            font-size: 22pt;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            text-align: right;
            margin-bottom: 5px;
        }
        
        .invoice-meta {
            text-align: right;
            font-size: 9.5pt;
            color: #555;
            line-height: 1.4;
        }

        /* Customer & Payment Info */
        .info-table {
            margin-bottom: 30px;
        }

        .info-table td {
            vertical-align: top;
            padding: 0;
        }
        
        .info-box {
            /* margin-bottom: 20px; */
        }
        
        .info-title {
            font-size: 9pt;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
            margin-bottom: 5px;
            width: 90%;
            display: block;
        }

        .client-info, .payment-info {
            font-size: 9.5pt;
            line-height: 1.4;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .items-table th {
            background-color: #f8f8f8;
            color: #333;
            font-weight: bold;
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ccc;
            font-size: 9pt;
            text-transform: uppercase;
        }
        
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            color: #333;
            font-size: 9.5pt;
            vertical-align: middle;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }

        /* Utilities */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .no-wrap { white-space: nowrap; }

        /* Totals Section */
        .totals-container {
            width: 100%;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 45%; 
            margin-left: auto; /* Push to right */
        }
        
        .totals-table td {
            padding: 6px 0;
            text-align: right;
        }
        
        .totals-table .label {
            color: #666;
            padding-right: 15px;
            width: 60%;
        }

        .totals-table .value {
            width: 40%;
        }
        
        .grand-total-row td {
            padding-top: 10px;
            border-top: 2px solid #333;
        }

        .grand-total-label, .grand-total-value {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
        }

        .status-paid { color: #16a34a; font-weight: bold; }
        .status-pending { color: #ea580c; font-weight: bold; }
        .status-cancelled { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>

    <!-- Defined Footer -->
    <htmlpagefooter name="mainFooter">
        <table width="100%" style="font-size: 8pt; border: none;">
            <tr>
                <td align="left" style="border: none;">
                    This is a computer-generated invoice. No signature required.
                </td>
                <td align="right" style="border: none;">
                    Page {PAGENO} / {nbpg}
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    <!-- Header -->
    <table class="header-section">
        <tr>
            <td width="60%" valign="top">
                <div class="company-name">{{ $clinic->name }}</div>
                <div class="company-address">
                    {{ $clinic->address_line1 }}<br>
                    {{ $clinic->city }} - {{ $clinic->pincode }}<br>
                    <strong>GSTIN:</strong> {{ $clinic->gst_number }}<br>
                    <strong>Email:</strong> {{ $clinic->email }} | <strong>Phone:</strong> {{ $clinic->phone }}
                </div>
            </td>
            <td width="40%" valign="top">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    Invoice #: <strong>{{ $invoice->id }}</strong><br>
                    Date: <strong>{{ $invoice->invoice_date->format('d M, Y') }}</strong><br>
                    Status: <span class="status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Billing Info -->
    <table class="info-table">
        <tr>
            <td width="55%" valign="top">
                <div class="info-box">
                    <div class="info-title">Bill To</div>
                    <div class="client-info">
                        <strong>{{ $client->name }}</strong><br>
                        {{ $client->mobile }}<br>
                        {{ $client->email }}
                    </div>
                </div>
            </td>
            <td width="45%" valign="top">
                <div class="info-box">
                    <div class="info-title">Payment Details</div>
                    <div class="payment-info">
                        Mode: {{ $invoice->payment_mode }}<br>
                        @if($invoice->source_note)
                        Note: {{ $invoice->source_note }}
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items -->
    <table class="items-table" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th width="5%" class="text-center">#</th>
                <th width="40%">Item Description</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="15%" class="text-right">Price</th>
                <th width="15%" class="text-right">Tax (GST)</th>
                <th width="15%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <span class="font-bold">{{ $item->product->name }}</span>
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">
                    <div style="font-size: 8pt; color: #666;">{{ $item->gst_percentage }}%</div>
                    <div>₹{{ number_format($item->gst_amount, 2) }}</div>
                </td>
                <td class="text-right font-bold">₹{{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-container">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">₹{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->discount_total > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="value" style="color: green;">- ₹{{ number_format($invoice->discount_total, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Taxable Value</td>
                <td class="value">₹{{ number_format($invoice->taxable_value, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total GST</td>
                <td class="value">₹{{ number_format($invoice->gst_total, 2) }}</td>
            </tr>
            <tr class="grand-total-row">
                <td class="grand-total-label">Grand Total</td>
                <td class="grand-total-value">₹{{ number_format($invoice->grand_total, 2) }}</td>
            </tr>
        </table>
        <div style="clear: both;"></div>
    </div>
</body>
</html>
