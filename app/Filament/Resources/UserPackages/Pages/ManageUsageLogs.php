<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use App\Models\UserPackageUsage;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;

class ManageUsageLogs extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = UserPackageResource::class;

    protected string $view = 'filament.resources.user-packages.pages.manage-usage-logs';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Usage Logs for "' . $this->record->package_name . '"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Usage Logs';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserPackageUsage::query()
                    ->whereHas('packageItem', function (Builder $q) {
                        $q->where('user_package_id', $this->record->id);
                    })
                    ->with(['packageItem.service', 'recordedBy', 'appointment'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('packageItem.service.name')
                    ->label('Service')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-sparkles')
                    ->placeholder('Unknown Service'),

                TextColumn::make('sessions_used')
                    ->label('Sessions Used')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('packageItem.used_sessions')
                    ->label('After Usage')
                    ->formatStateUsing(fn ($record) =>
                        $record->packageItem->used_sessions . ' / ' . $record->packageItem->quantity
                    )
                    ->alignCenter(),

                TextColumn::make('appointment.start_datetime')
                    ->label('Appointment')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not linked')
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('No Usage Logs')
            ->emptyStateDescription('Session consumption will appear here once sessions are used.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        return $record !== null;
    }
}
