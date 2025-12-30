<?php

namespace App\Filament\Resources\Appointments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Schema;
use Filament\Forms\Components\KeyValue;

use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Infolist;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->description('Core details about the appointment.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('type')->badge(),
                        TextEntry::make('clinic.name')->label('Clinic Name'),
                        TextEntry::make('client.first_name')->label('Client Name'),
                        TextEntry::make('therapist.first_name')->label('therapist Name'),

                        TextEntry::make('start_datetime')->dateTime('d M Y, h:i A')->badge()->color('warning'),
                        TextEntry::make('end_datetime')->dateTime('d M Y, h:i A')->badge()->color('warning'),
                        TextEntry::make('duration_minutes')->placeholder('N/A'),
                        TextEntry::make('status')->badge(),
                        IconEntry::make('created_by.first_name')->label('Created By')->placeholder('N/A'),
                        IconEntry::make('updated_by.first_name')->label('Updated By')->placeholder('N/A'),
                        TextEntry::make('is_emergency')
                            ->label('Emergency Override')
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'danger' : 'gray')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('notes')->placeholder('N/A')->columnSpanFull(),

                    ])
                    ->columns(3),

                Section::make('Emergency Reason')
                    ->description('Emergency reason details')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn ($record) => ! empty($record->is_emergency))
                    ->schema([
                        TextEntry::make('emergency_reason.capacity')->label('Available Capacity')->numeric(),
                        TextEntry::make('emergency_reason.confirmed')->label('Confirmed Cases')->numeric(),
                        TextEntry::make('emergency_reason.violations')->label('Violations')->badge()->listWithLineBreaks()->color('danger'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Treatment Session Details')
                    ->description('assessment id and treatment session title.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('assessment.id')->label('Assessment Id')->placeholder('N/A'),
                        TextEntry::make('treatmentSession.title')->label('Treatment Session Title')->placeholder('N/A'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->type->value == 'treatment')
                    ->collapsible(),

                Section::make('Record Information')
                    ->description('Timestamps for creation, update, deletion.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')->label('Created At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('updated_at')->label('Updated At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('deleted_at')->label('Deleted At')->dateTime('d M Y, h:i A')->placeholder('Not deleted'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}
