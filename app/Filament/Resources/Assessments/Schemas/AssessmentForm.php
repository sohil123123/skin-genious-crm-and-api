<?php

namespace App\Filament\Resources\Assessments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('assessment_id')
                    ->numeric(),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('clinic_id')
                    ->required()
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('age')
                    ->numeric(),
                TextInput::make('daily_sun_exposure_hours'),
                Select::make('social_event')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->default('no')
                    ->required(),
                Select::make('upcoming_travel')
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->default('no')
                    ->required(),
                TextInput::make('medical_history'),
                TextInput::make('allergies'),
                Toggle::make('is_pregnant')
                    ->required(),
                Select::make('breastfeeding')
                    ->options(['yes' => 'Yes', 'no' => 'No']),
                TextInput::make('diagnosis'),
                TextInput::make('parameters_with_abnormal_scores'),
                Select::make('selected_plan_type')
                    ->options(['single' => 'Single', 'multiple' => 'Multiple']),
                TextInput::make('total_time'),
                TextInput::make('recommended_full_plan'),
                Select::make('status')
                    ->options([
            'in_progress' => 'In progress',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'incomplete' => 'Incomplete',
            'cancelled' => 'Cancelled',
            'overdue' => 'Overdue',
        ])
                    ->default('in_progress')
                    ->required(),
                Textarea::make('therapist_notes')
                    ->columnSpanFull(),
            ]);
    }
}
