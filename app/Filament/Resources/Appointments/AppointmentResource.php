<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Filament\Resources\Appointments\Schemas\AppointmentInfolist;

use App\Models\Appointment;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    // protected static ?string $recordTitleAttribute = 'appointment_datetime';

    protected static ?string $navigationBadgeTooltip = 'The number of confirmed appointments created this month';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return AppointmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
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
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        // $modelClass = static::$model;
        // return (string) $modelClass::whereMonth('created_at', now()->month)->where('status', 'confirmed')->count();

        // $query = static::$model;
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('therapist')) {
            $query->where('therapist_id', $user->id); // Only their own
        } elseif ($user->hasRole('clinic_manager')) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }
        return $query->whereMonth('created_at', now()->month)
            ->where('status', 'confirmed')
            ->count();
    }

    // Global query scoping: Ensures therapists only see their own appointment in ALL pages (list, view, edit)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('therapist')) {
            $query->where('therapist_id', $user->id); // Only their own
        } elseif ($user->hasRole('clinic_manager')) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AppointmentInfolist::configure($schema);
    }
}
