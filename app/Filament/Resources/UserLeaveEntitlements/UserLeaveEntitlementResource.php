<?php

namespace App\Filament\Resources\UserLeaveEntitlements;

use App\Filament\Resources\UserLeaveEntitlements\Pages\CreateUserLeaveEntitlement;
use App\Filament\Resources\UserLeaveEntitlements\Pages\EditUserLeaveEntitlement;
use App\Filament\Resources\UserLeaveEntitlements\Pages\ListUserLeaveEntitlements;
use App\Filament\Resources\UserLeaveEntitlements\Schemas\UserLeaveEntitlementForm;
use App\Filament\Resources\UserLeaveEntitlements\Tables\UserLeaveEntitlementsTable;
use App\Models\UserLeaveEntitlement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UserLeaveEntitlementResource extends Resource
{
    protected static ?string $model = UserLeaveEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    // protected static ?string $recordTitleAttribute = 'UserLeaveEntitlement';

    protected static string | UnitEnum | null $navigationGroup = 'User Scheduling & Holidays';

    protected static ?string $navigationLabel = 'Leave Entitlement';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserLeaveEntitlementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserLeaveEntitlementsTable::configure($table);
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
            'index' => ListUserLeaveEntitlements::route('/'),
            // 'create' => CreateUserLeaveEntitlement::route('/create'),
            'edit' => EditUserLeaveEntitlement::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->when(!auth()->user()->hasRole(config('project.roles.super_admin', 'super_admin')), function ($query) {
                $query->whereHas('user', fn ($q) => $q->where('clinic_id', auth()->user()->clinic_id));
            });
    }
}
