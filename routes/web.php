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
        Route::resource('shops', \App\Http\Controllers\Admin\ShopController::class)->only(['store']);
        Route::resource('suppliers', \App\Http\Controllers\Admin\SupplierController::class);
        Route::resource('products', \App\Http\Controllers\Admin\ProductController::class)->except(['destroy']);
        Route::post('distributions/potential-duplicates/resolve', [\App\Http\Controllers\Admin\DistributionController::class, 'resolvePotentialDuplicate'])
            ->name('distributions.potential-duplicates.resolve');
        Route::resource('distributions', \App\Http\Controllers\Admin\DistributionController::class);
        Route::post('debt-ledgers/potential-duplicates/resolve', [\App\Http\Controllers\Admin\DebtLedgerController::class, 'resolvePotentialDuplicate'])
            ->name('debt-ledgers.potential-duplicates.resolve');
        Route::resource('debt-ledgers', \App\Http\Controllers\Admin\DebtLedgerController::class);
        Route::get('operations', [\App\Http\Controllers\Admin\OperationController::class, 'index'])->name('operations.index');
        Route::get('whatsapp-imports', [\App\Http\Controllers\Admin\WhatsAppImportController::class, 'index'])->name('whatsapp-imports.index');
        Route::post('whatsapp-imports', [\App\Http\Controllers\Admin\WhatsAppImportController::class, 'store'])->name('whatsapp-imports.store');
        Route::get('whatsapp-tasks', [\App\Http\Controllers\Admin\WhatsAppTaskController::class, 'index'])->name('whatsapp-tasks.index');
        Route::get('whatsapp-tasks/created', [\App\Http\Controllers\Admin\WhatsAppTaskController::class, 'created'])->name('whatsapp-tasks.created');
        Route::post('whatsapp-tasks', [\App\Http\Controllers\Admin\WhatsAppTaskController::class, 'store'])->name('whatsapp-tasks.store');
        Route::post('whatsapp-tasks/messages/delete', [\App\Http\Controllers\Admin\WhatsAppTaskController::class, 'destroyMessages'])->name('whatsapp-tasks.messages.destroy');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
            Route::post('/export', [\App\Http\Controllers\Admin\ReportController::class, 'export'])->name('export');
            Route::post('/export-client-debt/{client}', [\App\Http\Controllers\Admin\ReportController::class, 'exportClientDebt'])->name('export-client-debt');
            Route::post('/export-client-debt-range/{client}', [\App\Http\Controllers\Admin\ReportController::class, 'exportClientDebtRange'])->name('export-client-debt-range');
            Route::post('/{report}/regenerate', [\App\Http\Controllers\Admin\ReportController::class, 'regenerate'])->name('regenerate');
        });
    });

    Route::get('/dashboard', function () {
        return redirect()->route('admin.debt-ledgers.index');
    })->name('dashboard');
});
