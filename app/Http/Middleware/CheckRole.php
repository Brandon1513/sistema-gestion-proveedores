<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Verificar si el usuario está autenticado
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Obtener TODOS los roles del usuario — un usuario puede tener
        // varios a la vez (ej. ingeniero_alimentos + cuentas_por_pagar).
        // Maneja tanto role directo como roles de Spatie.
        $userRoleNames = [];

        if ($request->user()->role) {
            // Si tiene propiedad role directa (legado, un solo rol)
            $userRoleNames = [$request->user()->role];
        } elseif (method_exists($request->user(), 'roles')) {
            // Spatie Laravel Permission — puede tener varios roles
            $userRoles = $request->user()->roles;
            if ($userRoles && $userRoles->count() > 0) {
                $userRoleNames = $userRoles->pluck('name')->all();
            }
        }

        // Si no se pudo obtener ningún rol
        if (empty($userRoleNames)) {
            return response()->json([
                'message' => 'Usuario sin rol asignado',
            ], 403);
        }

        // Convertir a minúsculas para comparación case-insensitive
        $normalizedUserRoles = array_map('strtolower', $userRoleNames);
        $allowedRoles = array_map('strtolower', $roles);

        // Verificar si el usuario tiene AL MENOS UNO de los roles permitidos
        $hasAccess = !empty(array_intersect($normalizedUserRoles, $allowedRoles));

        if (!$hasAccess) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso',
                'required_roles' => $roles,
                'your_roles' => $userRoleNames,
            ], 403);
        }

        return $next($request);
    }
}