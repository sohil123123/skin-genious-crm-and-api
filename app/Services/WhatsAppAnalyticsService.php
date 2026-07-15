<?php

namespace App\Services;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppAnalyticsService
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard Stats
    |--------------------------------------------------------------------------
    */

    /**
     * Get overview stats for the WhatsApp dashboard.
     */
    public function getDashboardStats(): array
    {
        $totalSent = WhatsAppMessage::sent()->count();
        $totalDelivered = WhatsAppMessage::delivered()->count();
        $totalRead = WhatsAppMessage::read()->count();
        $totalFailed = WhatsAppMessage::failed()->count();
        $totalPending = WhatsAppMessage::pending()->count();
        $todayMessages = WhatsAppMessage::today()->count();
        $thisMonthMessages = WhatsAppMessage::thisMonth()->count();

        $total = $totalSent + $totalFailed + $totalPending;
        $successRate = $total > 0 ? round(($totalSent / $total) * 100, 1) : 0;

        return [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_read' => $totalRead,
            'total_failed' => $totalFailed,
            'total_pending' => $totalPending,
            'success_rate' => $successRate,
            'today_messages' => $todayMessages,
            'this_month_messages' => $thisMonthMessages,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Chart Data
    |--------------------------------------------------------------------------
    */

    /**
     * Get messages per day for the last N days.
     */
    public function getMessagesPerDay(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $results = WhatsAppMessage::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) as sent"),
            DB::raw("SUM(CASE WHEN status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered"),
            DB::raw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`"),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Fill in missing dates with zeros
        $data = [];
        $currentDate = $startDate->copy();
        $endDate = Carbon::now();

        $resultsByDate = $results->keyBy('date');

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $row = $resultsByDate->get($dateStr);

            $data[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('M d'),
                'total' => $row?->total ?? 0,
                'sent' => $row?->sent ?? 0,
                'delivered' => $row?->delivered ?? 0,
                'read' => $row?->read ?? 0,
                'failed' => $row?->failed ?? 0,
            ];

            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * Get delivery rate breakdown for chart.
     */
    public function getDeliveryRateBreakdown(): array
    {
        $total = WhatsAppMessage::count();

        if ($total === 0) {
            return [
                'delivered' => 0,
                'read' => 0,
                'sent_only' => 0,
                'failed' => 0,
                'pending' => 0,
            ];
        }

        return [
            'read' => WhatsAppMessage::read()->count(),
            'delivered' => WhatsAppMessage::delivered()->count() - WhatsAppMessage::read()->count(),
            'sent_only' => WhatsAppMessage::where('status', 'sent')->count(),
            'failed' => WhatsAppMessage::failed()->count(),
            'pending' => WhatsAppMessage::pending()->count(),
        ];
    }

    /**
     * Get template performance stats.
     */
    public function getTemplatePerformance(int $limit = 10): array
    {
        return WhatsAppMessage::select(
            'template_name',
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) as sent"),
            DB::raw("SUM(CASE WHEN status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered"),
            DB::raw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`"),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->whereNotNull('template_name')
            ->groupBy('template_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->delivery_rate = $row->sent > 0 ? round(($row->delivered / $row->sent) * 100, 1) : 0;
                $row->read_rate = $row->delivered > 0 ? round(($row->read / $row->delivered) * 100, 1) : 0;
                return $row;
            })
            ->toArray();
    }

    /**
     * Get campaign performance summary.
     */
    public function getCampaignPerformance(int $limit = 10): array
    {
        return WhatsAppCampaign::with('template:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($campaign) {
                $totalSuccessGroup = ($campaign->sent_count ?? 0) + ($campaign->failed_count ?? 0);
                $sentCount = $campaign->sent_count ?? 0;
                $deliveredCount = $campaign->delivered_count ?? 0;
                $readCount = $campaign->read_count ?? 0;

                $successRate = $totalSuccessGroup > 0 ? round(($sentCount / $totalSuccessGroup) * 100, 1) : 0;
                $deliveryRate = $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 1) : 0;
                $readRate = $deliveredCount > 0 ? round(($readCount / $deliveredCount) * 100, 1) : 0;

                $createdAt = null;
                if (isset($campaign->created_at)) {
                    $createdAt = $campaign->created_at instanceof \Carbon\Carbon 
                        ? $campaign->created_at->format('Y-m-d H:i') 
                        : \Carbon\Carbon::parse($campaign->created_at)->format('Y-m-d H:i');
                }

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'template' => $campaign->template?->name ?? 'N/A',
                    'status' => is_object($campaign->status) && isset($campaign->status->value) ? $campaign->status->value : $campaign->status,
                    'total' => $campaign->total_recipients,
                    'sent' => $sentCount,
                    'delivered' => $deliveredCount,
                    'read' => $readCount,
                    'failed' => $campaign->failed_count ?? 0,
                    'success_rate' => $successRate,
                    'delivery_rate' => $deliveryRate,
                    'read_rate' => $readRate,
                    'created_at' => $createdAt,
                ];
            })
            ->toArray();
    }

    /**
     * Get hourly message distribution for today.
     */
    public function getHourlyDistribution(): array
    {
        $results = WhatsAppMessage::select(
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COUNT(*) as count')
        )
            ->whereDate('created_at', today())
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $data[] = [
                'hour' => sprintf('%02d:00', $h),
                'count' => $results[$h] ?? 0,
            ];
        }

        return $data;
    }
}
