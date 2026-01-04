<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DessertController;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Здесь вы можете регистрировать веб-маршруты для вашего приложения. 
| Эти маршруты загружаются через RouteServiceProvider внутри группы "web".
|
*/

// Маршрут для получения всех десертов в формате JSON
Route::get('/products', [DessertController::class, 'jsonProducts']);
Route::get('/product/{id}', [DessertController::class, 'jsonProduct']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me',           [MeController::class, 'show']);
    Route::put('/me',           [MeController::class, 'update']);
    Route::put('/me/password',  [MeController::class, 'changePassword']);
});

// Пример дополнительных маршрутов (по желанию)
// Route::get('/', function () {
//     return view('welcome');
// });

// Можно добавить маршруты для отдельного десерта по id
// Route::get('/products/{id}', [DessertController::class, 'show']);

// Можно добавить маршруты для создания, обновления и удаления десертов
// Route::post('/products', [DessertController::class, 'store']);
// Route::put('/products/{id}', [DessertController::class, 'update']);
// Route::delete('/products/{id}', [DessertController::class, 'destroy']);