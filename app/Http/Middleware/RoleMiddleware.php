<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        // التأكد من وجود مستخدم مسجل دخول
        if (!$request->user()) {
            return response()->json(['error' => 'غير مصرح، لم يتم تسجيل الدخول'], 401);
        }

        // مقارنة الدور بدون حساسية للحروف أو المسافات
        if (trim(strtolower($request->user()->role)) !== trim(strtolower($role))) {
            return response()->json(['error' => 'غير مصرح'], 403);
        }

        return $next($request);
    }
}
