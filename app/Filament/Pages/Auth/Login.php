<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Auth\Pages\Login as BaseAuthLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
// use Filament\Pages\Page;
use Filament\Notifications\Notification;

class Login extends BaseAuthLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('login')
                    ->label('Email or Phone')
                    ->required()
                    ->placeholder('Email or Phone')
                    ->autofocus(),
                $this->getPasswordFormComponent()->placeholder('Enter your password'),
                $this->getRememberFormComponent(),
            ]);
    }
    protected function getCredentialsFromFormData(array $data): array
    {
        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        return [
            $loginField => $data['login'],
            'password'  => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        Notification::make()
            ->danger()
            ->title('Login failed')
            ->body( __('filament-panels::auth/pages/login.messages.failed'))
            ->persistent()
            ->seconds(5)
            ->send();

        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);

    }
}
