<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\HotmartController;
use App\Http\Controllers\YampiController;
use App\Http\Controllers\DigitalController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/events/send', [EventsController::class, 'send']);
Route::post('/webhook/hotmart', [HotmartController::class, 'Hotmart']);
Route::post('/webhook/yampi', [YampiController::class, 'Yampi']);
Route::post('/webhook/digital', [DigitalController::class, 'Digital']);

Route::middleware(['auth:sanctum'])->group(function () {
});

Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    
});