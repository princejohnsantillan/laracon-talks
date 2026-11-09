<?php

use App\Http\Controllers\TalkController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$otherLanguages = array_keys(array_diff_key(TalkController::LANGUAGES, ['en' => true]));

// The archive is a read-only public page: it needs no session and sets no cookies.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () use ($otherLanguages) {
    // The year sections the unfiltered page appends after it loads ("/earlier", "/earlier/es").
    Route::get('/earlier/{locale?}', [TalkController::class, 'earlier'])
        ->whereIn('locale', $otherLanguages)
        ->name('talks.earlier');

    // Words each talk says, for search ("/keywords").
    Route::get('/keywords', [TalkController::class, 'keywords'])->name('talks.keywords');

    // Which talks say a searched word anywhere in them, when few talks match otherwise ("/said").
    // POST, so the searched words travel in the body and not in the address.
    Route::post('/said', [TalkController::class, 'said'])->name('talks.said');

    // English is at "/", other interface languages at "/es" and so on.
    Route::get('/{locale?}', [TalkController::class, 'index'])
        ->whereIn('locale', $otherLanguages)
        ->name('talks.index');
});
