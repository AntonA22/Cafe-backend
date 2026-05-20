<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CustomCakePreviewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:32000'],
            'image_base64' => ['required', 'string'],
            'image_mime_type' => ['sometimes', 'string', 'max:100'],
        ]);

        $apiKey = trim((string) config('services.aitunnel.key'));
        if ($apiKey === '') {
            return response()->json([
                'success' => false,
                'error' => 'AITUNNEL_API_KEY is not configured',
            ], 500);
        }

        $imageData = base64_decode($this->stripDataUrlPrefix($data['image_base64']), true);
        if ($imageData === false || $imageData === '') {
            return response()->json([
                'success' => false,
                'error' => 'Некорректные данные изображения',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $mimeType = $data['image_mime_type'] ?? 'image/png';
        $baseUrl = rtrim((string) config('services.aitunnel.base_url'), '/');
        $model = (string) config('services.aitunnel.image_model');

        try {
            $response = Http::timeout(180)
                ->withToken($apiKey)
                ->attach('image', $imageData, 'cake-preview.png', [
                    'Content-Type' => $mimeType,
                ])
                ->post($baseUrl.'/images/edits', [
                    'model' => $model,
                    'prompt' => $data['prompt'],
                    'image_config' => json_encode([
                        'aspect_ratio' => '1:1',
                        'image_size' => '1K',
                    ], JSON_UNESCAPED_SLASHES),
                ]);
        } catch (ConnectionException $error) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось подключиться к AITunnel: '.$error->getMessage(),
            ], 502, [], JSON_UNESCAPED_UNICODE);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $this->aitunnelErrorMessage($response->json(), $response->body(), $response->status()),
            ], $response->status(), [], JSON_UNESCAPED_UNICODE);
        }

        $payload = $response->json();
        $imageBase64 = $this->extractImageBase64($payload);
        if ($imageBase64 === null) {
            return response()->json([
                'success' => false,
                'error' => 'AITunnel не вернул изображение',
            ], 502, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'image_base64' => $imageBase64,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function stripDataUrlPrefix(string $value): string
    {
        if (Str::startsWith($value, 'data:image/')) {
            return (string) Str::after($value, ',');
        }

        return $value;
    }

    private function extractImageBase64(?array $payload): ?string
    {
        $firstImage = $payload['data'][0] ?? null;
        if (! is_array($firstImage)) {
            return null;
        }

        if (! empty($firstImage['b64_json']) && is_string($firstImage['b64_json'])) {
            return $firstImage['b64_json'];
        }

        $url = $firstImage['url'] ?? null;
        if (is_string($url) && Str::startsWith($url, 'data:image/')) {
            return (string) Str::after($url, ',');
        }

        return null;
    }

    private function aitunnelErrorMessage(?array $payload, string $body, int $status): string
    {
        $message = $payload['error']['message'] ?? $payload['message'] ?? null;
        if (is_string($message) && $message !== '') {
            return "AITunnel error {$status}: {$message}";
        }

        return "AITunnel error {$status}: {$body}";
    }
}
