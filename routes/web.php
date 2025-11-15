<?php

use App\Http\Controllers\Api\BroadcastingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

// Redirect root to login
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('wallet');
    }

    return redirect()->route('login');
});

// Login routes
Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('wallet');
    }

    return view('login');
})->name('login');

Route::post('/login', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if ($validator->fails()) {
        return redirect()->route('login')
            ->withErrors($validator)
            ->withInput($request->except('password'));
    }

    $credentials = $request->only('email', 'password');
    $remember = $request->filled('remember');

    if (Auth::attempt($credentials, $remember)) {
        $request->session()->regenerate();

        return redirect()->intended(route('wallet'));
    }

    return redirect()->route('login')
        ->with('error', 'Invalid email or password.')
        ->withInput($request->except('password'));
})->name('login.post');

// Logout route
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// Broadcasting authentication routes
// Use custom controller to ensure JSON responses
// Note: We handle authentication in the controller to support both Bearer tokens and session-based auth
// The controller will check for Sanctum token first, then fall back to session auth
Route::post('/broadcasting/auth', [BroadcastingController::class, 'authenticate'])
    ->middleware(['web']); // Web middleware for session support, but auth is handled in controller

// Wallet route (requires authentication)
Route::middleware('auth')->get('/wallet', function (Request $request) {
    // Create a token for API calls
    $token = $request->user()->createToken('wallet-token')->plainTextToken;

    return view('wallet', ['token' => $token]);
})->name('wallet');
