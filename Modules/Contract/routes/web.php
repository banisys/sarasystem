<?php

use Illuminate\Support\Facades\Route;
use Modules\Contract\Http\Controllers\MyContractController;

// The person's own contracts in the user panel, inside the current tenant
// (docs/decisions/0020-contracts.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('contracts')->name('contracts.')->group(function () {
    Route::get('/', [MyContractController::class, 'index'])->name('index');
    Route::get('/{contract}', [MyContractController::class, 'show'])->name('show');
    Route::get('/{contract}/pdf', [MyContractController::class, 'pdf'])->name('pdf');
    Route::post('/{contract}/code', [MyContractController::class, 'sendCode'])->name('code');
    Route::post('/{contract}/sign', [MyContractController::class, 'sign'])->name('sign');
});
