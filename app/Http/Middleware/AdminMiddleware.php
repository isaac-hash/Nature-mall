<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // if ($user instanceof User && $user->isAdmin()) {
        //     return $next($request);
        // }
        if($user instanceof User && $user->role === 'admin') {
            return $next($request);
        }


        return response()->json([
            'message' => 'Unauthorized.',
            'role' => $user->role
        ], 403);
    }
}
