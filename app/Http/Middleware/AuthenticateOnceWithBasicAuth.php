<?php

namespace App\Http\Middleware;

use Auth;
use Closure;

class AuthenticateOnceWithBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::onceBasic()) {
            return response()->json(['message' => 'Your username or password is invalid'], 401);
        } else {
            return $next($request);
        }
    }
}