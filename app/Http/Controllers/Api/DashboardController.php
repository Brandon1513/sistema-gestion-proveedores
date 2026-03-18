<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\ProviderInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Dashboard general
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        
        // Si es proveedor, mostrar su dashboard
        if ($user->hasRole('proveedor')) {
            return $this->providerDashboard();
        }
        
        // Dashboard para usuarios internos
        return $this->internalDashboard();
    }

    /**
     * Dashboard para usuarios internos (Compras, Calidad, Admin)
     */
    private function internalDashboard(): JsonResponse
    {
        // Estadísticas generales
        $stats = [
            'total_providers' => Provider::count(),
            'active_providers' => Provider::where('status', 'active')->count(),
            'pending_providers' => Provider::where('status', 'pending')->count(),
            'pending_documents' => ProviderDocument::where('status', 'pending')->count(),
            'expired_documents' => ProviderDocument::where('status', 'expired')->count(),
            'documents_expiring_soon' => ProviderDocument::expiringSoon(30)->count(),
            'pending_invitations' => ProviderInvitation::where('status', 'pending')->count(),
        ];

        // ✅ CORREGIDO: Proveedores por tipo
        $providersByType = Provider::select('provider_type_id', DB::raw('count(*) as total'))
            ->with('providerType:id,name')
            ->groupBy('provider_type_id')
            ->get()
            ->map(function ($item) {
                return [
                    'provider_type_id' => $item->provider_type_id,
                    'provider_type_name' => $item->providerType ? $item->providerType->name : 'Sin tipo',
                    'name' => $item->providerType ? $item->providerType->name : 'Sin tipo', // Fallback adicional
                    'total' => $item->total,
                ];
            });

        // Proveedores por estado
        $providersByStatus = Provider::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // Documentos pendientes recientes
        $recentPendingDocuments = ProviderDocument::with([
            'provider:id,business_name',
            'documentType:id,name',
            'uploadedBy:id,name'
        ])
        ->where('status', 'pending')
        ->latest()
        ->limit(10)
        ->get();

        // Documentos que vencen pronto
        $expiringDocuments = ProviderDocument::with([
            'provider:id,business_name',
            'documentType:id,name'
        ])
        ->where('expiry_date', '<=', Carbon::now()->addDays(30))
        ->where('expiry_date', '>=', Carbon::now())
        ->where('status', 'approved')
        ->orderBy('expiry_date')
        ->limit(10)
        ->get();

        // Actividad reciente (últimas validaciones)
        $recentActivity = \App\Models\DocumentValidation::with([
            'providerDocument.provider:id,business_name',
            'providerDocument.documentType:id,name',
            'validatedBy:id,name'
        ])
        ->latest('validated_at')
        ->limit(10)
        ->get();

        return response()->json([
            'stats' => $stats,
            'providers_by_type' => $providersByType,
            'providers_by_status' => $providersByStatus,
            'recent_pending_documents' => $recentPendingDocuments,
            'expiring_documents' => $expiringDocuments,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Dashboard para proveedores
     */
    private function providerDashboard(): JsonResponse
    {
        $user = auth()->user();
        
        // Obtener el proveedor asociado al usuario
        $provider = Provider::where('email', $user->email)->first();

        if (!$provider) {
            return response()->json([
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        // Documentos requeridos
        $requiredDocuments = $provider->providerType
            ->documentTypes()
            ->wherePivot('is_required', true)
            ->count();

        // Documentos aprobados
        $approvedDocuments = $provider->documents()
            ->where('status', 'approved')
            ->count();

        // Documentos pendientes
        $pendingDocuments = $provider->documents()
            ->where('status', 'pending')
            ->count();

        // Documentos rechazados
        $rejectedDocuments = $provider->documents()
            ->where('status', 'rejected')
            ->count();

        // Documentos que vencen pronto
        $expiringDocuments = $provider->documents()
            ->with('documentType')
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->where('expiry_date', '>=', Carbon::now())
            ->orderBy('expiry_date')
            ->get();

        // Documentos vencidos
        $expiredDocuments = $provider->documents()
            ->with('documentType')
            ->where('status', 'expired')
            ->get();

        // Porcentaje de completitud
        $completionPercentage = $requiredDocuments > 0 
            ? round(($approvedDocuments / $requiredDocuments) * 100, 2)
            : 0;

        // Actividad reciente (validaciones de sus documentos)
        $recentActivity = \App\Models\DocumentValidation::with([
            'providerDocument.documentType',
            'validatedBy:id,name'
        ])
        ->whereHas('providerDocument', function ($query) use ($provider) {
            $query->where('provider_id', $provider->id);
        })
        ->latest('validated_at')
        ->limit(10)
        ->get();

        return response()->json([
            'provider' => $provider,
            'stats' => [
                'required_documents' => $requiredDocuments,
                'approved_documents' => $approvedDocuments,
                'pending_documents' => $pendingDocuments,
                'rejected_documents' => $rejectedDocuments,
                'completion_percentage' => $completionPercentage,
            ],
            'expiring_documents' => $expiringDocuments,
            'expired_documents' => $expiredDocuments,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Estadísticas por período
     */
    public function statistics(): JsonResponse
    {
        // Proveedores registrados por mes (últimos 6 meses)
        $providersPerMonth = Provider::select(
            DB::raw('DATE_TRUNC(\'month\', created_at) as month'),
            DB::raw('count(*) as total')
        )
        ->where('created_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        // Documentos validados por mes
        $documentsPerMonth = \App\Models\DocumentValidation::select(
            DB::raw('DATE_TRUNC(\'month\', validated_at) as month'),
            DB::raw('count(*) as total')
        )
        ->where('validated_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        return response()->json([
            'providers_per_month' => $providersPerMonth,
            'documents_per_month' => $documentsPerMonth,
        ]);
    }
        /**
     * Todos los documentos próximos a vencer (sin límite, para exportación)
     * GET /api/documents/expiring?days=30
     */
    public function expiringDocuments(): JsonResponse
    {
        $days = request()->query('days', 30);
 
        $documents = ProviderDocument::with([
            'provider:id,business_name,rfc',
            'provider.providerType:id,name',
            'documentType:id,name',
        ])
        ->where('expiry_date', '<=', Carbon::now()->addDays((int) $days))
        ->where('expiry_date', '>=', Carbon::now()->subDays(1)) // incluir vencidos de ayer
        ->where('status', 'approved')
        ->orderBy('expiry_date')
        ->get();
 
        return response()->json([
            'documents' => $documents,
            'total'     => $documents->count(),
        ]);
    }
}