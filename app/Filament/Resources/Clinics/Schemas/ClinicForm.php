<?php

namespace App\Filament\Resources\Clinics\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Illuminate\Http\UploadedFile;

use App\Models\Clinic;

class ClinicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Basic Information And Address Details')
                            // ->description('Location and mapping information.')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                FileUpload::make('logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('clinic-logos')
                                    ->maxSize(10240)
                                    ->preserveFilenames()
                                    ->imageEditor()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                    ->placeholder('Upload clinic logo'),
                                    // ->getUploadedFileNameForStorageUsing(
                                    //     fn (UploadedFile $file): string =>
                                    //         'photo_' . time() . '_' . $file->getClientOriginalName()
                                    // )
                                Grid::make(2)->schema([
                                    TextInput::make('name')->required()->maxLength(255)->placeholder('Enter clinic name'),
                                    Select::make('manager_id')
                                        ->relationship('managers', 'first_name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select clinic manager'),
                                ]),
                                Textarea::make('description')->rows(4)->placeholder('Describe the clinic services and specialties'),

                            ])
                            ->collapsible(),

                        Section::make('Contact Information')
                            // ->description('Ways to reach the clinic.')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('phone')->tel()->maxLength(20)->placeholder('Enter primary phone number'),
                                    TextInput::make('email')->email()->maxLength(255)->placeholder('Enter primary email address'),
                                    TextInput::make('website')->url()->maxLength(255)->placeholder('Enter website URL'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('address_line1')->required()->maxLength(255)->placeholder('Enter first line of address'),
                                    TextInput::make('address_line2')->maxLength(255)->placeholder('Enter second line of address (optional)'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('pincode')->required()->maxLength(10)->placeholder('Enter pincode'),
                                    TextInput::make('city')->required()->maxLength(100)->placeholder('Enter city'),
                                    TextInput::make('google_map_link')->required()->url()->placeholder('Enter Google Maps embed link'),
                                ]),
                            ])
                            // ->collapsed()
                            ->collapsible(),

                        Section::make('Financial Details')
                            // ->description('GST and revenue sharing information.')
                            ->icon('heroicon-o-currency-rupee')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('gst_number')->required()->maxLength(15)->placeholder('Enter GST number'),
                                    TextInput::make('first_sale_share')
                                        ->numeric()
                                        ->suffix('%')
                                        ->default(0.00)
                                        ->placeholder('First sale share percentage'),
                                    TextInput::make('sale_share')
                                        ->numeric()
                                        ->suffix('%')
                                        ->default(0.00)
                                        ->placeholder('Subsequent sale share percentage'),
                                ])

                            ])
                            // ->collapsed()
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => fn (?Clinic $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Clinic created date')
                            ->state(fn (Clinic $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn (Clinic $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Clinic $record) => $record === null),
            ])
            ->columns(3);
    }
}
