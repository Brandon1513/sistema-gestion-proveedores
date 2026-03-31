<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::active()->get(['id','name','abbreviation']);
        return response()->json(['units' => $units]);
    }
}