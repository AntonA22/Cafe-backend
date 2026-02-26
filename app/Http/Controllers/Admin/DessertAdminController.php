<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dessert;
use Illuminate\Http\Request;

class DessertAdminController extends Controller
{
    /**
     * GET /admin/products/{id}
     */
    public function show($id)
    {
        $dessert = Dessert::find($id);

        if (!$dessert) {
            return response()->json([
                'success' => false,
                'error' => 'Product not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => $dessert,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * PUT /admin/products/{id}
     */
    public function update(Request $request, $id)
    {
        $dessert = Dessert::find($id);

        if (!$dessert) {
            return response()->json([
                'success' => false,
                'error' => 'Product not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        // 1) Сначала валидируем всё, кроме photos (его обработаем отдельно)
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',

            'price' => 'sometimes|numeric|min:0',
            'available' => 'sometimes|boolean',
            'weight' => 'sometimes|numeric|min:0',

            'calories' => 'sometimes|integer|min:0',
            'proteins' => 'sometimes|numeric|min:0',
            'fats' => 'sometimes|numeric|min:0',
            'carbohydrates' => 'sometimes|numeric|min:0',

            // photos может быть чем угодно (null/string/array) — нормализуем ниже
            'photos' => 'sometimes|nullable',
        ]);

        // 2) Нормализация photos
        if ($request->has('photos')) {
            $photos = $request->input('photos');

            if ($photos === null || $photos === '') {
                // фоток нет — сохраняем пустой массив
                $validated['photos'] = [];
            } elseif (is_string($photos)) {
                // строка может быть JSON-массивом, или одним URL
                $decoded = json_decode($photos, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $validated['photos'] = $decoded;
                } else {
                    $validated['photos'] = [$photos];
                }
            } elseif (is_array($photos)) {
                $validated['photos'] = $photos;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid photos format',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            // 3) Чистим массив: оставляем только непустые строки и ограничим длину
            $validated['photos'] = array_values(array_filter($validated['photos'], function ($v) {
                return is_string($v) && trim($v) !== '';
            }));

            foreach ($validated['photos'] as $p) {
                if (mb_strlen($p) > 2048) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Each photo must be a string up to 2048 chars',
                    ], 422, [], JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // 4) Сохраняем
        $dessert->fill($validated);
        $dessert->save();

        return response()->json([
            'success' => true,
            'data' => $dessert->fresh(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}