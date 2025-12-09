<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request) {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Registrasi Berhasil, silahkan Verifikasi email Anda',
            'user' => $user
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login($request->validated());

            if (!$result) {
                return response()->json(['message' => 'Email atau password salah'], 401);
            }

            return response()->json($result);

        } catch (\Exception $e) {
             $code = $e->getCode() ?: 500;
             return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function refresh()
    {
        try {
            $newToken = auth()->refresh();
            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token invalid or expired'], 401);
        }
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::factory()->getTTL() * 60,
            'user' => Auth::user()
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Anda telah logout!']);
    }

    public function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email'
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __($status)
        ]);
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        return response()->json([
            'message' => __($status)
        ]);
    }

    public function verifyEmail(Request $request) {
        $request->validate([
            'token' => 'required'
        ]);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Token verifikasi tidak valid!'
            ], 400);
        }

        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return response()->json([
            'message' => 'Email berhasil diverifikasi!'
        ]);
    }

    public function resendVerification(Request $request) {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan!'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email sudah terverifikasi!'
            ]);
        }

        $user->email_verification_token = Str::random(60);
        $user->save();

        return response()->json(['message' => 'Link Verifikasi dikirim ulang']);
    }
}
