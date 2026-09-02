<?php

use App\Http\Controllers\Api\MicrosoftAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'SGP API - DASAVENA', 'version' => '1.0']);
});

// ===============================
// LOGIN CON MICROSOFT (SSO empleados)
// Van en web.php (no api.php) porque Socialite necesita sesión/cookies
// para el "state" de OAuth durante el flujo de redirección.
// ===============================
Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);