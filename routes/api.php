<?php

use App\Http\Controllers\Api\SameProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/same-products', [SameProductController::class, 'show']);
