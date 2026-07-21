<?php

use App\Http\Controllers\Api\V1\{SkillController, ProfileController, AuthController};
use Illuminate\Support\Facades\Route;

Route::get('auth/linkedin/signup', [AuthController::class, 'signUp'])->name('auth.signUp');
Route::get('auth/{user}', [AuthController::class, 'show'])->name('auth.show');


Route::apiResource('skills', SkillController::class);

// Profile routes -> I set them like this to have more control over the permsissions and the actions that can be performed on profiles.
Route::get('/profiles', [ProfileController::class, 'index']);
Route::get('/profiles/{profile}', [ProfileController::class, 'show']);
Route::post('/profiles', [ProfileController::class, 'store']);
Route::put('/profiles/{profile}', [ProfileController::class, 'update']);
Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy']);
Route::put('/profiles/{profile}/validate', [ProfileController::class, 'validateProfile']);


Route::get('/debug-url', function () {
    return [
        'app_url_config' => config('app.url'),
        'request_url' => request()->fullUrl(),
        'request_scheme' => request()->getScheme(),
        'is_secure' => request()->isSecure(),
        'linkedin_redirect_config' => config('services.linkedin-openid.redirect'),
    ];
});
