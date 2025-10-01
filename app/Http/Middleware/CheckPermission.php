<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        if (!$request->user() || !$request->user()->can($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول',
                'errors' => ['permission' => 'لا تمتلك الصلاحية المطلوبة']
            ], 403);
        }

        return $next($request);
    }
}
