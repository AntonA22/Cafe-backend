<?php

namespace App\Http\Middleware;

// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_staff) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
