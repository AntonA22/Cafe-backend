<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateMeRequest;
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

        $user->password = Hash::make($request->new_password);
        $user->save();

        // часто правильно: разлогинить все устройства
        $user->tokens()->delete();

        return response()->noContent();
    }
}
