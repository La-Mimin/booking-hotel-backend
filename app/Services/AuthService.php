<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'user',
            'email_verification_token' => Str::random(60)
        ]);
    }

    public function login(array $credentials): ?array
    {
        if (! $token = auth('api')->attempt($credentials)) {
            return null;
        }

        $user = auth('api')->user();

        if (!$user->email_verified_at) {
             // In a real scenario, you might want to throw a specific exception here
             // but for now we follow the controller logic pattern which returned 403
             // We can return a special array or throw exception. 
             // Let's modify logic to throw generic exception.
             throw new \Exception('Email Belum Terverifikasi!', 403);
        }

        return [
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user
        ];
    }
}
