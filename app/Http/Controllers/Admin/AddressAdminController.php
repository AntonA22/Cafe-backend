<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;

class AddressAdminController extends Controller
{
    // GET /admin/addresses/{id}
    public function show($id)
    {
        $address = Address::query()
            ->with(['user']) // если есть связь user() у Address
            ->find($id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'error' => 'Address not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => $address,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
