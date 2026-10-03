<?php

use App\Http\Controllers\Api\V1\{SkillController, ProfileController, UserController, AuthController, CategoryController};
use Illuminate\Support\Facades\Route;

Route::get('auth', [AuthController::class, 'redirect'])->name('auth.index');
Route::get('auth/linkedin/signup', [AuthController::class, 'signUp'])->name('auth.signUp');
Route::post('auth/exchange-code', [AuthController::class, 'exchangeCode'])->name('auth.exchangeCode');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');

    Route::get('profiles', [ProfileController::class, 'index']);
    Route::get('profiles/{profile}', [ProfileController::class, 'show']);
    Route::post('profiles', [ProfileController::class, 'store']);
    Route::patch('profiles/{profile}', [ProfileController::class, 'update']);
    Route::delete('profiles/{profile}', [ProfileController::class, 'destroy']);
    Route::put('profiles/{profile}/validate', [ProfileController::class, 'validateProfile']);

    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::apiResource('skills', SkillController::class);

    Route::apiResource('categories', CategoryController::class);
});
