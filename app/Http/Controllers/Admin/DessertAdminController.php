<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dessert;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;

class DessertAdminController extends Controller
{
    /**
     * POST /admin/products
     */
    public function store(Request $request)
    {
        $validated = $this->validateDessert($request, true);

        $dessert = Dessert::create($validated);

        return response()->json([
            'success' => true,
            'data' => $dessert,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

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

        $validated = $this->validateDessert($request, false);

        $dessert->fill($validated);
        $dessert->save();

        return response()->json([
            'success' => true,
            'data' => $dessert->fresh(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * DELETE /admin/products/{id}
     */
    public function destroy($id)
    {
        $dessert = Dessert::find($id);

        if (!$dessert) {
            return response()->json([
                'success' => false,
                'error' => 'Product not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        // Не удаляем десерты, которые уже попали в заказы:
        // иначе теряется состав исторических заказов.
        if (OrderItem::where('dessert_id', $dessert->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Нельзя удалить товар, который уже есть в заказах. Снимите его с продажи через available=false.',
            ], 409, [], JSON_UNESCAPED_UNICODE);
        }

        $dessert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function validateDessert(Request $request, bool $isCreate): array
    {
        $validated = $request->validate([
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'category' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string', 'max:255'],
            'description' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string'],
            'composition' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string'],
            'price' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'available' => 'sometimes|boolean',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'calories' => 'sometimes|nullable|integer|min:0',
            'proteins' => 'sometimes|nullable|numeric|min:0',
            'fats' => 'sometimes|nullable|numeric|min:0',
            'carbohydrates' => 'sometimes|nullable|numeric|min:0',
            'photos' => 'sometimes|nullable',
        ]);

        if ($request->has('photos')) {
            $validated['photos'] = $this->normalizePhotos($request->input('photos'));
        }

        return $validated;
    }

    private function normalizePhotos(mixed $photos): array
    {
        if ($photos === null || $photos === '') {
            return [];
        }

        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : [$photos];
        }

        if (!is_array($photos)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'Invalid photos format',
            ], 422, [], JSON_UNESCAPED_UNICODE));
        }

        $photos = array_values(array_filter($photos, function ($value) {
            return is_string($value) && trim($value) !== '';
        }));

        foreach ($photos as $photo) {
            if (mb_strlen($photo) > 2048) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'error' => 'Each photo must be a string up to 2048 chars',
                ], 422, [], JSON_UNESCAPED_UNICODE));
            }
        }

        return $photos;
    }
}
