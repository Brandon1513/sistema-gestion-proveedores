<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'SGP API - DASAVENA', 'version' => '1.0']);
});

