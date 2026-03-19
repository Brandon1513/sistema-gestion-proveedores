<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DocumentStatusController extends Controller
{
    /**
     * Resumen de estado documental por proveedor — para rol Compras
     * GET /api/documents/status
     */
    public function index(Request $request): JsonResponse
    {
        $query = Provider::with([
            'providerType:id,name',
            'documents' => fn($q) => $q->select(
                'id', 'provider_id', 'document_type_id', 'status', 'expiry_date'
            ),
        ]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('business_name', 'like', "%{$request->search}%")
                  ->orWhere('rfc', 'like', "%{$request->search}%");
            });
        }

        if ($request->provider_type_id) {
            $query->where('provider_type_id', $request->provider_type_id);
        }

        if ($request->status_filter) {
            $query->where('status', $request->status_filter);
        }

        $providers = $query->orderBy('business_name')->get();

        $now  = Carbon::now();
        $soon = Carbon::now()->addDays(30);

        $result = $providers->map(function ($provider) use ($now, $soon) {
            $docs = $provider->documents;

            $approved  = $docs->where('status', 'approved')->count();
            $pending   = $docs->where('status', 'pending')->count();
            $rejected  = $docs->where('status', 'rejected')->count();
            $total     = $docs->count();

            // Documentos por vencer (aprobados con expiry_date <= 30 días)
            $expiring = $docs->filter(fn($d) =>
                $d->status === 'approved' &&
                $d->expiry_date &&
                Carbon::parse($d->expiry_date)->lte($soon) &&
                Carbon::parse($d->expiry_date)->gte($now)
            );

            // Documentos vencidos
            $expired = $docs->filter(fn($d) =>
                $d->expiry_date &&
                Carbon::parse($d->expiry_date)->lt($now) &&
                $d->status === 'approved'
            );

            // Días al documento más urgente
            $mostUrgentDays = null;
            if ($expiring->isNotEmpty()) {
                $mostUrgentDays = $expiring->map(fn($d) =>
                    Carbon::now()->diffInDays(Carbon::parse($d->expiry_date), false)
                )->min();
            }

            return [
                'id'             => $provider->id,
                'business_name'  => $provider->business_name,
                'rfc'            => $provider->rfc,
                'status'         => $provider->status,
                'provider_type'  => $provider->providerType,
                'docs_summary'   => [
                    'total'    => $total,
                    'approved' => $approved,
                    'pending'  => $pending,
                    'rejected' => $rejected,
                    'expiring' => $expiring->count(),
                    'expired'  => $expired->count(),
                ],
                'most_urgent_days' => $mostUrgentDays,
                'has_issues'       => $pending > 0 || $rejected > 0 || $expiring->isNotEmpty() || $expired->isNotEmpty(),
            ];
        });

        // Ordenar: con issues primero, luego por urgencia
        $sorted = $result->sortByDesc('has_issues')
                         ->sortBy(fn($p) => $p['most_urgent_days'] ?? 999)
                         ->values();

        // Stats globales
        $stats = [
            'total_providers'    => $providers->count(),
            'with_expiring'      => $result->filter(fn($p) => $p['docs_summary']['expiring'] > 0)->count(),
            'with_pending'       => $result->filter(fn($p) => $p['docs_summary']['pending'] > 0)->count(),
            'with_rejected'      => $result->filter(fn($p) => $p['docs_summary']['rejected'] > 0)->count(),
        ];

        return response()->json([
            'providers' => $sorted,
            'stats'     => $stats,
        ]);
    }
}