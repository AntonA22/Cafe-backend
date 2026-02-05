<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        return Address::where('user_id', $userId)
            ->orderByDesc('is_default')   // ✅ default сверху
            ->orderBy('created_at')       // ✅ дальше стабильно
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'base_address' => 'required|string',
            'entrance' => 'nullable|string',
            'intercom' => 'nullable|string',
            'floor' => 'nullable|string',
            'flat' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $data['user_id'] = $request->user()->id;

        $address = Address::create($data);

       return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $address = Address::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $address->update($request->all());

        return response()->json($address);
    }

    public function destroy(Request $request, $id)
    {
        Address::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return response()->noContent();
    }

    public function setDefault(Request $request, $id)
    {
        $userId = $request->user()->id;

        // 1) Сбрасываем у всех адресов пользователя default = false
        Address::where('user_id', $userId)
            ->update(['is_default' => false]);

        // 2) Ставим нужному адресу default = true
        $address = Address::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $address->is_default = true;
        $address->save();

        return response()->json($address);
    }
}
