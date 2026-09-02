<?php

declare(strict_types=1);

namespace App\Filament\Resources\CallProviderAgents\Schemas;

use App\Enums\Call\CallProvider;
use App\Models\CallProviderAgent;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Call\PhoneNumberNormalizer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CallProviderAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Provider identity')
                ->description('How the telephony system refers to this person. Usually filled in automatically the first time they appear on a call.')
                // One column. Two put a searchable Select in a half-width
                // box inside a modal, where the clinic list wrapped onto three
                // lines and the dropdown covered the field it belonged to.
                ->columns(1)
                ->schema([
                    Select::make('provider')
                        ->options(CallProvider::options())
                        ->required()
                        ->native(false)
                        ->placeholder('Choose a provider'),

                    TextInput::make('provider_employee_name')
                        ->label('Name in the provider app')
                        ->maxLength(255)
                        // Named as it appears in the provider app, which is
                        // often not how the CRM spells the same person.
                        ->placeholder('e.g. Priya R'),

                    TextInput::make('provider_employee_number')
                        ->label('Agent number')
                        ->tel()
                        ->maxLength(32)
                        ->placeholder('e.g. +919000000001')
                        // The stored match key is derived from this, so editing
                        // the number by hand without updating the key would
                        // leave a mapping that never matches anything.
                        ->helperText('Changing this also updates the digits calls are matched on.')
                        ->afterStateUpdated(function (?string $state, callable $set): void {
                            $set('provider_employee_key', app(PhoneNumberNormalizer::class)->matchKey($state));
                        })
                        ->live(onBlur: true),

                    TextInput::make('provider_employee_code')
                        ->label('Employee code')
                        ->maxLength(100)
                        ->placeholder('e.g. EMP-01')
                        ->helperText('Callyzer emp_code, where one is set.'),

                    TextInput::make('provider_employee_key')
                        ->label('Match key')
                        ->maxLength(20)
                        ->disabled()
                        ->dehydrated()
                        // Explains the empty state of a field nobody can type
                        // into: it fills itself in from the number above.
                        ->placeholder('Filled in from the agent number')
                        ->helperText('The last digits of the number, used to recognise this agent on incoming calls.'),
                ]),

            Section::make('CRM user')
                ->description('Who this actually is. Until this is set, their calls are recorded but attributed to nobody.')
                // One column. Two put a searchable Select in a half-width
                // box inside a modal, where the clinic list wrapped onto three
                // lines and the dropdown covered the field it belonged to.
                ->columns(1)
                ->schema([
                    // Clinic first, because it now narrows the list below it.
                    // A dependency reads better when you meet it before the
                    // field it governs, rather than having an answer you
                    // already gave re-filtered underneath you.
                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->live()
                        ->options(fn (?CallProviderAgent $record): array => static::clinicOptions($record))
                        ->searchable()
                        // States the fallback rather than saying "Select a
                        // clinic", so leaving it empty is visibly a choice.
                        ->placeholder("Use the staff member's own clinic")
                        // The old wording said this was "only needed when the
                        // staff member has no clinic", which read as though a
                        // staff member were required. It is the one field that
                        // works on its own: an agent who dials for a branch
                        // without holding a CRM login is filed correctly by
                        // setting this and nothing else.
                        ->helperText('Files this agent’s calls under a clinic. Works on its own — a staff member is not required.'),

                    Select::make('user_id')
                        ->label('Staff member')
                        ->options(fn (Get $get, ?CallProviderAgent $record): array => static::staffOptions(
                            $get('clinic_id') === null ? null : (int) $get('clinic_id'),
                            $record?->user_id,
                        ))
                        ->searchable()
                        ->placeholder('Not mapped yet')
                        ->helperText(fn (Get $get): string => $get('clinic_id') === null
                            ? 'Everyone. Choose a clinic above to narrow this list.'
                            : 'Staff at the chosen clinic, plus anyone with no clinic of their own.'),

                    Toggle::make('active')
                        ->label('Use for new calls')
                        ->default(true)
                        ->helperText('Switching this off stops the mapping matching new calls. The calls it already explained keep their agent.'),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('e.g. Shared reception handset — do not map to one person.'),
                ]),
        ]);
    }

    /**
     * The clinics an agent may be filed under.
     *
     * Active ones only: a closed or unused branch is not somewhere to put
     * tomorrow's calls, and offering it invites a mistake nobody notices for
     * weeks - the calls simply stop appearing where the staff are looking.
     *
     * The exception is a clinic deactivated after this mapping was made. It
     * stays on the row that points at it, because hiding it would render an
     * empty field, and saving that form would quietly unfile the agent.
     *
     * @return array<int, string>
     */
    public static function clinicOptions(?CallProviderAgent $record = null): array
    {
        $clinics = Clinic::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($record?->clinic_id !== null && ! array_key_exists($record->clinic_id, $clinics)) {
            $name = Clinic::query()->whereKey($record->clinic_id)->value('name');

            if ($name !== null) {
                $clinics[$record->clinic_id] = $name . ' (inactive)';
            }
        }

        return $clinics;
    }

    /**
     * The staff this agent identity may belong to.
     *
     * Narrowed by the chosen clinic, because picking a person out of every
     * employee in the company is the slow part of this form and the clinic is
     * nearly always known first.
     *
     * Two escapes from that filter, both deliberate. Staff with no clinic of
     * their own are always listed: they are exactly who the clinic field above
     * exists to place, so filtering them out would hide the case the feature
     * was built for. And whoever is already saved on the mapping stays listed
     * whatever their clinic, because a filter that silently drops the current
     * value turns an unrelated edit into an unmapping.
     *
     * Labels carry the clinic name. Two people called Priya in two branches are
     * otherwise the same entry twice.
     *
     * @return array<int, string>
     */
    public static function staffOptions(?int $clinicId = null, ?int $keepUserId = null): array
    {
        return User::query()
            // Staff only: mapping an agent identity to a patient would
            // attribute the clinic's outgoing calls to a customer.
            ->whereHas('roles', fn (Builder $roles): Builder => $roles
                ->where('name', '!=', config('project.roles.client')))
            ->when($clinicId !== null, fn (Builder $query): Builder => $query
                ->where(fn (Builder $scope): Builder => $scope
                    ->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id')
                    ->when($keepUserId !== null, fn (Builder $kept): Builder => $kept
                        ->orWhere('id', $keepUserId))))
            ->with('clinic')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->clinic?->name !== null
                    ? $user->name . ' — ' . $user->clinic->name
                    : $user->name,
            ])
            ->all();
    }
}
