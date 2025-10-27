<?php

namespace App\Filament\Resources\Users\Schemas;

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
// use Filament\Tables\Grouping\Group;
use Filament\Schemas\Components\Grid;

use App\Models\User;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Section::make('Personal Information')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('first_name')->placeholder('N/A'),
                            TextEntry::make('last_name')->placeholder('N/A'),
                            TextEntry::make('gender')->placeholder('N/A'),
                            TextEntry::make('date_of_birth')->date()->placeholder('N/A'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Contact Details')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('mobile')->placeholder('N/A'),
                            TextEntry::make('email')->label('Email address')->placeholder('N/A'),
                            TextEntry::make('occupation')->placeholder('N/A'),
                        ]),
                        Grid::make(3)->schema([
                            TextEntry::make('city')->placeholder('N/A'),
                            TextEntry::make('pincode')->placeholder('N/A'),
                            TextEntry::make('referral_code')->placeholder('N/A'),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('address_line_1')->placeholder('N/A'),
                            TextEntry::make('address_line_2')->placeholder('N/A'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Medical Background')
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('has_diabetes')
                                ->label('Has Diabetes')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_high_bp')
                                ->label('Has High Blood Pressure')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_cholesterol')
                                ->label('Has High Cholesterol')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_asthma')
                                ->label('Has Asthma')
                                ->boolean()
                                ->placeholder('N/A'),
                        ]),
                        Grid::make(4)->schema([

                            IconEntry::make('has_heart_disease')
                                ->label('Has Heart Disease')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_anaemia')
                                ->label('Has Anaemia')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_pcos')
                                ->label('Has PCOS')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('has_thyroid')
                                ->label('Has Thyroid Condition')
                                ->boolean()
                                ->placeholder('N/A'),
                        ]),
                        Grid::make(3)->schema([
                            TextEntry::make('other_diseases')
                                ->label('Other Diseases')
                                ->placeholder('No other diseases listed'),
                            TextEntry::make('current_medications')
                                ->label('Current Medications')
                                ->placeholder('No current medications listed'),
                            TextEntry::make('allergies')
                                ->label('Allergies')
                                ->placeholder('No known allergies listed'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Skin Profile')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('skin_type')->label('Skin Type')->placeholder('N/A'),
                            TextEntry::make('skin_quality')->label('Skin Quality')->placeholder('N/A'),
                            TextEntry::make('skin_improvement')->label('Skin Improvement Goal')->placeholder('N/A'),
                            TextEntry::make('facials_history')->label('Facials History')->placeholder('No facials history provided'),
                        ]),

                    ])
                    ->collapsible(),

                Section::make('Aesthetic Goals')
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('goal_less_tired')
                                ->label('Look Less Tired')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_less_angry')
                                ->label('Look Less Angry')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_less_sad')
                                ->label('Look Less Sad')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_less_saggy')
                                ->label('Look Less Saggy')
                                ->boolean()
                                ->placeholder('N/A'),
                        ]),
                        Grid::make(4)->schema([
                            IconEntry::make('goal_youthful')
                                ->label('Look More Youthful')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_attractive')
                                ->label('Look More Attractive')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_soft_features')
                                ->label('Have Softer Features')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('goal_slim_face')
                                ->label('Have Slimmer Face')
                                ->boolean()
                                ->placeholder('N/A'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Account Settings')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('how_did_you_hear')->placeholder('N/A'),
                            IconEntry::make('opt_for_loyalty')
                                ->label('Opted for Loyalty Program?')
                                ->boolean()
                                ->placeholder('N/A'),
                            IconEntry::make('is_active')->boolean()->placeholder('N/A'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Roles & Permissions')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('roles.name')->label('Roles')->badge()->placeholder('N/A'),
                            TextEntry::make('clinic.name')->label('Assigned Clinic')->placeholder('N/A'),
                        ]),
                        TextEntry::make('permissions.name')->label('Permissions')->badge()->placeholder('N/A'),
                    ]),

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
