<?php

namespace App\Exports;

use App\Models\WhatsAppMessage;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppMessageExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected ?string $dateFrom;
    protected ?string $dateTo;
    protected ?string $status;
    protected ?string $templateName;

    public function __construct(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null,
        ?string $templateName = null
    ) {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->status = $status;
        $this->templateName = $templateName;
    }

    public function query(): Builder
    {
        $query = WhatsAppMessage::query()->with([
            'user:id,first_name,last_name,mobile',
            'conversation:id,display_name,phone_number'
        ]);

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->templateName) {
            $query->where('template_name', $this->templateName);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Client Name',
            'Phone Number',
            'Template',
            'Message Type',
            'Direction',
            'Status',
            'Meta Message ID',
            'Content',
            'Failed Reason',
            'Sent At',
            'Delivered At',
            'Read At',
            'Failed At',
            'Retry Count',
            'Created At',
        ];
    }

    public function map($message): array
    {
        return [
            $message->id,
            $message->conversation?->display_name ?? 'System/AI',
            $message->conversation?->phone_number ?? 'N/A',
            $message->template_name,
            $message->type?->value ?? $message->type,
            $message->direction?->value ?? $message->direction,
            $message->status?->value ?? $message->status,
            $message->message_id,
            $message->text_body,
            $message->failed_reason,
            $message->sent_at?->format('Y-m-d H:i:s'),
            $message->delivered_at?->format('Y-m-d H:i:s'),
            $message->read_at?->format('Y-m-d H:i:s'),
            $message->failed_at?->format('Y-m-d H:i:s'),
            $message->retry_count,
            $message->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
