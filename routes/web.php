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
| - /home => يعرض Laravel (مثلاً welcome.blade.php)
| - / => يعرض React (index.html)
| - /api/... => يظل للـ API
| - أي Route تاني => fallback إلى React
|
*/

Route::get('/home', function () {
    return view('welcome');
})->name('home');

// Route لتأمين CSRF لـ Sanctum (لو بتستخدم React + Laravel API)
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->name('sanctum.csrf');

// المسار الأساسي: يفتح تطبيق React
Route::get('/', function () {
    $path = public_path('index.html');

    if (File::exists($path)) {
        return Response::file($path);
    }

    abort(404, 'React app not found.');
});

// fallback لأي Route غير معروف (علشان React Router)
Route::fallback(function () {
    $path = public_path('index.html');

    if (!request()->is('api/*')) {
        if (File::exists($path)) {
            return Response::file($path);
        }
    }

    abort(404, 'Page not found.');
});
