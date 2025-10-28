<?php

namespace App\Filament\Resources\Holidays\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use Filament\Infolists;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Models\Holiday;

class HolidayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->description('Core details about the holiday.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('user.name')->label('User Name')->placeholder('N/A'),
                        TextEntry::make('clinic.name')->label('Clinic')->placeholder('N/A'),
                        TextEntry::make('start_date')->label('Start Ddate')->placeholder('N/A'),
                        TextEntry::make('end_date')->label('End Date')->placeholder('N/A'),
                        TextEntry::make('status')->label('Status')->placeholder('N/A'),
                        TextEntry::make('reason')->html()->placeholder('N/A'),
                        TextEntry::make('approver.name')->label('Approver')->placeholder('N/A'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Record Information')
                    ->description('Timestamps for creation, update, and deletion.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('Not deleted'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}
