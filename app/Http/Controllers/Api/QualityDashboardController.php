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
    public function stats(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'month');

            $startDate = match($period) {
                'day'   => Carbon::today(),
                'week'  => Carbon::now()->startOfWeek(),
                'month' => Carbon::now()->startOfMonth(),
                default => Carbon::now()->startOfMonth(),
            };
            $endDate = Carbon::now();

            $totalValidated = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])->count();

            $approved = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                ->where('action', 'approved')->count();

            $rejected = DocumentValidation::whereBetween('validated_at', [$startDate, $endDate])
                ->where('action', 'rejected')->count();

            // ✅ Fix PostgreSQL: EXTRACT EPOCH en lugar de TIMESTAMPDIFF
            $avgValidationTime = 0;
            try {
                $avgTime = ProviderDocument::whereNotNull('status')
                    ->where('status', '!=', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw("AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 3600) as avg_hours")
                    ->value('avg_hours');

                $avgValidationTime = $avgTime ? round($avgTime, 1) : 0;
            } catch (\Exception $e) {
                Log::warning('Error calculando tiempo promedio: ' . $e->getMessage());
            }

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
                Log::warning('Error obteniendo top rechazados: ' . $e->getMessage());
            }

            $pendingDocuments = ProviderDocument::where('status', 'pending')->count();

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
                Log::warning('Error obteniendo distribución por tipo: ' . $e->getMessage());
            }

            $approvalRate = $totalValidated > 0
                ? round(($approved / $totalValidated) * 100, 1)
                : 0;

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
                'period'     => $period,
                'date_range' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'summary'    => [
                    'total_validated'          => $totalValidated,
                    'approved'                 => $approved,
                    'rejected'                 => $rejected,
                    'pending'                  => $pendingDocuments,
                    'approval_rate'            => $approvalRate,
                    'avg_validation_time_hours'=> $avgValidationTime,
                ],
                'charts'  => [
                    'approval_distribution'      => ['approved' => $approved, 'rejected' => $rejected],
                    'document_type_distribution' => $documentTypeDistribution,
                ],
                'rankings' => [
                    'top_rejected_providers' => $topRejected,
                    'top_validators'         => $topValidators,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en QualityDashboardController@stats: ' . $e->getMessage());
            return response()->json([
                'period'     => $request->get('period', 'month'),
                'date_range' => ['start' => Carbon::now()->startOfMonth()->toDateString(), 'end' => Carbon::now()->toDateString()],
                'summary'    => ['total_validated'=>0,'approved'=>0,'rejected'=>0,'pending'=>0,'approval_rate'=>0,'avg_validation_time_hours'=>0],
                'charts'     => ['approval_distribution'=>['approved'=>0,'rejected'=>0],'document_type_distribution'=>[]],
                'rankings'   => ['top_rejected_providers'=>[],'top_validators'=>[]],
            ]);
        }
    }

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
                ->map(fn($v) => [
                    'id'               => $v->id,
                    'action'           => $v->action,
                    'provider'         => optional($v->providerDocument)->provider->business_name ?? 'N/A',
                    'document_type'    => optional($v->providerDocument)->documentType->name ?? 'N/A',
                    'validator'        => optional($v->validatedBy)->name ?? 'N/A',
                    'comments'         => $v->comments,
                    'validated_at'     => $v->validated_at->diffForHumans(),
                    'validated_at_full'=> $v->validated_at->toDateTimeString(),
                ]);

            return response()->json(['activities' => $activities]);

        } catch (\Exception $e) {
            Log::error('Error en QualityDashboardController@recentActivity: ' . $e->getMessage());
            return response()->json(['activities' => []]);
        }
    }
}