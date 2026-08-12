<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Schemas;

use App\Models\MetaPage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Connect one Facebook Page.
 *
 * The Page id is the join between an incoming webhook and a clinic, so it is
 * required and immutable once saved — changing it would silently re-route
 * every future lead and orphan the sync history already recorded against it.
 */
class MetaPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->description('The Facebook Page whose lead forms feed this clinic.')
                ->icon('heroicon-o-flag')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('page_id')
                        ->label('Page ID')
                        ->required()
                        ->maxLength(64)
                        ->rule('regex:/^\d+$/')
                        ->unique(ignoreRecord: true)
                        // Immutable after creation: the sync log and every lead
                        // already filed are keyed to this Page.
                        ->disabled(fn (?MetaPage $record): bool => $record !== null)
                        ->dehydrated()
                        ->helperText('The numeric Page ID from Meta Business Suite, not the vanity URL.'),

                    TextInput::make('page_name')
                        ->label('Page name')
                        ->maxLength(255)
                        ->helperText('Filled in automatically by Test Connection.'),

                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name', fn ($query) => $query->orderBy('name'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Every lead from this Page is filed against this clinic.'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive Pages keep their history but stop accepting new webhook leads.'),
                ]),

            Section::make('Access token')
                ->description('A Page access token with the leads_retrieval permission.')
                ->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('access_token')
                        ->label('Page access token')
                        ->password()
                        ->revealable()
                        ->autocomplete(false)
                        ->maxLength(1000)
                        // Encrypted by the model cast; never shown in the table
                        // and never written to the activity log.
                        ->helperText('Stored encrypted. Without it a lead cannot be fetched from Meta. Required permissions: leads_retrieval, pages_show_list, pages_read_engagement, pages_manage_metadata.'),
                ]),
        ]);
    }
}
