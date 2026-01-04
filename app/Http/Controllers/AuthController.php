<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'email'      => $request->email,
            'username'   => $request->username,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'password'   => Hash::make($request->password),
        ]);

        $token = $user->createToken('ios')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $request->input('login');

        // login может быть email или username
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // по желанию: очищать старые токены для ios
        // $user->tokens()->where('name', 'ios')->delete();

        $token = $user->createToken('ios')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    public function logout()
    {
        request()->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}