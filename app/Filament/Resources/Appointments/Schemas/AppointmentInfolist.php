<?php

namespace App\Filament\Resources\Appointments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

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
                        TextEntry::make('type'),
                        TextEntry::make('clinic.name')->label('Clinic Name'),
                        TextEntry::make('client.first_name')->label('Client Name'),
                        TextEntry::make('therapist.first_name')->label('therapist Name'),

                        TextEntry::make('appointment_datetime')->dateTime('d M Y, h:i A')->badge()->color('warning'),
                        TextEntry::make('duration')->placeholder('N/A'),
                        TextEntry::make('status')->placeholder('N/A'),
                        IconEntry::make('created_by.first_name')->label('Created By')->placeholder('N/A'),
                        TextEntry::make('notes')->placeholder('N/A')->columnSpanFull(),

                    ])
                    ->columns(3),

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
                    ->description('Timestamps for creation, update, deletion and billed.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')->label('Created At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('updated_at')->label('Updated At')->dateTime('d M Y, h:i A'),
                        TextEntry::make('deleted_at')->label('Deleted At')->dateTime('d M Y, h:i A')->placeholder('Not deleted'),
                        TextEntry::make('billed_at')->label('Billed At')->dateTime('d M Y, h:i A')->placeholder('Not Billed'),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}
