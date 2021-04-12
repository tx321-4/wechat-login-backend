<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
class AuthTest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, Guard $guard = null)
    {
     if (!\Auth::guard($guard)->check()) {
            return response()->api(-1, 'no login');
        }
        return $next($request);
    }
}
