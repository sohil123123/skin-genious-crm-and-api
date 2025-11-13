<?php

namespace App\Filament\Resources\Clinics\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use App\Models\Clinic;
use Filament\Infolists;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClinicInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->description('Core details about the clinic.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('manager.name')
                            ->label('Manager')
                            ->placeholder('N/A'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('name')
                            ->label('Name'),
                        ImageEntry::make('logo')
                            ->label('Logo')
                            ->disk('public')
                            ->circular()
                            ->placeholder('No logo uploaded'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('description')
                            ->label('Description')
                            ->html()
                            ->placeholder('N/A')
                            ->columnSpan('full')
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Address Details')
                    ->description('Location information.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('address_line1')
                            ->label('Address Line 1'),
                        TextEntry::make('address_line2')
                            ->label('Address Line 2')
                            ->placeholder('N/A'),
                        TextEntry::make('pincode')
                            ->label('Pincode'),
                        TextEntry::make('city')
                            ->label('City'),
                        TextEntry::make('google_map_link')
                            ->label('Google Map Link')
                            ->url(fn (Clinic $record): ?string => $record->google_map_link)
                            ->openUrlInNewTab()
                            ->placeholder('N/A'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Contact Information')
                    ->description('Ways to reach the clinic.')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        TextEntry::make('phone')
                            ->label('Phone')
                            ->placeholder('N/A'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('N/A'),
                        TextEntry::make('website')
                            ->label('Website')
                            ->url(fn (Clinic $record): ?string => $record->website)
                            ->openUrlInNewTab()
                            ->placeholder('N/A'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Financial Details')
                    ->description('GST and revenue sharing information.')
                    ->icon('heroicon-o-currency-rupee')
                    ->schema([
                        TextEntry::make('gst_number')
                            ->label('GST Number')
                            ->placeholder('N/A'),
                        TextEntry::make('first_sale_share')
                            ->label('First Sale Share')
                            ->suffix('%'),
                        TextEntry::make('sale_share')
                            ->label('Sale Share')
                            ->suffix('%'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Record Information')
                    ->description('Timestamps for creation, update, and deletion.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y, h:i A'),
                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y, h:i A'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('d M Y, h:i A')
                            ->placeholder('Not deleted'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}
