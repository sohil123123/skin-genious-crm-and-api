<?php

namespace App\Filament\Resources\WhatsAppTemplates\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class WhatsAppTemplateForm
{
    /**
     * Supported WhatsApp template languages.
     */
    protected static function getLanguageOptions(): array
    {
        return [
            'en_US' => 'English (US)',
            'en_GB' => 'English (UK)',
            'hi' => 'Hindi',
            'mr' => 'Marathi',
            'gu' => 'Gujarati',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'kn' => 'Kannada',
            'ml' => 'Malayalam',
            'bn' => 'Bengali',
            'pa' => 'Punjabi',
            'ur' => 'Urdu',
            'ar' => 'Arabic',
            'es' => 'Spanish',
            'pt_BR' => 'Portuguese (BR)',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh_CN' => 'Chinese (Simplified)',
            'zh_TW' => 'Chinese (Traditional)',
            'ru' => 'Russian',
            'tr' => 'Turkish',
            'nl' => 'Dutch',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Left Column (spans 2 on large screens)
                Group::make()
                    ->schema([
                        /*
                        |--------------------------------------------------------------
                        | Section 1: Template Name & Language
                        |--------------------------------------------------------------
                        */
                        Section::make('Template Name and Language')
                            ->description('Name your template and select the language.')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')
                                        ->label('Template Name')
                                        ->required()
                                        ->maxLength(512)
                                        ->rules(['regex:/^[a-z0-9_]+$/'])
                                        ->helperText('Only lowercase letters, numbers, and underscores. E.g. order_confirmation')
                                        ->placeholder('e.g. appointment_reminder')
                                        ->unique(ignoreRecord: true),

                                    Select::make('language')
                                        ->label('Language')
                                        ->options(self::getLanguageOptions())
                                        ->required()
                                        ->default('en_US')
                                        ->searchable(),

                                    Select::make('category')
                                        ->label('Category')
                                        ->options([
                                            'MARKETING' => 'Marketing',
                                            'UTILITY' => 'Utility',
                                            'AUTHENTICATION' => 'Authentication',
                                        ])
                                        ->required()
                                        ->default('UTILITY')
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            if ($state === 'AUTHENTICATION') {
                                                $authText = "*{{1}}* is your verification code. For your security, do not share this code.";
                                                $set('body_text', $authText);
                                                $set('variable_type', 'number');
                                                $set('header_type', 'none');
                                                $set('footer_text', 'Expires in 10 minutes.');
                                                self::updateVariableSamples($get, $set, $authText);
                                            }
                                        })
                                        ->helperText(
                                            fn(Get $get): string =>
                                            $get('category') === 'AUTHENTICATION'
                                            ? '🔒 Authentication category locks text to Meta standard format and auto-attaches Copy Code button.'
                                            : 'Select UTILITY for custom text templates or custom verification messages.'
                                        ),
                                ]),
                            ]),

                        /*
                        |--------------------------------------------------------------
                        | Section 3: Variable Samples
                        |--------------------------------------------------------------
                        */
                        Section::make('Variable Samples')
                            ->description('Include samples of all variables in your message to help Meta review your template. Do not include any customer information.')
                            ->icon('heroicon-o-variable')
                            ->schema([
                                Repeater::make('variable_samples')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('key')
                                            ->label('Variable')
                                            ->disabled()
                                            ->dehydrated(),

                                        TextInput::make('value')
                                            ->label('Sample Value')
                                            ->required()
                                            ->placeholder('e.g. John'),
                                    ])
                                    ->columns(2)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn(Get $get): bool => self::hasVariables($get('body_text') ?? '', $get('variable_type') ?? 'number')),
                    ])
                    ->columnSpan(['lg' => 1]),

                // Right Column (spans 1 on large screens)
                Group::make()
                    ->schema([
                        /*
                        |--------------------------------------------------------------
                        | Section 2: Content
                        |--------------------------------------------------------------
                        */
                        Section::make('Content')
                            ->description('Add a header, body, and footer for your template. Cloud API hosted by Meta will review the template.')
                            ->icon('heroicon-o-document-text')
                            ->schema([

                                // Type of variable
                                Select::make('variable_type')
                                    ->label('Type of variable')
                                    ->options([
                                        'number' => 'Number (e.g. {{1}})',
                                        'name' => 'Name (e.g. {{user_name}})',
                                    ])
                                    ->default('number')
                                    ->live()
                                    ->disabled(fn(Get $get): bool => $get('category') === 'AUTHENTICATION')
                                    ->dehydrated()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::updateVariableSamples($get, $set, $get('body_text'));
                                    })
                                    ->columnSpanFull(),

                                // Header
                                Select::make('header_type')
                                    ->label('Header · Optional')
                                    ->options([
                                        'none' => 'None',
                                        'text' => 'Text',
                                    ])
                                    ->default('none')
                                    ->live()
                                    ->disabled(fn(Get $get): bool => $get('category') === 'AUTHENTICATION')
                                    ->dehydrated()
                                    ->columnSpanFull(),

                                TextInput::make('header_content')
                                    ->label('Header Text')
                                    ->maxLength(60)
                                    ->placeholder('Enter header text')
                                    ->helperText('Max 60 characters')
                                    ->visible(fn(Get $get): bool => $get('header_type') === 'text' && $get('category') !== 'AUTHENTICATION')
                                    ->columnSpanFull(),

                                // Body
                                Textarea::make('body_text')
                                    ->label('Body')
                                    ->required()
                                    ->maxLength(1024)
                                    ->rows(5)
                                    ->disabled(fn(Get $get): bool => $get('category') === 'AUTHENTICATION')
                                    ->dehydrated()
                                    ->placeholder(
                                        fn(Get $get): string =>
                                        ($get('variable_type') === 'name')
                                        ? "Hello {{user_name}},\n\nYour appointment is confirmed for {{appointment_date}}.\n\nThank you!"
                                        : "Hello {{1}},\n\nYour appointment is confirmed for {{2}}.\n\nThank you!"
                                    )
                                    ->helperText(
                                        fn(Get $get): string =>
                                        ($get('category') === 'AUTHENTICATION')
                                        ? '🔒 Locked to Meta standard security text format. Custom text is forbidden by Meta for Authentication templates.'
                                        : (($get('variable_type') === 'name')
                                            ? 'Use {{variable_name}} for variables. Max 1024 characters.'
                                            : 'Use {{1}}, {{2}}, etc. for variables. Max 1024 characters.')
                                    )
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        self::updateVariableSamples($get, $set, $state);
                                    })
                                    ->columnSpanFull(),

                                // Footer
                                TextInput::make('footer_text')
                                    ->label('Footer · Optional')
                                    ->maxLength(60)
                                    ->disabled(fn(Get $get): bool => $get('category') === 'AUTHENTICATION')
                                    ->dehydrated()
                                    ->placeholder('Enter footer text')
                                    ->helperText('Max 60 characters')
                                    ->columnSpanFull(),
                            ]),

                        /*
                        |--------------------------------------------------------------
                        | Section 4: Buttons (Optional or Automatic OTP for Authentication)
                        |--------------------------------------------------------------
                        */
                        Section::make('Buttons')
                            ->description(
                                fn(Get $get): string =>
                                $get('category') === 'AUTHENTICATION'
                                ? '✨ Meta automatically attaches an OTP Copy Code button for Authentication templates.'
                                : 'Create buttons that let customers respond to your message or take action.'
                            )
                            ->icon('heroicon-o-cursor-arrow-ripple')
                            ->schema([
                                TextInput::make('auth_button_preview')
                                    ->label('Automatic Button')
                                    ->default('📋 Copy Code (Meta OTP Button)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn(Get $get): bool => $get('category') === 'AUTHENTICATION')
                                    ->columnSpanFull(),

                                Repeater::make('buttons')
                                    ->label('')
                                    ->visible(fn(Get $get): bool => $get('category') !== 'AUTHENTICATION')
                                    ->schema([
                                        Select::make('type')
                                            ->label('Button Type')
                                            ->options([
                                                'QUICK_REPLY' => 'Quick Reply',
                                                'URL' => 'Visit Website',
                                                'PHONE_NUMBER' => 'Call Phone Number',
                                            ])
                                            ->required()
                                            ->default('QUICK_REPLY')
                                            ->live(),

                                        TextInput::make('text')
                                            ->label('Button Text')
                                            ->required()
                                            ->maxLength(25)
                                            ->placeholder('e.g. View Details'),

                                        TextInput::make('url')
                                            ->label('Website URL')
                                            ->maxLength(2000)
                                            ->placeholder('https://example.com/{{1}}')
                                            ->visible(fn(Get $get): bool => $get('type') === 'URL'),

                                        TextInput::make('url_example')
                                            ->label('Sample URL')
                                            ->maxLength(2000)
                                            ->placeholder('https://example.com/order/12345')
                                            ->helperText('Provide a sample URL for Meta review')
                                            ->visible(fn(Get $get): bool => $get('type') === 'URL'),

                                        TextInput::make('phone_number')
                                            ->label('Phone Number')
                                            ->tel()
                                            ->placeholder('+919876543210')
                                            ->visible(fn(Get $get): bool => $get('type') === 'PHONE_NUMBER'),
                                    ])
                                    ->columns(2)
                                    ->maxItems(10)
                                    ->defaultItems(0)
                                    ->addActionLabel('+ Add Button')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(fn(Get $get): bool => $get('category') !== 'AUTHENTICATION'),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(2);
    }

    /**
     * Update the variable samples list dynamically based on body_text.
     */
    public static function updateVariableSamples(Get $get, Set $set, ?string $bodyText): void
    {
        if (empty($bodyText)) {
            $set('variable_samples', []);
            return;
        }

        $variableType = $get('variable_type') ?? 'number';
        $pattern = ($variableType === 'name')
            ? '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/'
            : '/\{\{(\d+)\}\}/';

        preg_match_all($pattern, $bodyText, $matches);
        $placeholders = array_unique($matches[1]);

        $currentSamples = $get('variable_samples') ?? [];
        $newSamples = [];

        // Build key-value map of current samples to preserve user input
        $existingValues = [];
        foreach ($currentSamples as $item) {
            if (isset($item['key'])) {
                $existingValues[$item['key']] = $item['value'] ?? '';
            }
        }

        foreach ($placeholders as $placeholder) {
            $newSamples[] = [
                'key' => (string) $placeholder,
                'value' => $existingValues[$placeholder] ?? '',
            ];
        }

        $set('variable_samples', $newSamples);
    }

    /**
     * Check if the body text contains any variables.
     */
    protected static function hasVariables(string $bodyText, string $variableType = 'number'): bool
    {
        $pattern = ($variableType === 'name')
            ? '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/'
            : '/\{\{(\d+)\}\}/';

        return (bool) preg_match($pattern, $bodyText);
    }
}
