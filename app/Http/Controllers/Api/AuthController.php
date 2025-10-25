<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Models\User;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseApiController
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                        $fail('The value must be a valid email or mobile number.');
                    }
                }
            ],
            'password' => 'required',
            'remember_me' => 'boolean'
        ]);

        $login = $request->email;

        // Detect if it's an email or a mobile number
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $login)->first()
            : User::where('mobile', $login)->first();

        if(!$user->hasRole('user')) {
            return $this->error('Login Failed. Invalid role', [], HTTP_UNAUTHORIZED);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Login Failed. Invalid credentials', [], HTTP_UNAUTHORIZED);
        }

        // Check status, is_verified, is_active
        if ($user->is_active != 1) {
            return $this->error('Validation Error', ['Login Failed. Account not active or not verified.'], HTTP_UNAUTHORIZED);
        }

        if ($request->remember_me)
            $token = $user->createToken('auth_token', ['*'], now()->addWeek());
        else
            $token = $user->createToken('auth_token');

        $data = [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addWeek(),
            'user' => $user,
            'roles' => $user->roles,
        ];

        return $this->success('User login successfully.', $data);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('User successfully logged out', []);
    }
}
