<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedIP
{
     /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the IP address of the incoming request
        $ip = $request->ip();

        // Check if there is any user with this IP address that is blocked
        $blockedUser = User::where('ipaddress', $ip)
                                        ->where('block', true)
                                        ->first();

        // If the user's IP is blocked, log out the user and redirect
        if ($blockedUser) {
            Auth::logout(); // Log out the user if they are logged in
            return redirect()->route('blocked.page')->with('error', 'Your IP address is blocked.');
        }

            return $next($request);
   }
}
