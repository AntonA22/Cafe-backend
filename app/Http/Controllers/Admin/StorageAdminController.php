<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use RuntimeException;

class StorageAdminController extends Controller
{
    public function destroyPhoto(Request $request, SupabaseStorageService $storage)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
        ]);

        try {
            $result = $storage->deletePublicUrl($data['url']);
        } catch (RuntimeException $error) {
            return response()->json([
                'success' => false,
                'error' => $error->getMessage(),
            ], 502, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
