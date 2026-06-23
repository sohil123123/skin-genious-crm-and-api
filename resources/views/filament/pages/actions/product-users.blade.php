<div class="product-users-container">
    @if ($clients->isEmpty())
        <div class="product-users-empty">
            No purchases found for this product in the selected period.
        </div>
    @else
        <div class="product-users-table-container">
            <table class="product-users-table">
                <thead>
                    <tr>
                        <th scope="col" class="product-users-th" style="text-align: left;">Client Name</th>
                        <th scope="col" class="product-users-th" style="text-align: left;">Contact Info</th>
                        <th scope="col" class="product-users-th" style="text-align: center;">Quantity Purchased</th>
                        <th scope="col" class="product-users-th" style="text-align: right;">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr class="product-users-tr">
                            <td class="product-users-td product-users-name" style="text-align: left;">
                                {{ $client['name'] }}
                            </td>
                            <td class="product-users-td" style="text-align: left;">
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span style="font-weight: 500;">{{ $client['mobile'] ?: 'N/A' }}</span>
                                    @if($client['email'])
                                        <span style="font-size: 0.75rem; opacity: 0.8;">{{ $client['email'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="product-users-td" style="text-align: center;">
                                <span class="product-users-badge">
                                    {{ $client['total_qty'] }}
                                </span>
                            </td>
                            <td class="product-users-td product-users-spent" style="text-align: right;">
                                ₹{{ number_format($client['total_spent'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
    .product-users-container {
        width: 100%;
        padding: 0.5rem 0;
    }

    .product-users-empty {
        padding: 2rem;
        text-align: center;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .dark .product-users-empty {
        color: #9ca3af;
    }

    .product-users-table-container {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        background-color: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }

    .dark .product-users-table-container {
        border-color: #374151;
        background-color: #111827;
    }

    .product-users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .product-users-th {
        background-color: #f9fafb;
        color: #374151;
        font-weight: 600;
        padding: 14px 20px;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e5e7eb;
    }

    .dark .product-users-th {
        background-color: #1f2937;
        color: #9ca3af;
        border-bottom-color: #374151;
    }

    .product-users-tr {
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.15s ease-in-out;
    }

    .dark .product-users-tr {
        border-bottom-color: #374151;
    }

    .product-users-tr:last-child {
        border-bottom: none;
    }

    .product-users-tr:hover {
        background-color: #f9fafb;
    }

    .dark .product-users-tr:hover {
        background-color: #1f2937;
    }

    .product-users-td {
        padding: 14px 20px;
        color: #4b5563;
        vertical-align: middle;
    }

    .dark .product-users-td {
        color: #d1d5db;
    }

    .product-users-name {
        font-weight: 600;
        color: #111827;
    }

    .dark .product-users-name {
        color: #ffffff;
    }

    .product-users-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .dark .product-users-badge {
        background-color: rgba(22, 101, 52, 0.2);
        color: #4ade80;
        border-color: rgba(74, 222, 128, 0.3);
    }

    .product-users-spent {
        font-weight: 700;
        color: #111827;
    }

    .dark .product-users-spent {
        color: #ffffff;
    }
</style>
