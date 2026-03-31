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
    // ✅ Roles internos permitidos — un solo lugar para mantener
    private const INTERNAL_ROLES = [
        'super_admin',
        'admin',
        'compras',
        'calidad',
        'seguridad',
        'ingeniero_alimentos',
    ];

    private const ROLE_LABELS = [
        'super_admin'          => 'Super Administrador',
        'admin'                => 'Administrador',
        'compras'              => 'Compras',
        'calidad'              => 'Calidad',
        'seguridad'            => 'Seguridad',
        'ingeniero_alimentos'  => 'Ingeniero de Alimentos',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', self::INTERNAL_ROLES);
            });

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show($id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        if ($user->hasRole('proveedor')) {
            return response()->json(['message' => 'No se puede acceder a usuarios de tipo proveedor desde esta vista'], 403);
        }

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'role'      => ['required', Rule::in(self::INTERNAL_ROLES)],
            'is_active' => 'boolean',
        ]);

        try {
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $user->assignRole($validated['role']);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user'    => $user->load('roles'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear usuario', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->hasRole('proveedor')) {
            return response()->json(['message' => 'No se puede editar usuarios de tipo proveedor desde esta vista'], 403);
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'      => ['required', Rule::in(self::INTERNAL_ROLES)],
            'is_active' => 'boolean',
        ]);

        try {
            $user->update([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'is_active' => $validated['is_active'] ?? $user->is_active,
            ]);

            if ($user->roles->first()?->name !== $validated['role']) {
                $user->syncRoles([$validated['role']]);
            }

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user'    => $user->load('roles'),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar usuario', 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePassword(Request $request, $id): JsonResponse
    {
        $user      = User::findOrFail($id);
        $validated = $request->validate(['password' => 'required|string|min:8|confirmed']);

        try {
            $user->update(['password' => Hash::make($validated['password'])]);
            return response()->json(['message' => 'Contraseña actualizada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar contraseña', 'error' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id() && $user->hasRole('super_admin')) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta'], 403);
        }

        try {
            $user->update(['is_active' => !$user->is_active]);
            return response()->json([
                'message' => $user->is_active ? 'Usuario activado' : 'Usuario desactivado',
                'user'    => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cambiar estado', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta'], 403);
        }
        if ($user->hasRole('proveedor')) {
            return response()->json(['message' => 'No se puede eliminar usuarios de tipo proveedor desde esta vista'], 403);
        }

        try {
            $user->delete();
            return response()->json(['message' => 'Usuario eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar usuario', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar roles disponibles para el selector
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::whereIn('name', self::INTERNAL_ROLES)
            ->select('id', 'name')
            ->get()
            ->map(fn($role) => [
                'value' => $role->name,
                'label' => self::ROLE_LABELS[$role->name] ?? ucfirst(str_replace('_', ' ', $role->name)),
            ]);

        return response()->json($roles);
    }
}