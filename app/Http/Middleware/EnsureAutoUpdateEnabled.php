<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAutoUpdateEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('installer.run_installer', false)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Aggiornamenti automatici non disponibili'
                ], 403);
            }

            return redirect()->route('system.upgrade.index');
        }

        return $next($request);
    }
}
