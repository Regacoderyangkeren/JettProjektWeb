<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirebaseSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('firebase.uid')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
