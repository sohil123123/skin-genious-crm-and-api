<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Services\Lead\CsvReaderService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class LeadExporter extends Exporter
{
    public static function getModel(): string
    {
        return Lead::class;
    }

    /**
     * Core columns plus one column per dynamic question.
     *
     * The custom-field columns are built from the registry at export time, so an
     * export automatically widens when a new lead form introduces a new
     * question — there is no column list to keep in sync.
     */
    public static function getColumns(): array
    {
        $columns = [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('clinic.name')->label('Clinic'),
            ExportColumn::make('full_name')->label('Name')->formatStateUsing(self::safe()),
            ExportColumn::make('phone')->label('Phone')->formatStateUsing(self::safe()),
            ExportColumn::make('phone_status')
                ->label('Phone Quality')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('phone_raw')->label('Original Phone')->formatStateUsing(self::safe()),
            ExportColumn::make('email')->label('Email')->formatStateUsing(self::safe()),
            ExportColumn::make('city')->label('City')->formatStateUsing(self::safe()),
            ExportColumn::make('state')->label('State')->formatStateUsing(self::safe()),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('source')
                ->label('Source')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? ''),
            ExportColumn::make('assignedStaff.name')->label('Assigned To'),
            ExportColumn::make('matchedUser.name')->label('Matching Patient'),
            ExportColumn::make('campaign_name')->label('Campaign')->formatStateUsing(self::safe()),
            ExportColumn::make('adset_name')->label('Ad Set')->formatStateUsing(self::safe()),
            ExportColumn::make('ad_name')->label('Ad')->formatStateUsing(self::safe()),
            ExportColumn::make('form_name')->label('Form')->formatStateUsing(self::safe()),
            ExportColumn::make('platform')->label('Platform'),
            ExportColumn::make('fb_lead_id')->label('Facebook Lead ID'),
            ExportColumn::make('fb_created_time')
                ->label('Submitted At')
                ->formatStateUsing(fn ($state): string => $state
                    ? Carbon::parse($state)->timezone(app_timezone())->format('d-m-Y H:i')
                    : ''),
            ExportColumn::make('created_at')
                ->label('Imported At')
                ->formatStateUsing(fn ($state): string => $state
                    ? Carbon::parse($state)->timezone(app_timezone())->format('d-m-Y H:i')
                    : ''),
            ExportColumn::make('notes')->label('Notes')->formatStateUsing(self::safe()),
        ];

        foreach (self::customFields() as $field) {
            $columns[] = ExportColumn::make('custom_' . $field->key)
                ->label($field->display_label)
                ->state(function (Lead $record) use ($field): string {
                    $value = $record->fieldValues
                        ->firstWhere('lead_custom_field_id', $field->getKey());

                    if ($value === null) {
                        return '';
                    }

                    return app(CsvReaderService::class)
                        ->escapeForExport(implode(', ', $value->display_values));
                });
        }

        return $columns;
    }

    /**
     * @return \Illuminate\Support\Collection<int, LeadCustomField>
     */
    protected static function customFields()
    {
        return LeadCustomField::query()
            ->where('is_active', true)
            ->where('usage_count', '>', 0)
            ->when(
                ! check_role(config('project.roles.super_admin')),
                fn ($query) => $query->forCurrentClinic()
            )
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Escape a value so it cannot execute as a formula in Excel.
     *
     * Lead names and campaign names come from an external system and end up in
     * a file a staff member will open in a spreadsheet, which is exactly the
     * path CSV injection exploits.
     */
    protected static function safe(): \Closure
    {
        return fn ($state): string => app(CsvReaderService::class)->escapeForExport((string) $state);
    }

    public static function modifyQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        // Without this the exporter issues a query per custom field per lead.
        return $query->with(['fieldValues.customField', 'clinic', 'assignedStaff', 'matchedUser']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your lead export has finished and ' . number_format($export->successful_rows) . ' '
            . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failed) . ' ' . str('row')->plural($failed) . ' failed to export.';
        }

        return $body;
    }
}
