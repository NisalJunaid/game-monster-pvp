<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnsureStarterChosen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && ! $user->has_starter && ! $request->routeIs('starter.*', 'logout')) {
            return redirect()->route('starter.show');
        }

        return $next($request);
    }
}
