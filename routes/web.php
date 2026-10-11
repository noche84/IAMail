<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\TestEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn() => redirect()->route('inbox.index'));

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Boîte de réception
    Route::prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::get('/{analysis}', [InboxController::class, 'show'])->name('show');
        Route::post('/{analysis}/toggle-star', [InboxController::class, 'toggleStar'])->name('toggle-star');
        Route::post('/{analysis}/toggle-read', [InboxController::class, 'toggleRead'])->name('toggle-read');
        Route::post('/{analysis}/mark-read-server', [InboxController::class, 'markReadOnServer'])->name('mark-read-server');
        Route::post('/bulk-mark-read', [InboxController::class, 'bulkMarkRead'])->name('bulk-mark-read');
    });

    // Classification IA
    Route::prefix('review')->name('review.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/{analysis}', [ReviewController::class, 'show'])->name('show');
        Route::post('/{analysis}/validate', [ReviewController::class, 'validateAnalysis'])->name('validate');
        Route::post('/{analysis}/correct', [ReviewController::class, 'correct'])->name('correct');
        Route::post('/{analysis}/ignore', [ReviewController::class, 'ignore'])->name('ignore');
        Route::post('/bulk-validate', [ReviewController::class, 'bulkValidate'])->name('bulk-validate');
        Route::post('/recheck', [ReviewController::class, 'recheckPending'])->name('recheck');
    });

    // Comptes email
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [EmailAccountController::class, 'index'])->name('index');
        Route::get('/create', [EmailAccountController::class, 'create'])->name('create');
        Route::post('/', [EmailAccountController::class, 'store'])->name('store');
        Route::get('/{account}/edit', [EmailAccountController::class, 'edit'])->name('edit');
        Route::put('/{account}', [EmailAccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [EmailAccountController::class, 'destroy'])->name('destroy');
        Route::post('/{account}/test', [EmailAccountController::class, 'test'])->name('test');
        Route::post('/{account}/collect', [EmailAccountController::class, 'collectNow'])->name('collect');
        Route::get('/{account}/learned', [EmailAccountController::class, 'learnedExamples'])->name('learned');
        Route::post('/{account}/learned', [EmailAccountController::class, 'addExample'])->name('add-example');
        Route::delete('/{account}/learned/{example}', [EmailAccountController::class, 'deleteExample'])->name('delete-example');
        Route::get('/{account}/preview-prompt', [EmailAccountController::class, 'previewPrompt'])->name('preview-prompt');
    });

    Route::get('/test', [TestEmailController::class, 'form'])->name('test.form');
    Route::post('/test', [TestEmailController::class, 'analyze'])->name('test.analyze');
});
