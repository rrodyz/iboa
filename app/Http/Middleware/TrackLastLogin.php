<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackLastLogin
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Update once per session login (not on every request)
        if (Auth::check() && ! session()->has('login_tracked')) {
            Auth::user()->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
            session(['login_tracked' => true]);
        }

        return $next($request);
    }
}
