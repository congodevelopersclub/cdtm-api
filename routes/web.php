<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\WebAuthController;

Route::get('/admin/login', [WebAuthController::class, 'index'])->name('login');
Route::post('/admin/login', [WebAuthController::class, 'login'])->name('auth')->middleware('throttle:5,1');
Route::post('/admin/logout', [WebAuthController::class, 'logout'])->name('logout')->middleware('auth');
