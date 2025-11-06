<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BinanceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ExchangeCredentialsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Ruta temporal para probar el dashboard de transacciones
Route::get('/test-transactions', [TransactionController::class, 'index'])->name('test.transactions');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Settings Routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    
    // Bot Configuration Routes
    Route::post('/bot/configuration', [BotController::class, 'saveConfiguration']);
    Route::get('/bot/configuration', [BotController::class, 'getConfiguration']);
    Route::post('/bot/{id}/toggle', [BotController::class, 'toggleBotStatus']);
    Route::get('/bot/actions/suggested', [BotController::class, 'getActionsSuggested']);
    Route::post('/bot/actions/{id}/approve', [BotController::class, 'approveAction']);
    Route::post('/bot/actions/{id}/reject', [BotController::class, 'rejectAction']);
    Route::get('/bot/actions/history', [BotController::class, 'getActionHistory']);
    
    // Binance P2P API Routes
    Route::post('/binance/ad/details', [BinanceController::class, 'getAdDetails']);
    Route::get('/binance/reference-price/{fiat}/{asset}', [BinanceController::class, 'getReferencePrice']);
    Route::post('/binance/search-competitors', [BinanceController::class, 'searchCompetitors']);
    Route::get('/binance/market-data', [BinanceController::class, 'getMarketData']);
    Route::get('/binance/p2p-ads', [BinanceController::class, 'getP2PAds']);
    Route::post('/binance/sync-p2p-ads', [BinanceController::class, 'syncP2PAds']);
    
    // Binance Credentials (mantener compatibilidad)
    Route::post('/binance/credentials', [BinanceController::class, 'storeCredentials']);
    
    // Exchange Credentials Management Routes
    Route::get('/settings/exchanges', [ExchangeCredentialsController::class, 'index'])
        ->name('settings.exchanges');
    Route::post('/settings/exchanges/binance', [ExchangeCredentialsController::class, 'storeBinance']);
    Route::post('/settings/exchanges/bybit', [ExchangeCredentialsController::class, 'storeBybit']);
    Route::post('/settings/exchanges/okx', [ExchangeCredentialsController::class, 'storeOKX']);
    Route::post('/settings/exchanges/{exchange}/test', [ExchangeCredentialsController::class, 'testConnection']);
    Route::delete('/settings/exchanges/{exchange}', [ExchangeCredentialsController::class, 'deleteCredentials']);
    
    // Transaction Management Routes
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/create/manual', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    
    // Transaction Sync Routes
    Route::post('/transactions/sync', [TransactionController::class, 'sync'])->name('transactions.sync');
    Route::get('/transactions/stats', [TransactionController::class, 'stats'])->name('transactions.stats');
    Route::post('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/export/excel', [TransactionController::class, 'exportExcel'])->name('transactions.export.excel');
    
    // Reports Routes
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
});

require __DIR__.'/auth.php';
