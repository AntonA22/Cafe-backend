<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DessertController;

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