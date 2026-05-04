<x-filament-panels::page>
    @php
        $invoice = $this->getOwnerRecord();
        $isPaid = $invoice->status === 'paid';
        $isCancelled = $invoice->status === 'cancelled';
    @endphp

    {{-- Paid / Cancelled Watermark Banner --}}
    @if($isPaid)
        <div style="position: relative; overflow: hidden;">
            {{-- Watermark Overlay --}}
            <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                font-size: 8rem;
                font-weight: 900;
                color: rgba(34, 197, 94, 0.10);
                letter-spacing: 1rem;
                pointer-events: none;
                z-index: 10;
                white-space: nowrap;
                user-select: none;
                text-transform: uppercase;
            ">PAID</div>

            {{-- Green Status Banner --}}
            <div style="
                background: linear-gradient(135deg, #dcfce7, #bbf7d0);
                border: 2px solid #22c55e;
                border-radius: 12px;
                padding: 1rem 1.5rem;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: center;
                gap: 1rem;
            ">
                <div style="
                    background: #22c55e;
                    color: white;
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                    flex-shrink: 0;
                ">✓</div>
                <div>
                    <p style="font-size: 1.25rem; font-weight: 700; color: #15803d; margin: 0;">
                        Invoice Fully Paid
                    </p>
                    <p style="font-size: 0.875rem; color: #166534; margin: 0; opacity: 0.8;">
                        Total: ₹{{ number_format($invoice->grand_total, 2) }} — Paid: ₹{{ number_format($invoice->amount_paid, 2) }}
                    </p>
                </div>
            </div>

            {{ $this->table }}
        </div>
    @elseif($isCancelled)
        <div style="
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        ">
            <div style="
                background: #ef4444;
                color: white;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                flex-shrink: 0;
            ">✕</div>
            <div>
                <p style="font-size: 1.25rem; font-weight: 700; color: #991b1b; margin: 0;">
                    Invoice Cancelled
                </p>
                <p style="font-size: 0.875rem; color: #991b1b; margin: 0; opacity: 0.8;">
                    This invoice has been cancelled. No further payments can be recorded.
                </p>
            </div>
        </div>
        {{ $this->table }}
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
