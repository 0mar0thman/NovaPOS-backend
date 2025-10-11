<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| - /api/... => خاص بالـ API (في routes/api.php)
| - /sanctum/csrf-cookie => خاص بالأمان
| - / => React index.html
| - أي Route تاني => fallback إلى React
|
*/

// Route CSRF خاص بـ Sanctum
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->name('sanctum.csrf');

// Route رئيسي للـ React App
Route::get('/', function () {
    return Response::file(public_path('index.html'));
});

// Fallback لأي Route Frontend فقط (ليس API)
Route::fallback(function () {
    if (!request()->is('api/*')) {
        $path = public_path('index.html');
        if (File::exists($path)) {
            return Response::file($path);
        }
    }
    abort(404);
});
