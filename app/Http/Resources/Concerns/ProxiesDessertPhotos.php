<?php

namespace App\Http\Resources\Concerns;

trait ProxiesDessertPhotos
{
    protected function proxyBaseUrl(): string
    {
        return request()->getSchemeAndHttpHost() . '/api/image-proxy';
    }

    protected function proxiedPhotos(?array $photos, ?string $proxyBaseUrl = null): ?array
    {
        if ($photos === null) {
            return null;
        }

        $proxyBaseUrl ??= $this->proxyBaseUrl();

        return array_values(array_map(
            fn (string $photo) => str_starts_with($photo, 'data:image/')
                ? $photo
                : $proxyBaseUrl . '?url=' . rawurlencode($photo),
            array_filter($photos, fn ($photo) => is_string($photo) && trim($photo) !== '')
        ));
    }
}
