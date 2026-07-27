<?php

namespace App\Filament\Pages;

use App\Exports\WhatsAppMessageExport;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;

class WhatsAppReports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'Reports & Export';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 27;

    protected string $view = 'filament.pages.whatsapp-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->subMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Report Filters')
                ->icon('heroicon-o-funnel')
                ->schema([
                    Grid::make(4)->schema([
                        DatePicker::make('date_from')
                            ->label('From Date')
                            ->native(false),

                        DatePicker::make('date_to')
                            ->label('To Date')
                            ->native(false),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                '' => 'All',
                                'sent' => 'Sent',
                                'delivered' => 'Delivered',
                                'read' => 'Read',
                                'failed' => 'Failed',
                                'pending' => 'Pending',
                            ])
                            ->placeholder('All Statuses'),

                        Select::make('template_name')
                            ->label('Template')
                            ->options(fn () => WhatsAppTemplate::pluck('name', 'name')->toArray())
                            ->searchable()
                            ->placeholder('All Templates'),
                    ]),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $data = $this->form->getState();

                    return Excel::download(
                        new WhatsAppMessageExport(
                            $data['date_from'] ?? null,
                            $data['date_to'] ?? null,
                            $data['status'] ?? null,
                            $data['template_name'] ?? null
                        ),
                        'whatsapp-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    $data = $this->form->getState();

                    return Excel::download(
                        new WhatsAppMessageExport(
                            $data['date_from'] ?? null,
                            $data['date_to'] ?? null,
                            $data['status'] ?? null,
                            $data['template_name'] ?? null
                        ),
                        'whatsapp-report-' . now()->format('Y-m-d') . '.csv',
                        \Maatwebsite\Excel\Excel::CSV
                    );
                }),
        ];
    }
}
