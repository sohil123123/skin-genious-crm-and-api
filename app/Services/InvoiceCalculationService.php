<?php

namespace App\Services;

class InvoiceCalculationService
{
    /**
     * Calculate item gross amount (quantity * unit_price)
     */
    public function calculateGrossAmount(int|float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscountAmount(float $grossAmount, string $discountType, float $discountValue): float
    {
        if ($discountValue <= 0) {
            return 0.0;
        }

        if (in_array($discountType, ['percentage', '%'])) {
            return round($grossAmount * ($discountValue / 100), 2);
        }

        return round($discountValue, 2);
    }

    /**
     * Calculate taxable value from discounted amount using reverse GST calculation
     */
    public function calculateTaxableValue(float $discountedAmount, float $gstPercentage): float
    {
        if ($gstPercentage <= 0) {
            return round($discountedAmount, 2);
        }

        return round(($discountedAmount * 100) / (100 + $gstPercentage), 2);
    }

    /**
     * Calculate GST amount from discounted amount and taxable value
     */
    public function calculateGstAmount(float $discountedAmount, float $taxableValue): float
    {
        return round($discountedAmount - $taxableValue, 2);
    }

    /**
     * Calculate all metrics for a single line item based on inclusive pricing
     *
     * @return array
     */
    public function calculateLineItem(int|float $quantity, float $unitPrice, string $discountType, float $discountValue, float $gstPercentage): array
    {
        $grossAmount = $this->calculateGrossAmount($quantity, $unitPrice);
        $discountAmount = $this->calculateDiscountAmount($grossAmount, $discountType, $discountValue);
        
        $discountedAmount = round($grossAmount - $discountAmount, 2);
        // Fallback for negative amounts
        if ($discountedAmount < 0) {
            $discountedAmount = 0;
            $discountAmount = $grossAmount;
        }

        $taxableValue = $this->calculateTaxableValue($discountedAmount, $gstPercentage);
        $gstAmount = $this->calculateGstAmount($discountedAmount, $taxableValue);

        return [
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'discounted_amount' => $discountedAmount, // Line total
            'taxable_value' => $taxableValue,
            'gst_amount' => $gstAmount,
            'line_total' => $discountedAmount,
        ];
    }
}
