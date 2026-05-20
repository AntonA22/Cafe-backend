<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\ForgotPasswordTemporaryPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'email' => $request->email,
            'username' => $request->username,
            'phone' => $request->phone,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('ios')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = $this->findUserForLogin($request);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // по желанию: очищать старые токены для ios
        // $user->tokens()->where('name', 'ios')->delete();

        $token = $user->createToken('ios')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function moderatorLogin(LoginRequest $request)
    {
        $user = $this->findUserForLogin($request);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (! $user->is_staff) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function moderatorMe(Request $request)
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function moderatorLogout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function logout()
    {
        request()->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->string('email')->lower()->toString();

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Почта не зарегистрирована',
            ], 404);
        }

        $temporaryPassword = Str::password(10, true, true, false, false);

        Mail::to($user->email)->send(
            new ForgotPasswordTemporaryPasswordMail($temporaryPassword)
        );

        $user->password = Hash::make($temporaryPassword);
        $user->save();

        // После сброса лучше завершить все активные сессии.
        $user->tokens()->delete();

        return $this->forgotPasswordNeutralResponse();
    }

    private function forgotPasswordNeutralResponse()
    {
        return response()->json([
            'message' => 'If an account with this email exists, a temporary password has been sent.',
        ]);
    }

    private function findUserForLogin(LoginRequest $request): ?User
    {
        $login = trim((string) $request->input('login'));
        $emailLogin = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($login)
            : null;

        // login может быть email или username
        return User::query()
            ->where(function ($query) use ($login, $emailLogin) {
                if ($emailLogin !== null) {
                    $query->whereRaw('LOWER(email) = ?', [$emailLogin]);
                }

                $query->orWhere('username', $login);
            })
            ->first();
    }
}
