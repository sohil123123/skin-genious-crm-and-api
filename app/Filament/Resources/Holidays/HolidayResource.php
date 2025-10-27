<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\EditHoliday;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Filament\Resources\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Holidays\Tables\HolidaysTable;
use App\Models\Holiday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use UnitEnum;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'holiday';

    protected static string | UnitEnum | null $navigationGroup = 'Therapist Management';

    // protected static ?int $navigationSort = 1;

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
}
