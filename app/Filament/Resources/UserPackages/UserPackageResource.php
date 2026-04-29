<?php

namespace App\Filament\Resources\UserPackages;

use App\Filament\Resources\UserPackages\Pages\CreateUserPackage;
use App\Filament\Resources\UserPackages\Pages\EditUserPackage;
use App\Filament\Resources\UserPackages\Pages\ListUserPackages;
use App\Filament\Resources\UserPackages\Pages\ViewUserPackage;
use App\Filament\Resources\UserPackages\Schemas\UserPackageForm;
use App\Filament\Resources\UserPackages\Schemas\UserPackageInfolist;
use App\Filament\Resources\UserPackages\Tables\UserPackagesTable;
use App\Models\UserPackage;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserPackageResource extends Resource
{
    protected static ?string $model = UserPackage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Packages';

    protected static ?string $modelLabel = 'Package';

    protected static ?string $pluralModelLabel = 'Packages';

    protected static ?string $recordTitleAttribute = 'package_name';

    protected static ?int $navigationSort = 8;

    // protected static string|UnitEnum|null $navigationGroup = 'Clients';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'clinic_manager', 'receptionist', 'doctor', 'therapist']);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if (!auth()->user()->hasRole('super_admin')) {
            $query->where('clinic_id', auth()->user()->clinic_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return UserPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserPackagesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserPackageInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserPackages::route('/'),
            'create' => CreateUserPackage::route('/create'),
            'view' => ViewUserPackage::route('/{record}'),
            'edit' => EditUserPackage::route('/{record}/edit'),
        ];
    }
}
