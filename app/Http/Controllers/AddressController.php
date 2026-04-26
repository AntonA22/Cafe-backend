<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Address;
use Illuminate\Support\Facades\DB;

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

        $shouldBecomeDefault = !Address::where('user_id', $request->user()->id)->exists();

        $address = new Address($data);
        $address->user()->associate($request->user());
        if ($shouldBecomeDefault) {
            $address->is_default = true;
        }
        $address->save();

        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $address = Address::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string',
            'base_address' => 'sometimes|required|string',
            'entrance' => 'nullable|string',
            'intercom' => 'nullable|string',
            'floor' => 'nullable|string',
            'flat' => 'nullable|string',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
        ]);

        $address->fill($validated);
        $address->save();

        return response()->json($address);
    }

    public function destroy(Request $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $address = Address::where('user_id', $request->user()->id)
                ->where('id', $id)
                ->firstOrFail();

            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                Address::where('user_id', $request->user()->id)
                    ->orderBy('created_at')
                    ->limit(1)
                    ->update(['is_default' => true]);
            }
        });

        return response()->noContent();
    }

    public function setDefault(Request $request, $id)
    {
        $userId = $request->user()->id;

        $address = DB::transaction(function () use ($userId, $id) {
            Address::where('user_id', $userId)
                ->update(['is_default' => false]);

            $address = Address::where('user_id', $userId)
                ->where('id', $id)
                ->firstOrFail();

            $address->is_default = true;
            $address->save();

            return $address;
        });

        return response()->json($address);
    }
}
