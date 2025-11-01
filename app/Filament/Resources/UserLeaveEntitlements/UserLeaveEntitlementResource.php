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

class UserLeaveEntitlementResource extends Resource
{
    protected static ?string $model = UserLeaveEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $recordTitleAttribute = 'UserLeaveEntitlement';

    protected static ?int $navigationSort = 5;

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
}
