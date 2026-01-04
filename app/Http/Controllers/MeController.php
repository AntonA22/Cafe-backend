<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function show()
    {
        return new UserResource(request()->user());
    }

    public function update(UpdateMeRequest $request)
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return new UserResource($user);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Wrong current password'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // часто правильно: разлогинить все устройства
        $user->tokens()->delete();

        return response()->noContent();
    }
}