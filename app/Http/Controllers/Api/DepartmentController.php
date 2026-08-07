<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::query();

        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        return response()->json([
            'departments' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message'    => 'Departamento creado correctamente',
            'department' => $department,
        ], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255|unique:departments,name,' . $department->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $department->update($validated);

        return response()->json([
            'message'    => 'Departamento actualizado correctamente',
            'department' => $department,
        ]);
    }
}