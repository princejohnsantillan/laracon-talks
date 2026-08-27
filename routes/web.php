<?php

use App\Http\Controllers\TalkController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TalkController::class, 'index'])->name('talks.index');
