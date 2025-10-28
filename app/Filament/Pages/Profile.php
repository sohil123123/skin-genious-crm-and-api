<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class Profile extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.profile';
    protected static ?string $title = 'My Profile';
    protected static bool $shouldRegisterNavigation = false;

    public ?User $user = null;

     public array $data = [];

    // 👇 integer index (1 = View, 2 = Edit)
    public int $activeTab = 1;

    public function mount(): void
    {
        $this->user = auth()->user();

        // Prefill auth user data
        $this->data = [
            'name'  => $this->user->name,
            'email' => $this->user->email,
        ];
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('ProfileTabs')
                    ->activeTab(fn () => $this->activeTab)
                    ->tabs([
                        // 🔹 VIEW TAB
                        Tab::make('View Profile')
                            ->schema([
                                Placeholder::make('name_view')->label('Name')->content(fn () => $this->user->name),
                                Placeholder::make('email_view')->label('Email')->content(fn () => $this->user->email),
                                Placeholder::make('role_view')->label('Role')->content(fn () => $this->user->role ?? 'N/A'),
                            ]),

                        // 🔹 EDIT TAB
                        Tab::make('Edit Profile')
                            ->schema([
                                TextInput::make('name')->label('Name')->required(),
                                TextInput::make('email')->label('Email')->email()->required(),
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(fn ($state): bool => filled($state)),
                            ]),
                    ]),
            ])
            ->statePath('data')
            ->model($this->user);
    }


    /**  👇  This is where we define visible footer actions */
    // protected function getFooterActions(): array
    // {
    //     return [
    //         Action::make('save')
    //             ->label('Save Changes')
    //             ->color('primary')
    //             ->action('submit')
    //             ->visible(fn () => $this->activeTab === 1), // show only on Edit tab
    //     ];
    // }

    public function submit(): void
    {
        $data = $this->data;

        $this->user->fill($data);

        if (!empty($data['password'])) {
            $this->user->password = bcrypt($data['password']);
        }

        $this->user->save();

        $this->activeTab = 0; // switch back to View

        Notification::make()
            ->title('Profile updated successfully!')
            ->success()
            ->body('Your updated profile details have been saved.')
            ->send();
    }
}
