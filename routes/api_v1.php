<?php

use App\Http\Controllers\Api\V1\{SkillController, ProfileController, UserController, AuthController};
use Illuminate\Support\Facades\Route;

// Auth routes
Route::get('auth', [AuthController::class, 'redirect'])->name('auth.index');
Route::post('auth/exchange-code', [AuthController::class, 'exchangeCode'])->name('auth.exchangeCode');
Route::get('auth/linkedin/signup', [AuthController::class, 'signUp'])->name('auth.signUp');
Route::get('me', [AuthController::class, 'me'])->name('auth.me');

// User routes
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

// Skill routes
Route::apiResource('skills', SkillController::class);

// Profile routes -> I set them like this to have more control over the permsissions and the actions that can be performed on profiles.
Route::get('profiles', [ProfileController::class, 'index']);
Route::get('profiles/{profile}', [ProfileController::class, 'show']);
Route::post('profiles', [ProfileController::class, 'store']);
Route::patch('profiles/{profile}', [ProfileController::class, 'update']);
Route::delete('profiles/{profile}', [ProfileController::class, 'destroy']);
Route::put('profiles/{profile}/validate', [ProfileController::class, 'validateProfile']);
