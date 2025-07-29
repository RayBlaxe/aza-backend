<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperadminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (Auth::user()->role !== 'superadmin') {
            return response()->json(['error' => 'Forbidden. Superadmin access required.'], 403);
        }

        return $next($request);
    }
}
