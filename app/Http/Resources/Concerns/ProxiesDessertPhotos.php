<?php

namespace App\Http\Resources\Concerns;

trait ProxiesDessertPhotos
{
    protected function proxyBaseUrl(): string
    {
        return request()->getSchemeAndHttpHost().'/api/image-proxy';
    }

    protected function proxiedPhotos(?array $photos, ?string $proxyBaseUrl = null): ?array
    {
        if ($photos === null) {
            return null;
        }

        return array_values(array_map(
            fn (string $photo) => $this->originalImageUrl($photo),
            array_filter($photos, fn ($photo) => is_string($photo) && trim($photo) !== '')
        ));
    }

    private function originalImageUrl(string $photo): string
    {
        $photo = trim($photo);
        $query = parse_url($photo, PHP_URL_QUERY);

        if ($query === null) {
            return $photo;
        }

        parse_str($query, $params);

        if (isset($params['url']) && is_string($params['url']) && $this->isAllowedSupabaseImageUrl($params['url'])) {
            return $params['url'];
        }

        return $photo;
    }

    private function isAllowedSupabaseImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $scheme = $parts['scheme'] ?? '';

        return $scheme === 'https' && str_ends_with($host, '.supabase.co');
    }
}
