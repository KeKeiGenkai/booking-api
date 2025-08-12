<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $apiToken = $request->header('Authorization');
        
        if (str_starts_with($apiToken, 'Bearer ')) {
            $apiToken = substr($apiToken, 7);
        }
        
        if (!$apiToken) {
            return response()->json([
                'message' => 'API токен не предоставлен'
            ], 401);
        }
        
        $user = User::where('api_token', $apiToken)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'Неверный API токен'
            ], 401);
        }
        
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        return $next($request);
    }
}
