<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\StockApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — A3 ERP
|--------------------------------------------------------------------------
|
| Base URL  : /api
| Auth      : Bearer token (Laravel Sanctum)
|             POST /api/auth/token  { email, password, device_name }
|
| Rate limit: 60 req/min per token (Laravel default)
|
*/

// ── Authentication ────────────────────────────────────────────────────────────
// [SEC-C2] Rate-limited to 5 attempts per minute to prevent brute-force attacks.
Route::post('/auth/token', [ApiController::class, 'token'])
    ->middleware('throttle:5,1')
    ->name('api.token');

// ── Protected endpoints ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::delete('/auth/token', [ApiController::class, 'revoke'])->name('api.revoke');

    // Produits
    Route::get('/products',        [ProductApiController::class, 'index'])->name('api.products.index');
    // /search MUST be declared before /{product} — otherwise "search" is treated as a product ID.
    Route::get('/products/search', [ProductApiController::class, 'search'])->name('api.products.search');
    Route::get('/products/{product}', [ProductApiController::class, 'show'])->name('api.products.show');

    // Clients
    Route::get('/clients',       [ClientApiController::class, 'index'])->name('api.clients.index');
    Route::get('/clients/{client}', [ClientApiController::class, 'show'])->name('api.clients.show');

    // Factures clients
    Route::get('/invoices',      [InvoiceApiController::class, 'index'])->name('api.invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show'])->name('api.invoices.show');

    // Stock
    Route::get('/stock',         [StockApiController::class, 'index'])->name('api.stock.index');
    Route::get('/stock/movements', [StockApiController::class, 'movements'])->name('api.stock.movements');
});
