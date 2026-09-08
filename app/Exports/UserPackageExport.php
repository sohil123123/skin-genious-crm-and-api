<?php

namespace App\Exports;

use App\Models\UserPackage;

class UserPackageExport extends SelectableColumnsExport
{
    public static function relationsToLoad(): array
    {
        return ['user', 'clinic', 'createdBy', 'items.service'];
    }

    public static function columnDefinitions(): array
    {
        return [
            'package_name' => [
                'label' => 'Package',
                'value' => fn (UserPackage $package): string => (string) $package->package_name,
                'type' => 'text',
                'width' => 24,
            ],
            'clinic' => [
                'label' => 'Clinic',
                'value' => fn (UserPackage $package): string => $package->clinic?->name ?? '',
                'type' => 'text',
                'width' => 22,
            ],
            'client' => [
                'label' => 'Client',
                'value' => fn (UserPackage $package): string => $package->user?->name ?? 'N/A',
                'type' => 'text',
                'width' => 22,
            ],
            'client_mobile' => [
                'label' => 'Client Mobile',
                'value' => fn (UserPackage $package): string => (string) ($package->user?->mobile ?? ''),
                'type' => 'text',
                'width' => 15,
            ],
            'client_email' => [
                'label' => 'Client Email',
                'value' => fn (UserPackage $package): string => (string) ($package->user?->email ?? ''),
                'type' => 'text',
                'width' => 24,
            ],
            'services' => [
                'label' => 'Services',
                'value' => fn (UserPackage $package): string => $package->items
                    ->map(fn ($item): string => $item->service?->name ?? ($item->service_snapshot['name'] ?? 'Unknown'))
                    ->join(', '),
                'type' => 'text',
                'width' => 36,
            ],
            'services_count' => [
                'label' => 'No. of Services',
                'value' => fn (UserPackage $package): int => $package->items->count(),
                'type' => 'number',
                'sum' => true,
                'width' => 14,
            ],
            'total_sessions' => [
                'label' => 'Total Sessions',
                'value' => fn (UserPackage $package): int => $package->getTotalSessions(),
                'type' => 'number',
                'sum' => true,
                'width' => 14,
            ],
            'used_sessions' => [
                'label' => 'Used Sessions',
                'value' => fn (UserPackage $package): int => $package->getTotalUsedSessions(),
                'type' => 'number',
                'sum' => true,
                'width' => 14,
            ],
            'remaining_sessions' => [
                'label' => 'Remaining Sessions',
                'value' => fn (UserPackage $package): int => $package->getTotalRemainingSessions(),
                'type' => 'number',
                'sum' => true,
                'width' => 18,
            ],
            'subtotal' => [
                'label' => 'Subtotal',
                'value' => fn (UserPackage $package): float => round((float) $package->subtotal, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'discount_type' => [
                'label' => 'Discount Type',
                'value' => fn (UserPackage $package): string => $package->discount_type?->getLabel() ?? '',
                'type' => 'text',
                'width' => 16,
            ],
            'discount_value' => [
                'label' => 'Discount Value',
                'value' => fn (UserPackage $package): float => round((float) $package->discount_value, 2),
                'type' => 'currency',
                'width' => 14,
            ],
            'discount_amount' => [
                'label' => 'Discount Amount',
                'value' => fn (UserPackage $package): float => round((float) $package->discount_amount, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 16,
            ],
            'final_amount' => [
                'label' => 'Final Amount',
                'value' => fn (UserPackage $package): float => round((float) $package->final_amount, 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 16,
            ],
            'paid_amount' => [
                'label' => 'Paid Amount',
                'value' => fn (UserPackage $package): float => round($package->getPaidAmount(), 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'outstanding_amount' => [
                'label' => 'Outstanding',
                'value' => fn (UserPackage $package): float => round($package->getOutstandingAmount(), 2),
                'type' => 'currency',
                'sum' => true,
                'width' => 14,
            ],
            'is_active' => [
                'label' => 'Status',
                'value' => fn (UserPackage $package): string => $package->is_active ? 'Active' : 'Inactive',
                'type' => 'text',
                'width' => 12,
            ],
            'expired_at' => [
                'label' => 'Expires On',
                'value' => fn (UserPackage $package): string => $package->expired_at?->format('d-m-Y') ?? 'No expiry',
                'type' => 'text',
                'width' => 14,
            ],
            'notes' => [
                'label' => 'Notes',
                'value' => fn (UserPackage $package): string => (string) ($package->notes ?? ''),
                'type' => 'text',
                'width' => 30,
            ],
            'created_by' => [
                'label' => 'Created By',
                'value' => fn (UserPackage $package): string => $package->createdBy?->name ?? '',
                'type' => 'text',
                'width' => 20,
            ],
            'created_at' => [
                'label' => 'Created At',
                'value' => fn (UserPackage $package): string => $package->created_at?->format('d-m-Y H:i') ?? '',
                'type' => 'text',
                'width' => 18,
            ],
        ];
    }

    public function title(): string
    {
        return $this->reportTitle ?: 'Packages';
    }
}
