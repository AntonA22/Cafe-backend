<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Address::where('user_id', $user->id)->get();
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
}
