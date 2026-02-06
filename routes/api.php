<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DessertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\OrderController;

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

Route::get('/products/search', [DessertController::class, 'searchProducts']);


Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me',           [MeController::class, 'show']);
    Route::put('/me/update',           [MeController::class, 'update']);
    Route::put('/me/password',  [MeController::class, 'changePassword']);

    Route::get   ('/cart',                 [CartController::class, 'show']);      // получить корзину
    Route::post  ('/cart/items',           [CartController::class, 'add']);       // добавить десерт
    Route::patch ('/cart/items/{dessert}', [CartController::class, 'setQty']);    // изменить qty
    Route::delete('/cart/items/{dessert}', [CartController::class, 'remove']);    // удалить десерт
    Route::delete('/cart',                 [CartController::class, 'clear']);     // очистить корзину

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::post('/addresses/{id}/default', [AddressController::class, 'setDefault']);

    Route::get('/orders',            [OrderController::class, 'index']);   // список моих заказов
    Route::get('/orders/{id}',       [OrderController::class, 'show']);    // один заказ
    Route::post('/orders',           [OrderController::class, 'store']);   // создать заказ из корзины
    Route::post('/orders/{id}/cancel',[OrderController::class, 'cancel']); // отменить (если можно)

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