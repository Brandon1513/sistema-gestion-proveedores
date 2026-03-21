<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Listar usuarios (solo internos, no proveedores)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['super_admin', 'admin', 'compras', 'calidad']);
            });

        // Filtro por rol
        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filtro por búsqueda (nombre o email)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->filled('is_active')) {
        $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->latest()->paginate(15);

        return response()->json($users);
    }

    /**
     * Obtener un usuario específico
     */
    public function show($id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        // Verificar que no sea un proveedor
        if ($user->hasRole('proveedor')) {
            return response()->json([
                'message' => 'No se puede acceder a usuarios de tipo proveedor desde esta vista',
            ], 403);
        }

        return response()->json($user);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['super_admin', 'admin', 'compras', 'calidad'])],
            'is_active' => 'boolean',
        ]);

        try {
            // Crear usuario
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Asignar rol
            $user->assignRole($validated['role']);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user' => $user->load('roles'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un usuario
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Verificar que no sea un proveedor
        if ($user->hasRole('proveedor')) {
            return response()->json([
                'message' => 'No se puede editar usuarios de tipo proveedor desde esta vista',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'compras', 'calidad'])],
            'is_active' => 'boolean',
        ]);

        try {
            // Actualizar datos básicos
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'] ?? $user->is_active,
            ]);

            // Actualizar rol si cambió
            if ($user->roles->first()->name !== $validated['role']) {
                $user->syncRoles([$validated['role']]);
            }

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $user->load('roles'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar contraseña de un usuario
     */
    public function updatePassword(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            return response()->json([
                'message' => 'Contraseña actualizada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar/Desactivar un usuario
     */
    public function toggleStatus($id): JsonResponse
    {
        $user = User::findOrFail($id);

        // No permitir desactivar al super admin actual
        if ($user->id === auth()->id() && $user->hasRole('super_admin')) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta',
            ], 403);
        }

        try {
            $user->update([
                'is_active' => !$user->is_active,
            ]);

            return response()->json([
                'message' => $user->is_active ? 'Usuario activado' : 'Usuario desactivado',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar estado del usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un usuario
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        // No permitir eliminar al super admin actual
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta',
            ], 403);
        }

        // No permitir eliminar proveedores
        if ($user->hasRole('proveedor')) {
            return response()->json([
                'message' => 'No se puede eliminar usuarios de tipo proveedor desde esta vista',
            ], 403);
        }

        try {
            $user->delete();

            return response()->json([
                'message' => 'Usuario eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar roles disponibles
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::whereIn('name', ['super_admin', 'admin', 'compras', 'calidad'])
            ->select('id', 'name')
            ->get()
            ->map(function ($role) {
                return [
                    'value' => $role->name,
                    'label' => ucfirst(str_replace('_', ' ', $role->name)),
                ];
            });

        return response()->json($roles);
    }
}