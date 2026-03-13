<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentValidation;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityDashboardController extends Controller
{
    /**
     * Obtener estadísticas del dashboard de Calidad
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'month');
            
            // Calcular fechas según período
            $startDate = match($period) {
                'day' => Carbon::today(),
                'week' => Carbon::now()->startOfWeek(),
                'month' => Carbon::now()->startOfMonth(),
                default => Carbon::now()->startOfMonth(),
            };
            
            $endDate = Carbon::now();

            // 1. Total documentos validados en el período
            $totalValidated = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                ->count();

            // 2. Documentos aprobados vs rechazados
            $approved = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                ->where('action', 'approved')
                ->count();

            $rejected = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                ->where('action', 'rejected')
                ->count();

            // 3. Promedio de tiempo de validación (en horas)
            $avgValidationTime = 0;
            try {
                $avgTime = ProviderDocument::whereNotNull('status')
                    ->where('status', '!=', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours')
                    ->value('avg_hours');
                
                $avgValidationTime = $avgTime ? round($avgTime, 2) : 0;
            } catch (\Exception $e) {
                Log::warning('Error calculando tiempo promedio de validación: ' . $e->getMessage());
            }

            // 4. Top 5 proveedores con más rechazos
            $topRejected = [];
            try {
                $topRejected = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                    ->where('action', 'rejected')
                    ->join('provider_documents', 'document_validations.provider_document_id', '=', 'provider_documents.id')
                    ->join('providers', 'provider_documents.provider_id', '=', 'providers.id')
                    ->select('providers.id', 'providers.business_name', 'providers.rfc', DB::raw('count(*) as rejections'))
                    ->groupBy('providers.id', 'providers.business_name', 'providers.rfc')
                    ->orderByDesc('rejections')
                    ->limit(5)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('Error obteniendo top proveedores rechazados: ' . $e->getMessage());
            }

            // 5. Documentos pendientes
            $pendingDocuments = ProviderDocument::where('status', 'pending')->count();

            // 6. Distribución por tipo de documento
            $documentTypeDistribution = [];
            try {
                $documentTypeDistribution = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                    ->join('provider_documents', 'document_validations.provider_document_id', '=', 'provider_documents.id')
                    ->join('document_types', 'provider_documents.document_type_id', '=', 'document_types.id')
                    ->select('document_types.name', DB::raw('count(*) as total'))
                    ->groupBy('document_types.name')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('Error obteniendo distribución por tipo de documento: ' . $e->getMessage());
            }

            // 7. Tasa de aprobación
            $approvalRate = $totalValidated > 0 
                ? round(($approved / $totalValidated) * 100, 2) 
                : 0;

            // 8. Top 5 validadores
            $topValidators = [];
            try {
                $topValidators = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                    ->join('users', 'document_validations.validated_by', '=', 'users.id')
                    ->select('users.id', 'users.name', DB::raw('count(*) as validations'))
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('validations')
                    ->limit(5)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('Error obteniendo top validadores: ' . $e->getMessage());
            }

            return response()->json([
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'summary' => [
                    'total_validated' => $totalValidated,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'pending' => $pendingDocuments,
                    'approval_rate' => $approvalRate,
                    'avg_validation_time_hours' => $avgValidationTime,
                ],
                'charts' => [
                    'approval_distribution' => [
                        'approved' => $approved,
                        'rejected' => $rejected,
                    ],
                    'document_type_distribution' => $documentTypeDistribution,
                ],
                'rankings' => [
                    'top_rejected_providers' => $topRejected,
                    'top_validators' => $topValidators,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en QualityDashboardController@stats: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            // Devolver estructura vacía en caso de error
            return response()->json([
                'period' => $request->get('period', 'month'),
                'date_range' => [
                    'start' => Carbon::now()->startOfMonth()->toDateString(),
                    'end' => Carbon::now()->toDateString(),
                ],
                'summary' => [
                    'total_validated' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'pending' => 0,
                    'approval_rate' => 0,
                    'avg_validation_time_hours' => 0,
                ],
                'charts' => [
                    'approval_distribution' => [
                        'approved' => 0,
                        'rejected' => 0,
                    ],
                    'document_type_distribution' => [],
                ],
                'rankings' => [
                    'top_rejected_providers' => [],
                    'top_validators' => [],
                ],
            ]);
        }
    }

    /**
     * Obtener actividad reciente
     */
    public function recentActivity(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);

            $activities = DocumentValidation::with([
                    'providerDocument.provider',
                    'providerDocument.documentType',
                    'validatedBy'
                ])
                ->latest('validated_at')
                ->limit($limit)
                ->get()
                ->map(function ($validation) {
                    return [
                        'id' => $validation->id,
                        'action' => $validation->action,
                        'provider' => optional($validation->providerDocument)->provider->business_name ?? 'N/A',
                        'document_type' => optional($validation->providerDocument)->documentType->name ?? 'N/A',
                        'validator' => optional($validation->validatedBy)->name ?? 'N/A',
                        'comments' => $validation->comments,
                        'validated_at' => $validation->validated_at->diffForHumans(),
                        'validated_at_full' => $validation->validated_at->toDateTimeString(),
                    ];
                });

            return response()->json([
                'activities' => $activities,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en QualityDashboardController@recentActivity: ' . $e->getMessage());
            
            return response()->json([
                'activities' => [],
            ]);
        }
    }
}