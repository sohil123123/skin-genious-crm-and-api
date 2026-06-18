<?php

namespace App\Filament\Resources\UserWeeklySchedules;

use App\Filament\Resources\UserWeeklySchedules\Pages\CreateUserWeeklySchedule;
use App\Filament\Resources\UserWeeklySchedules\Pages\EditUserWeeklySchedule;
use App\Filament\Resources\UserWeeklySchedules\Pages\ListUserWeeklySchedules;
use App\Filament\Resources\UserWeeklySchedules\Schemas\UserWeeklyScheduleForm;
use App\Filament\Resources\UserWeeklySchedules\Tables\UserWeeklySchedulesTable;
use App\Models\UserWeeklySchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Builder;

class UserWeeklyScheduleResource extends Resource
{
    protected static ?string $model = UserWeeklySchedule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | UnitEnum | null $navigationGroup = 'User Scheduling & Holidays';

    protected static ?string $navigationLabel = 'Weekly Schedule';

    // protected static ?string $recordTitleAttribute = 'Weekly Schedule';

    protected static ?string $breadcrumb = 'Weekly Schedule';

    protected static ?string $modelLabel = 'Weekly Schedule';

    protected static ?string $pluralModelLabel = 'Weekly Schedule';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return UserWeeklyScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserWeeklySchedulesTable::configure($table);
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
            'index' => ListUserWeeklySchedules::route('/'),
            'create' => CreateUserWeeklySchedule::route('/create'),
            'edit' => EditUserWeeklySchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('therapist')) {
            $query->where('user_id', $user->id); // Only their own
        } elseif ($user->hasRole(['clinic_manager', 'clinic_head'])) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }
        // Superadmin sees all

        return $query;
    }
}
