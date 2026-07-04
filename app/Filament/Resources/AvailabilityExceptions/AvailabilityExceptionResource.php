<?php

namespace App\Filament\Resources\AvailabilityExceptions;

use App\Filament\Resources\AvailabilityExceptions\Pages\CreateAvailabilityException;
use App\Filament\Resources\AvailabilityExceptions\Pages\CreateAvailabilityExceptionEntry;
use App\Filament\Resources\AvailabilityExceptions\Pages\CreateAvailabilityExceptionWizard;
use App\Filament\Resources\AvailabilityExceptions\Pages\CreateAvailabilityExceptionSimple;
use App\Filament\Resources\AvailabilityExceptions\Pages\EditAvailabilityException;
use App\Filament\Resources\AvailabilityExceptions\Pages\ListAvailabilityExceptions;
use App\Filament\Resources\AvailabilityExceptions\Schemas\AvailabilityExceptionForm;
use App\Filament\Resources\AvailabilityExceptions\Tables\AvailabilityExceptionsTable;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use DateTime;

use Illuminate\Database\Eloquent\Builder;

use App\Models\User;
use App\Models\Clinic;
use App\Models\AvailabilityException;

class AvailabilityExceptionResource extends Resource
{
    protected static ?string $model = AvailabilityException::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'User Scheduling & Holidays';

    // protected static ?string $recordTitleAttribute = 'type';

    protected static ?string $navigationLabel = 'Holidays';

    protected static ?string $breadcrumb = 'Holidays';

    protected static ?string $modelLabel = 'Holidays';

    protected static ?string $pluralModelLabel = 'Holidays';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return AvailabilityExceptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvailabilityExceptionsTable::configure($table);
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
            'index' => ListAvailabilityExceptions::route('/'),
            // 'create' => CreateAvailabilityException::route('/create'),
            'create' => CreateAvailabilityExceptionEntry::route('/create'),
            'create-wizard' => CreateAvailabilityExceptionWizard::route('/create/wizard'),
            'create-simple' => CreateAvailabilityExceptionSimple::route('/create/simple'),
            'edit' => EditAvailabilityException::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        // $class = static::getModel();
        // get_class($model)

        if ($user->hasRole('therapist')) {
            $query->where('exceptionable_id', $user->id); // Only their own
        } elseif ($user->hasRole(['clinic_manager', 'clinic_head'])) {
            $query->where('clinic_id', $user->clinic_id); // Assuming User has 'clinic_id' field for their clinic
        }
        // Superadmin sees all

        return $query;
    }

    public static function validateLeaveLimit(array $data): void
    {
        $userId = $data['exceptionable_id'] ?? null;
        $leaveType = $data['leave_type']?->value ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        if ($userId && $leaveType && $startDate && $endDate) {
            $user = User::find($userId);
            $days = (new DateTime($endDate))->diff(new DateTime($startDate))->days + 1;
            $remaining = $user->remainingLeaveDays($leaveType, date('Y', strtotime($startDate)));

            if ($days > $remaining) {
                Notification::make()
                    ->title('Leave Limit Exceeded')
                    ->body("You only have {$remaining} {$leaveType} days remaining.")
                    ->danger()
                    // ->persistent()
                    ->send();
                throw new Halt("Leave limit exceeded — form not saved.");
            }
        }
    }
}
