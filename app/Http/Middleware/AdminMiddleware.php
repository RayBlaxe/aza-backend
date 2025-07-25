<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Forbidden. Admin access required.'], 403);
        }

        return $next($request);
    }
}
