<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CakeDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CakeDesignAdminController extends Controller
{
    public function index(Request $request)
    {
        $designs = CakeDesign::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CakeDesign $design) => $this->toResponse($design, $request))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $designs,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $validated = $this->validateDesign($request, true);
        $validated['slug'] = $validated['slug'] ?? $this->makeUniqueSlug($validated['name'] ?? 'cake');
        $validated = $this->syncPrimaryPhoto($validated);
        $design = CakeDesign::query()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $this->toResponse($design->fresh(), $request),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, string $design)
    {
        $cakeDesign = $this->findDesign($design);

        return response()->json([
            'success' => true,
            'data' => $this->toResponse($cakeDesign, $request),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, string $design)
    {
        $cakeDesign = $this->findDesign($design);
        $validated = $this->validateDesign($request, false, $cakeDesign);
        if (!isset($validated['slug']) && !$cakeDesign->slug && isset($validated['name'])) {
            $validated['slug'] = $this->makeUniqueSlug($validated['name']);
        }
        $validated = $this->syncPrimaryPhoto($validated);

        $cakeDesign->fill($validated);
        $cakeDesign->save();

        return response()->json([
            'success' => true,
            'data' => $this->toResponse($cakeDesign->fresh(), $request),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(string $design)
    {
        $cakeDesign = $this->findDesign($design);
        $this->deleteLocalImage($cakeDesign->image_path);
        $cakeDesign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cake design deleted',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function validateDesign(Request $request, bool $isCreate, ?CakeDesign $current = null): array
    {
        $slugRule = Rule::unique('cake_designs', 'slug');
        if ($current) {
            $slugRule = $slugRule->ignore($current->id);
        }

        return $request->validate([
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'subtitle' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string', 'max:255'],
            'filling' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string'],
            'accent' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string'],
            'composition' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string'],
            'storage' => [$isCreate ? 'required' : 'sometimes', 'nullable', 'string', 'max:255'],
            'weight_grams' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'price' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'calories_per_100g' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'recommended_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'photos' => ['sometimes', 'nullable', 'array', 'max:3'],
            'photos.*' => ['required', 'url', 'max:2048'],
            'available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function findDesign(string $identifier): CakeDesign
    {
        return CakeDesign::query()
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->firstOrFail();
    }

    private function toResponse(CakeDesign $design, Request $request): array
    {
        $rawPhotos = $this->rawPhotoUrls($design);
        $displayPhotoURLs = array_map(
            fn (string $photo) => $request->getSchemeAndHttpHost() . '/api/image-proxy?url=' . rawurlencode($photo),
            $rawPhotos
        );
        $displayImageURL = $displayPhotoURLs[0] ?? (
            $design->image_path
                ? $request->getSchemeAndHttpHost() . '/api/cake-designs/' . $design->slug . '/image'
                : null
        );

        return $design->toArray() + [
            'photos' => $rawPhotos,
            'photo_previews' => $displayPhotoURLs,
            'image_url' => $rawPhotos[0] ?? null,
            'imageURLString' => $displayImageURL,
        ];
    }

    private function rawPhotoUrls(CakeDesign $design): array
    {
        $photos = $design->photos;
        if (!is_array($photos)) {
            $photos = [];
        }

        if ($design->image_url) {
            array_unshift($photos, $design->image_url);
        }

        return array_values(array_slice(array_unique(array_filter(
            $photos,
            fn ($photo) => is_string($photo) && trim($photo) !== ''
        )), 0, 3));
    }

    private function syncPrimaryPhoto(array $validated): array
    {
        if (array_key_exists('photos', $validated)) {
            $photos = is_array($validated['photos']) ? array_values(array_slice($validated['photos'], 0, 3)) : [];
            $validated['photos'] = $photos;
            $validated['image_url'] = $photos[0] ?? null;
            return $validated;
        }

        if (array_key_exists('image_url', $validated) && $validated['image_url']) {
            $validated['photos'] = [$validated['image_url']];
        }

        return $validated;
    }

    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'cake-design';
        }

        $slug = $base;
        $suffix = 2;
        while (CakeDesign::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function deleteLocalImage(?string $path): void
    {
        if (!$path || !str_starts_with($path, 'cake-designs/')) {
            return;
        }

        $absolutePath = storage_path('app/' . ltrim($path, '/'));
        if (is_file($absolutePath)) {
            File::delete($absolutePath);
        }
    }
}
