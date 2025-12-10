<?php

namespace App\Http\Controllers;

use App\Models\Dessert;

class DessertController extends Controller
{
    public function jsonProducts()
    {
        $desserts = Dessert::all();

        return response()->json([
            "success" => true,
            "data" => $desserts
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function jsonProduct($id)
    {
        $dessert = Dessert::find($id);

        if (!$dessert) {
            return response()->json([
                "success" => false,
                "error" => "Dessert not found"
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            "success" => true,
            "data" => $dessert
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}