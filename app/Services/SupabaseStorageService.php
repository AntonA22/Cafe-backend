<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseStorageService
{
    public function deletePublicUrl(?string $url): array
    {
        $path = $this->pathFromPublicUrl($url);
        if ($path === null) {
            return [
                'deleted' => false,
                'skipped' => true,
                'missing' => false,
                'path' => null,
            ];
        }

        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $serviceRoleKey = trim((string) config('services.supabase.service_role_key'));
        $bucket = (string) config('services.supabase.bucket', 'cafe');

        if ($baseUrl === '' || $serviceRoleKey === '') {
            throw new RuntimeException('Supabase Storage is not configured');
        }

        $deleteUrl = $baseUrl.'/storage/v1/object/'.rawurlencode($bucket).'/'.$this->encodePath($path);
        $response = Http::withHeaders([
            'apikey' => $serviceRoleKey,
            'Authorization' => 'Bearer '.$serviceRoleKey,
        ])->delete($deleteUrl);

        if ($response->status() === 404) {
            return [
                'deleted' => false,
                'skipped' => false,
                'missing' => true,
                'path' => $path,
            ];
        }

        if (! $response->successful()) {
            $payload = $response->json();
            $message = $payload['message'] ?? $payload['error'] ?? $response->body();
            throw new RuntimeException('Supabase Storage delete failed: '.$message);
        }

        return [
            'deleted' => true,
            'skipped' => false,
            'missing' => false,
            'path' => $path,
        ];
    }

    private function pathFromPublicUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $bucket = (string) config('services.supabase.bucket', 'cafe');
        if ($baseUrl === '') {
            return null;
        }

        $urlParts = parse_url($url);
        $baseParts = parse_url($baseUrl);
        if (! is_array($urlParts) || ! is_array($baseParts)) {
            return null;
        }

        $urlOrigin = ($urlParts['scheme'] ?? '').'://'.($urlParts['host'] ?? '');
        $baseOrigin = ($baseParts['scheme'] ?? '').'://'.($baseParts['host'] ?? '');
        if ($urlOrigin !== $baseOrigin) {
            return null;
        }

        $path = $urlParts['path'] ?? '';
        $bucketPattern = preg_quote(rawurlencode($bucket), '#');
        $pattern = '#^/storage/v1/object/(?:public|sign)/'.$bucketPattern.'/(.+)$#';
        if (! preg_match($pattern, $path, $matches)) {
            return null;
        }

        return collect(explode('/', $matches[1]))
            ->map(fn (string $part) => rawurldecode($part))
            ->implode('/');
    }

    private function encodePath(string $path): string
    {
        return collect(explode('/', $path))
            ->map(fn (string $part) => rawurlencode($part))
            ->implode('/');
    }
}
