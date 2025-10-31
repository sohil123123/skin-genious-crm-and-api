<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\EditHoliday;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Filament\Resources\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Holidays\Tables\HolidaysTable;
use Filament\Notifications\Notification;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Support\Exceptions\Halt;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use DateTime;
use UnitEnum;

use App\Filament\Resources\Holidays\Schemas\HolidayInfolist;

use App\Models\User;
use App\Models\Holiday;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = null;

    // protected static string | UnitEnum | null $navigationGroup = 'Therapist Management';

    protected static ?string $navigationBadgeTooltip = 'The number of holidays booked';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return HolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return HolidayInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
            'create' => CreateHoliday::route('/create'),
            // 'view' => Pages\ViewHoliday::route('/{record}'),
            'edit' => EditHoliday::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // return static::getModel()::count();

        // $query = static::$model;
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('therapist')) {
            $query->where('user_id', $user->id); // Only their own
        } elseif ($user->hasRole('clinic_manager')) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }
        return $query->count();
    }

    // Global query scoping: Ensures therapists only see their own holidays in ALL pages (list, view, edit)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('therapist')) {
            $query->where('user_id', $user->id); // Only their own
        } elseif ($user->hasRole('clinic_manager')) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }
        // Superadmin sees all

        return $query;
    }

    public static function validateLeaveLimit(array $data): void
    {
        $userId = $data['user_id'] ?? auth()->id();
        $type = $data['type']?->value ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        if ($userId && $type && $startDate && $endDate) {
            $user = User::find($userId);
            if (! $user) {
                Notification::make()
                    ->title('User not found')
                    ->body("Invalid user selected.")
                    ->danger()
                    ->send();
            }

            $days = (new DateTime($endDate))->diff(new DateTime($startDate))->days + 1;
            $remaining = $user->remainingLeaveDays($type, date('Y', strtotime($startDate)));

            if ($days > $remaining) {
                // throw ValidationException::withMessages([
                //     'type' => "You requested {$days} days, but only {$remaining} {$type} leave days remain this year.",
                // ]);
                Notification::make()
                    ->title('Leave Limit Exceeded')
                    ->body("You only have {$remaining} {$type} days remaining.")
                    ->danger()
                    ->persistent()
                    // ->actions([
                    //     Action::make('subscribe')
                    //         ->button()
                    //         ->url(route('subscribe'), shouldOpenInNewTab: true),
                    // ])
                    ->send();
                throw new Halt("Leave limit exceeded — form not saved.");
            }
        }
    }
}
