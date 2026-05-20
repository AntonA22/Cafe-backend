<?php

namespace App\Http\Controllers;

use App\Models\Dessert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DessertController extends Controller
{
    public function searchProducts(Request $request)
    {
        $searchName = trim((string) $request->input('query', ''));
        $query = $this->publicDessertQuery();

        if ($request->boolean('favorites')) {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Требуется авторизация',
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            $query->whereHas('favoritedBy', fn ($favorites) => $favorites->where('users.id', $user->id));
        }

        if ($searchName === '' || $searchName === '*') {
            $desserts = $query->get();
        } else {
            $terms = preg_split('/\s+/u', $searchName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $desserts = $query->get()
                ->filter(fn (Dessert $dessert) => $this->dessertMatchesSearch($dessert, $terms))
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $this->withNormalizedPhotos($desserts),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function favoriteProducts(Request $request)
    {
        $desserts = $request->user()
            ->favoriteDesserts()
            ->where('available', true)
            ->where('archived', false)
            ->orderByPivot('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->withNormalizedPhotos($desserts),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function searchFavoriteProducts(Request $request)
    {
        $request->merge(['favorites' => true]);

        return $this->searchProducts($request);
    }

    public function addFavorite(Request $request, Dessert $dessert)
    {
        if (! $dessert->available || $dessert->archived) {
            return response()->json([
                'success' => false,
                'error' => 'Dessert not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $request->user()->favoriteDesserts()->syncWithoutDetaching([$dessert->id]);

        return response()->json([
            'success' => true,
            'message' => 'Товар добавлен в избранное',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function removeFavorite(Request $request, Dessert $dessert)
    {
        $request->user()->favoriteDesserts()->detach($dessert->id);

        return response()->json([
            'success' => true,
            'message' => 'Товар удалён из избранного',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function jsonProducts()
    {
        $desserts = $this->publicDessertQuery()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->withNormalizedPhotos($desserts),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function jsonProduct($id)
    {
        $dessert = $this->publicDessertQuery()->find($id);

        if (! $dessert) {
            return response()->json([
                'success' => false,
                'error' => 'Dessert not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => $this->withNormalizedPhotos($dessert),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function imageProxy(Request $request)
    {
        $url = $request->query('url');

        if (! $this->isAllowedImageUrl($url)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid image URL',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $cacheDirectory = storage_path('app/image-proxy');
        $cacheKey = hash('sha256', $url);
        $imagePath = $cacheDirectory.DIRECTORY_SEPARATOR.$cacheKey.'.image';
        $metaPath = $cacheDirectory.DIRECTORY_SEPARATOR.$cacheKey.'.json';

        if (is_file($imagePath)) {
            $contentType = 'image/jpeg';
            if (is_file($metaPath)) {
                $meta = json_decode((string) file_get_contents($metaPath), true);
                if (is_array($meta) && is_string($meta['content_type'] ?? null)) {
                    $contentType = $meta['content_type'];
                }
            }

            return response()->file($imagePath, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $remote = Http::timeout(15)->retry(2, 250)->get($url);

        if (! $remote->successful()) {
            return response($remote->body(), $remote->status())
                ->header('Content-Type', $remote->header('Content-Type', 'text/plain'));
        }

        if (! is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        $contentType = $remote->header('Content-Type', 'image/jpeg');
        file_put_contents($imagePath, $remote->body());
        file_put_contents($metaPath, json_encode([
            'content_type' => $contentType,
            'source_url' => $url,
            'cached_at' => now()->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return response($remote->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function withNormalizedPhotos($value)
    {
        $favoriteIds = $this->favoriteDessertIds();

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn (Dessert $dessert) => $this->dessertArrayWithNormalizedPhotos($dessert, $favoriteIds))->values();
        }

        if ($value instanceof Dessert) {
            return $this->dessertArrayWithNormalizedPhotos($value, $favoriteIds);
        }

        return $value;
    }

    private function publicDessertQuery()
    {
        return Dessert::query()
            ->where('available', true)
            ->where('archived', false);
    }

    private function dessertMatchesSearch(Dessert $dessert, array $terms): bool
    {
        $haystack = implode(' ', array_filter([
            $dessert->name,
            $dessert->category,
            $dessert->description,
            $dessert->composition,
        ], fn ($value) => is_string($value) && trim($value) !== ''));

        foreach ($terms as $term) {
            if (mb_stripos($haystack, $term) === false) {
                return false;
            }
        }

        return true;
    }

    private function dessertArrayWithNormalizedPhotos(Dessert $dessert, array $favoriteIds = []): array
    {
        $data = $dessert->toArray();
        $photos = $dessert->photos;
        $data['is_favorite'] = in_array($dessert->id, $favoriteIds, true);

        if (is_array($photos)) {
            $data['photos'] = array_values(array_map(
                fn (string $photo) => $this->originalImageUrl($photo),
                array_filter($photos, fn ($photo) => is_string($photo) && trim($photo) !== '')
            ));
        }

        return $data;
    }

    private function originalImageUrl(string $photo): string
    {
        $photo = trim($photo);
        $query = parse_url($photo, PHP_URL_QUERY);

        if ($query === null) {
            return $photo;
        }

        parse_str($query, $params);

        if (isset($params['url']) && is_string($params['url']) && $this->isAllowedImageUrl($params['url'])) {
            return $params['url'];
        }

        return $photo;
    }

    private function favoriteDessertIds(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return $user->favoriteDesserts()
            ->pluck('desserts.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isAllowedImageUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $scheme = $parts['scheme'] ?? '';

        return $scheme === 'https' && str_ends_with($host, '.supabase.co');
    }
}
