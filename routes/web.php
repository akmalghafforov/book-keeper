<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ru', 'tj'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('set-locale');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.debt-ledgers.index');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.debt-ledgers.index');
        });
        Route::resource('clients', \App\Http\Controllers\Admin\ClientController::class)->except(['destroy']);
        Route::resource('suppliers', \App\Http\Controllers\Admin\SupplierController::class);
        Route::resource('products', \App\Http\Controllers\Admin\ProductController::class)->except(['destroy']);
        Route::resource('distributions', \App\Http\Controllers\Admin\DistributionController::class);
        Route::resource('debt-ledgers', \App\Http\Controllers\Admin\DebtLedgerController::class);

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
            Route::get('/client-debt', [\App\Http\Controllers\Admin\ReportController::class, 'clientDebt'])->name('client-debt');
            Route::post('/export', [\App\Http\Controllers\Admin\ReportController::class, 'export'])->name('export');
        });
    });

    Route::get('/dashboard', function () {
        return redirect()->route('admin.debt-ledgers.index');
    })->name('dashboard');
});
