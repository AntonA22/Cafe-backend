<?php

namespace App\Http\Controllers;

use App\Models\Dessert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class DessertController extends Controller
{

    public function searchProducts(Request $request)
    {
        $searchName  = $request->input('query');
        $query = Dessert::query()->where('available', true);

        if ($searchName === '*') {
            $desserts = $query->get();
        } else {
            $desserts = $query
                ->where('name', 'LIKE', "%{$searchName}%")
                ->get();
        }

        return response()->json([
            "success" => true,
            "data" => $this->withProxiedPhotos($desserts)
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }


    public function jsonProducts()
    {
        $desserts = Dessert::query()
            ->where('available', true)
            ->get();

        return response()->json([
            "success" => true,
            "data" => $this->withProxiedPhotos($desserts)
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
            "data" => $this->withProxiedPhotos($dessert)
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function imageProxy(Request $request)
    {
        $url = $request->query('url');

        if (!$this->isAllowedImageUrl($url)) {
            return response()->json([
                "success" => false,
                "error" => "Invalid image URL"
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $cacheDirectory = storage_path('app/image-proxy');
        $cacheKey = hash('sha256', $url);
        $imagePath = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.image';
        $metaPath = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.json';

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

        if (!$remote->successful()) {
            return response($remote->body(), $remote->status())
                ->header('Content-Type', $remote->header('Content-Type', 'text/plain'));
        }

        if (!is_dir($cacheDirectory)) {
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

    private function withProxiedPhotos($value)
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn (Dessert $dessert) => $this->dessertArrayWithProxiedPhotos($dessert))->values();
        }

        if ($value instanceof Dessert) {
            return $this->dessertArrayWithProxiedPhotos($value);
        }

        return $value;
    }

    private function dessertArrayWithProxiedPhotos(Dessert $dessert): array
    {
        $data = $dessert->toArray();
        $photos = $dessert->photos;

        if (is_array($photos)) {
            $proxyBaseUrl = request()->getSchemeAndHttpHost() . '/api/image-proxy';
            $data['photos'] = array_values(array_map(
                fn (string $photo) => $proxyBaseUrl . '?url=' . rawurlencode($photo),
                array_filter($photos, fn ($photo) => is_string($photo) && trim($photo) !== '')
            ));
        }

        return $data;
    }

    private function isAllowedImageUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $scheme = $parts['scheme'] ?? '';

        return $scheme === 'https' && str_ends_with($host, '.supabase.co');
    }
}
