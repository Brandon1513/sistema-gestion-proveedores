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

        // Obtener el rol del usuario
        // Maneja tanto role directo como roles de Spatie
        $userRole = null;
        
        if ($request->user()->role) {
            // Si tiene propiedad role directa
            $userRole = $request->user()->role;
        } elseif (method_exists($request->user(), 'roles')) {
            // Si usa Spatie Laravel Permission
            $userRoles = $request->user()->roles;
            if ($userRoles && $userRoles->count() > 0) {
                $userRole = $userRoles->first()->name;
            }
        }

        // Si no se pudo obtener el rol
        if (!$userRole) {
            return response()->json([
                'message' => 'Usuario sin rol asignado',
            ], 403);
        }

        // Convertir a minúsculas para comparación case-insensitive
        $userRole = strtolower($userRole);
        $allowedRoles = array_map('strtolower', $roles);

        // Verificar si el usuario tiene uno de los roles permitidos
        if (!in_array($userRole, $allowedRoles)) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso',
                'required_roles' => $roles,
                'your_role' => $request->user()->role ?? $request->user()->roles->first()->name ?? 'sin rol',
            ], 403);
        }

        return $next($request);
    }
}