<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Optional overrides for a Page that has already registered itself.
 *
 * Nothing on this form needs filling in for leads to work. The Page id and name
 * come from Meta and are shown read-only; the only editable values are the two
 * genuine decisions a human might want to make — which clinic this Page's leads
 * belong to, and whether to keep accepting them at all.
 *
 * The access token field is gone: one token on the settings screen now serves
 * every Page, which is what removed the per-Page setup in the first place.
 */
class MetaPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->description('Discovered automatically from Meta. These values come from the webhook and cannot be edited.')
                ->icon('heroicon-o-flag')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('page_id')
                        ->label('Page ID')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('page_name')
                        ->label('Page name')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Resolved when the next lead arrives'),
                ]),

            Section::make('Routing')
                ->description('The only two settings this Page needs, and both have working defaults.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name', fn ($query) => $query->orderBy('name'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Use the default clinic')
                        ->helperText('Leave empty to file this Page\'s leads against the default clinic set on Meta Lead Settings.'),

                    Toggle::make('is_active')
                        ->label('Accept leads')
                        ->default(true)
                        ->helperText('Turn off to stop accepting new leads from this Page. Leads already imported are kept, and the Page will not be re-added automatically.'),
                ]),
        ]);
    }
}
