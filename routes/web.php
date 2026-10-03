<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', fn () => view('admin-login'))->name('login');


Route::post('/admin/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials)) {
        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    $request->session()->regenerate();

    return redirect()->intended('/telescope');
})->middleware('throttle:5, 1');

Route::post('/admin/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth');
