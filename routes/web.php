<?php

use App\Http\Controllers\Webhooks\ProductWebhookController;
use App\Http\Controllers\Webhooks\VariantWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::name('webhooks.')
    ->prefix('webhooks/{language}')
    ->middleware(config('webshop.webhooks.middleware', []))
    ->group(function () {
        Route::controller(ProductWebhookController::class)
            ->name('products.')
            ->prefix('products')
            ->group(function () {
                Route::post('created', 'created')->name('created');
                Route::post('updated', 'updated')->name('updated');
                Route::post('deleted', 'deleted')->name('deleted');
            });

        Route::controller(VariantWebhookController::class)
            ->name('variants.')
            ->prefix('variants')
            ->group(function () {
                Route::post('created', 'created')->name('created');
                Route::post('updated', 'updated')->name('updated');
                Route::post('deleted', 'deleted')->name('deleted');
            });
    });
