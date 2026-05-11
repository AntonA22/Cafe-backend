<?php

namespace App\Http\Controllers;

use App\Models\CakeDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CakeDesignController extends Controller
{
    public function index(Request $request)
    {
        $designs = CakeDesign::query()
            ->where('available', true)
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

    public function image(string $slug)
    {
        $design = CakeDesign::query()
            ->where('slug', $slug)
            ->where('available', true)
            ->firstOrFail();

        $firstPhoto = $this->rawPhotoUrls($design)[0] ?? null;
        if ($firstPhoto) {
            return redirect()->away($this->proxiedImageUrl($firstPhoto));
        }

        if (!$design->image_path) {
            abort(404);
        }

        $path = storage_path('app/' . ltrim($design->image_path, '/'));
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => File::mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function toResponse(CakeDesign $design, Request $request): array
    {
        $weightTitle = $this->formatWeightTitle($design->weight_grams);
        $photoURLs = $this->displayPhotoUrls($design, $request);
        $imageURL = $photoURLs[0] ?? $this->displayLegacyImageUrl($design, $request);

        return [
            'id' => $design->slug,
            'name' => $design->name,
            'subtitle' => $design->subtitle,
            'imageName' => 'cake_text_' . str_replace('-', '_', $design->slug),
            'imageURLString' => $imageURL,
            'photos' => $photoURLs,
            'galleryImageURLStrings' => $photoURLs,
            'filling' => $design->filling,
            'accent' => $design->accent,
            'composition' => $design->composition,
            'storage' => $design->storage,
            'kcalPer100g' => $design->calories_per_100g ?? 0,
            'pricePerKg' => (int) round($design->price * 1000 / max($design->weight_grams, 1)),
            'recommendedText' => $design->recommended_text ?? '',
            'availableWeights' => [
                [
                    'title' => $weightTitle,
                    'grams' => $design->weight_grams,
                ],
            ],
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

    private function displayPhotoUrls(CakeDesign $design, Request $request): array
    {
        return array_map(
            fn (string $photo) => $this->proxiedImageUrl($photo, $request),
            $this->rawPhotoUrls($design)
        );
    }

    private function displayLegacyImageUrl(CakeDesign $design, Request $request): ?string
    {
        if ($design->image_path) {
            return $request->getSchemeAndHttpHost() . '/api/cake-designs/' . $design->slug . '/image';
        }

        return null;
    }

    private function proxiedImageUrl(string $url, ?Request $request = null): string
    {
        $host = $request?->getSchemeAndHttpHost() ?? request()->getSchemeAndHttpHost();
        return $host . '/api/image-proxy?url=' . rawurlencode($url);
    }

    private function formatWeightTitle(int $grams): string
    {
        if ($grams % 1000 === 0) {
            return ($grams / 1000) . ' кг';
        }

        return rtrim(rtrim(number_format($grams / 1000, 1, '.', ''), '0'), '.') . ' кг';
    }
}
