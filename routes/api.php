<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DebtLedgerController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\SupportingDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:15,1');
    Route::middleware('mobile.auth')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{session}', [AuthController::class, 'revokeSession']);
        Route::middleware('endpoint-access:clients.view')->get('clients', [SupportingDataController::class, 'clients']);
        Route::middleware('endpoint-access:clients.create')->post('clients', [SupportingDataController::class, 'storeClient']);
        Route::middleware('endpoint-access:clients.view')->get('clients/{client}/shops', [SupportingDataController::class, 'shops']);
        Route::middleware('endpoint-access:shops.create')->post('clients/{client}/shops', [SupportingDataController::class, 'storeShop']);
        Route::middleware('endpoint-access:catalogs.view')->get('product-categories', [SupportingDataController::class, 'categories']);
        Route::middleware('endpoint-access:catalogs.view')->get('products', [SupportingDataController::class, 'products']);
        Route::middleware('endpoint-access:suppliers.view')->get('suppliers', [SupportingDataController::class, 'suppliers']);
        Route::middleware('endpoint-access:suppliers.create')->post('suppliers', [SupportingDataController::class, 'storeSupplier']);
        Route::middleware('endpoint-access:providers.view')->get('providers', [SupportingDataController::class, 'providers']);
        Route::middleware('endpoint-access:debt_ledgers.view')->get('debt-ledgers', [DebtLedgerController::class, 'index']);
        Route::middleware('endpoint-access:debt_ledgers.create')->post('debt-ledgers', [DebtLedgerController::class, 'store']);
        Route::middleware('endpoint-access:debt_ledgers.view')->get('debt-ledgers/{debtLedger}', [DebtLedgerController::class, 'show']);
        Route::middleware('endpoint-access:debt_ledgers.update')->patch('debt-ledgers/{debtLedger}', [DebtLedgerController::class, 'update']);
        Route::middleware('endpoint-access:debt_ledgers.delete')->delete('debt-ledgers/{debtLedger}', [DebtLedgerController::class, 'destroy']);
        Route::middleware('endpoint-access:distributions.view')->get('distributions', [DistributionController::class, 'index']);
        Route::middleware('endpoint-access:distributions.create')->post('distributions', [DistributionController::class, 'store']);
        Route::middleware('endpoint-access:distributions.view')->get('distributions/{distribution}', [DistributionController::class, 'show']);
        Route::middleware('endpoint-access:distributions.update')->patch('distributions/{distribution}', [DistributionController::class, 'update']);
        Route::middleware('endpoint-access:distributions.delete')->delete('distributions/{distribution}',[DistributionController::class, 'destroy']);
    });
});
