<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NonConformityClosedMail;
use App\Mail\NonConformityEvidenceReviewedMail;
use App\Mail\NonConformityEvidenceSubmittedMail;
use App\Mail\NonConformityIssuedMail;
use App\Mail\NonConformityResponseSubmittedMail;
use App\Mail\NonConformityReviewedMail;
use App\Models\NonConformity;
use App\Models\NonConformityAction;
use App\Models\NonConformityEvidence;
use App\Models\NonConformityResponse;
use App\Models\NonConformityPhoto;
use App\Models\NonConformityType;
use App\Models\Provider;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Support\RichText;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * No Conformidades a proveedor (F-CAL-24).
 *  - Fase 1: Sección 1 (DASAVENA levanta la NC)
 *  - Fase 2: Sección 2 (el proveedor responde; el ingeniero acepta o pide otra vuelta)
 *  - Fase 3: Sección 3 (evidencia por acción, Cumple / No cumple, y cierre)
 *  - Fase 4: PDF del formato F-CAL-24 lleno
 *
 * Permisos:
 *  - Crear / editar / borrar: ingeniero_alimentos, admin, super_admin
 *  - Solo lectura: calidad, compras
 *  - Proveedor: solo sus NC, sin la acción inmediata (seguimiento interno)
 */
class NonConformityController extends Controller
{
    private const WITH = [
        'provider:id,business_name,rfc',
        'productService:id,name,type',
        'unit:id,name,abbreviation',
        'type:id,name',
        'createdBy:id,name',
        'closedBy:id,name',
        'photos:id,non_conformity_id,original_name',
        'latestResponse',
        'acceptedResponse.actions.evidences',
        'responses.actions.evidences.reviewedBy:id,name',
        'responses.reviewedBy:id,name',
    ];

    // ── Catálogos ─────────────────────────────────────────────────────────────

    public function types(): JsonResponse
    {
        return response()->json([
            'types'            => NonConformityType::active()->get(['id', 'name']),
            'display_statuses' => NonConformity::DISPLAY_STATUSES,
        ]);
    }

    /** Productos asignados al proveedor (solo de esos se puede levantar una NC) */
    public function providerProducts($providerId): JsonResponse
    {
        $provider = Provider::findOrFail($providerId);

        $products = $provider->productsServices()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['products_services.id', 'products_services.name', 'products_services.type']);

        return response()->json(['products' => $products]);
    }

    // ── Vista interna ─────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id'    => 'nullable|exists:providers,id',
            'type_id'        => 'nullable|exists:non_conformity_types,id',
            'display_status' => 'nullable|in:' . implode(',', NonConformity::DISPLAY_STATUSES),
            'year'           => 'nullable|integer',
            'search'         => 'nullable|string|max:100',
        ]);

        $query = NonConformity::with(self::WITH);

        if ($request->filled('provider_id'))    $query->where('provider_id', $request->provider_id);
        if ($request->filled('type_id'))        $query->where('non_conformity_type_id', $request->type_id);
        if ($request->filled('year'))           $query->where('folio_year', $request->year);
        if ($request->filled('display_status')) $query->displayStatus($request->display_status);
        if ($request->filled('search')) {
            $s = '%' . trim($request->search) . '%';
            $query->where(fn ($q) => $q->where('folio', 'ilike', $s)->orWhere('lot', 'ilike', $s)->orWhere('description', 'ilike', $s));
        }

        $items = $query->orderByDesc('folio_year')->orderByDesc('folio_sequence')->get();

        // Conteo por estado mostrado (sobre el resultado filtrado)
        $counts = collect(NonConformity::DISPLAY_STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $items->filter(fn ($nc) => $nc->display_status === $s)->count()]);

        return response()->json([
            'non_conformities' => $items->map(fn ($nc) => $this->format($nc)),
            'counts'           => $counts,
            'total'            => $items->count(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $nc = NonConformity::with(self::WITH)->findOrFail($id);
        return response()->json(['non_conformity' => $this->format($nc)]);
    }

    private function rules(): array
    {
        return [
            'provider_id'            => 'required|exists:providers,id',
            'product_service_id'     => 'required|exists:products_services,id',
            'lot'                    => 'nullable|string|max:100',
            'deviation_date'         => 'required|date|before_or_equal:today',
            'reception_date'         => 'nullable|date|before_or_equal:today',
            'quantity_involved'      => 'nullable|numeric|min:0',
            'unit_id'                => 'nullable|exists:units,id',
            'non_conformity_type_id' => 'required|exists:non_conformity_types,id',
            'description'            => 'required|string|max:50000', // HTML del editor
            'immediate_action'       => 'nullable|string|max:500',
            'immediate_action_date'  => 'nullable|date',
        ];
    }

    private function messages(): array
    {
        return [
            'provider_id.required'            => 'Selecciona el proveedor',
            'product_service_id.required'     => 'Selecciona el producto',
            'deviation_date.required'         => 'Indica la fecha de la desviación',
            'deviation_date.before_or_equal'  => 'La fecha de desviación no puede ser futura',
            'non_conformity_type_id.required' => 'Selecciona el tipo de desviación',
            'description.required'            => 'Describe la No Conformidad',
        ];
    }

    /**
     * Limpia el HTML del editor y valida que traiga texto real
     * (un editor vacío manda "<p></p>").
     */
    private function cleanRichText(array &$data, string $field, string $emptyMessage): ?JsonResponse
    {
        $data[$field] = RichText::sanitize($data[$field] ?? '');
        if (RichText::plainText($data[$field]) === '') {
            return response()->json(['message' => $emptyMessage, 'errors' => [$field => [$emptyMessage]]], 422);
        }
        return null;
    }

    /** El producto debe ser uno de los asignados al proveedor */
    private function productBelongsToProvider(int $providerId, int $productId): bool
    {
        return Provider::findOrFail($providerId)
            ->productsServices()
            ->whereKey($productId)
            ->exists();
    }

    /**
     * POST /non-conformities  (multipart: campos de la Sección 1 + photos[])
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'photos'   => 'nullable|array|max:' . NonConformity::MAX_PHOTOS,
            'photos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], $this->messages());

        if (!$this->productBelongsToProvider((int) $data['provider_id'], (int) $data['product_service_id'])) {
            return response()->json(['message' => 'El producto no está asignado a este proveedor'], 422);
        }
        if ($error = $this->cleanRichText($data, 'description', 'Describe la No Conformidad')) return $error;

        $issuedAt = Carbon::today();
        unset($data['photos']);

        // Reintento por si dos NC se crean al mismo tiempo y chocan en el folio
        $nc = null;
        for ($attempt = 1; $attempt <= 3 && !$nc; $attempt++) {
            try {
                $nc = DB::transaction(function () use ($data, $issuedAt) {
                    return NonConformity::create($data + NonConformity::nextFolio($issuedAt->year) + [
                        'status'            => NonConformity::STATUS_AWAITING_RESPONSE,
                        'issued_at'         => $issuedAt,
                        'response_due_date' => $issuedAt->copy()->addDays(NonConformity::RESPONSE_DAYS),
                        'created_by'        => auth()->id(),
                    ]);
                });
            } catch (QueryException $e) {
                if ($attempt === 3) throw $e;
            }
        }

        if ($request->hasFile('photos')) {
            $this->storePhotos($nc, $request->file('photos'));
        }

        $nc->load(self::WITH);
        $this->notifyProvider($nc);

        return response()->json([
            'message'         => "No Conformidad {$nc->folio} emitida y enviada al proveedor",
            'non_conformity'  => $this->format($nc),
        ], 201);
    }

    /**
     * PUT /non-conformities/{id} — solo mientras el proveedor no haya respondido
     */
    public function update(Request $request, $id): JsonResponse
    {
        $nc = NonConformity::findOrFail($id);
        if (!$nc->is_editable) {
            return response()->json(['message' => 'La No Conformidad ya no se puede editar porque el proveedor ya respondió'], 422);
        }

        $data = $request->validate($this->rules(), $this->messages());

        if (!$this->productBelongsToProvider((int) $data['provider_id'], (int) $data['product_service_id'])) {
            return response()->json(['message' => 'El producto no está asignado a este proveedor'], 422);
        }
        if ($error = $this->cleanRichText($data, 'description', 'Describe la No Conformidad')) return $error;

        $nc->update($data);

        return response()->json([
            'message'        => 'No Conformidad actualizada',
            'non_conformity' => $this->format($nc->fresh(self::WITH)),
        ]);
    }

    /**
     * DELETE /non-conformities/{id} — solo mientras el proveedor no haya respondido.
     * Borrado lógico: el folio no se reutiliza.
     */
    public function destroy($id): JsonResponse
    {
        $nc = NonConformity::findOrFail($id);
        if (!$nc->is_editable) {
            return response()->json(['message' => 'No se puede eliminar: el proveedor ya respondió'], 422);
        }
        $nc->delete();

        return response()->json(['message' => "No Conformidad {$nc->folio} eliminada"]);
    }

    // ── Fotos ─────────────────────────────────────────────────────────────────

    private function storePhotos(NonConformity $nc, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store("non-conformities/{$nc->id}", 'documents');
            $nc->photos()->create([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'size_kb'       => (int) round($file->getSize() / 1024),
                'uploaded_by'   => auth()->id(),
            ]);
        }
    }

    public function uploadPhotos(Request $request, $id): JsonResponse
    {
        $nc = NonConformity::findOrFail($id);
        if (!$nc->is_editable) {
            return response()->json(['message' => 'La No Conformidad ya no se puede editar'], 422);
        }

        $request->validate([
            'photos'   => 'required|array|min:1|max:' . NonConformity::MAX_PHOTOS,
            'photos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $current = $nc->photos()->count();
        if ($current + count($request->file('photos')) > NonConformity::MAX_PHOTOS) {
            $left = max(0, NonConformity::MAX_PHOTOS - $current);
            return response()->json(['message' => 'Máximo ' . NonConformity::MAX_PHOTOS . " fotos. Puedes agregar {$left} más."], 422);
        }

        $this->storePhotos($nc, $request->file('photos'));

        return response()->json([
            'message' => 'Fotos guardadas',
            'photos'  => $nc->photos()->get(['id', 'non_conformity_id', 'original_name']),
        ], 201);
    }

    public function deletePhoto($photoId): JsonResponse
    {
        $photo = NonConformityPhoto::with('nonConformity')->findOrFail($photoId);
        if (!$photo->nonConformity?->is_editable) {
            return response()->json(['message' => 'La No Conformidad ya no se puede editar'], 422);
        }

        if ($photo->path && Storage::disk('documents')->exists($photo->path)) {
            Storage::disk('documents')->delete($photo->path);
        }
        $photo->delete();

        return response()->json(['message' => 'Foto eliminada']);
    }

    /**
     * GET /non-conformity-photos/{photoId} — un proveedor solo ve fotos de sus NC
     */
    public function showPhoto(Request $request, $photoId)
    {
        $photo = NonConformityPhoto::with('nonConformity')->findOrFail($photoId);
        $nc    = $photo->nonConformity;
        $user  = $request->user();

        if (!$nc) return response()->json(['message' => 'Foto no encontrada'], 404);

        if ($user->hasRole('proveedor')) {
            $ownProviderId = Provider::where('email', $user->email)->value('id');
            if ((int) $ownProviderId !== (int) $nc->provider_id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        if (!Storage::disk('documents')->exists($photo->path)) {
            return response()->json(['message' => 'El archivo no existe en el servidor'], 404);
        }

        return Storage::disk('documents')->response($photo->path, $photo->original_name, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ── Portal del proveedor ──────────────────────────────────────────────────

    private function currentProvider(Request $request): Provider
    {
        return Provider::where('email', $request->user()->email)->firstOrFail();
    }

    public function myIndex(Request $request): JsonResponse
    {
        $provider = $this->currentProvider($request);

        $items = NonConformity::with(self::WITH)
            ->where('provider_id', $provider->id)
            ->orderByDesc('folio_year')->orderByDesc('folio_sequence')
            ->get();

        return response()->json([
            'non_conformities' => $items->map(fn ($nc) => $this->format($nc, true)),
        ]);
    }

    public function myShow(Request $request, $id): JsonResponse
    {
        $provider = $this->currentProvider($request);
        $nc = NonConformity::with(self::WITH)->where('provider_id', $provider->id)->findOrFail($id);

        return response()->json(['non_conformity' => $this->format($nc, true)]);
    }

    // ── Sección 2: respuesta del proveedor ───────────────────────────────────

    /**
     * POST /provider/non-conformities/{id}/responses
     * El proveedor envía (o reenvía) su análisis de causa raíz y acciones correctivas.
     * La primera vez arranca el plazo de evidencias, que ya no se mueve con las vueltas.
     */
    public function submitResponse(Request $request, $id): JsonResponse
    {
        $provider = $this->currentProvider($request);
        $nc = NonConformity::where('provider_id', $provider->id)->findOrFail($id);

        if (!$nc->can_respond) {
            return response()->json(['message' => 'Esta No Conformidad no está esperando su respuesta'], 422);
        }

        $data = $request->validate([
            'team_leader'                   => 'required|string|max:255',
            'area'                          => 'required|string|max:255',
            'root_cause'                    => 'required|string|max:100000', // HTML del editor
            'actions'                       => 'required|array|min:1|max:20',
            'actions.*.action'              => 'required|string|max:2000',
            'actions.*.responsible'         => 'required|string|max:255',
            'actions.*.commitment_date'     => 'required|date',
            'actions.*.completion_date'     => 'nullable|date',
            'actions.*.evidence_to_deliver' => 'required|string|max:2000',
        ], [
            'team_leader.required'                   => 'Indica el líder del equipo',
            'area.required'                          => 'Indica el área',
            'root_cause.required'                    => 'Describe la causa raíz',
            'actions.required'                       => 'Agrega al menos una acción correctiva',
            'actions.min'                            => 'Agrega al menos una acción correctiva',
            'actions.*.action.required'              => 'Cada acción correctiva necesita su descripción',
            'actions.*.responsible.required'         => 'Cada acción correctiva necesita un responsable',
            'actions.*.commitment_date.required'     => 'Cada acción correctiva necesita su fecha compromiso',
            'actions.*.evidence_to_deliver.required' => 'Indica la evidencia a entregar de cada acción',
        ]);
        if ($error = $this->cleanRichText($data, 'root_cause', 'Describe la causa raíz')) return $error;

        $response = DB::transaction(function () use ($nc, $data, $request) {
            $nc = NonConformity::whereKey($nc->id)->lockForUpdate()->first();
            $round = (int) $nc->responses()->max('round') + 1;

            $response = $nc->responses()->create([
                'round'         => $round,
                'team_leader'   => $data['team_leader'],
                'area'          => $data['area'],
                'root_cause'    => $data['root_cause'],
                'submitted_at'  => now(),
                'submitted_by'  => $request->user()->id,
                'review_status' => NonConformityResponse::REVIEW_PENDING,
            ]);

            foreach (array_values($data['actions']) as $i => $a) {
                $response->actions()->create([
                    'sort_order'          => $i + 1,
                    'action'              => $a['action'],
                    'responsible'         => $a['responsible'],
                    'commitment_date'     => $a['commitment_date'],
                    'completion_date'     => $a['completion_date'] ?? null,
                    'evidence_to_deliver' => $a['evidence_to_deliver'],
                ]);
            }

            // Primera respuesta: arranca el plazo de evidencias (no se mueve con las vueltas)
            $updates = ['status' => NonConformity::STATUS_PLAN_IN_REVIEW];
            if (!$nc->response_received_at) {
                $today = Carbon::today();
                $updates['response_received_at'] = $today;
                $updates['evidence_due_date']    = $today->copy()->addDays(NonConformity::EVIDENCE_DAYS);
            }
            $nc->update($updates);

            return $response;
        });

        $nc = $nc->fresh(self::WITH);
        $this->notifyResponseSubmitted($nc, $response);

        return response()->json([
            'message'        => 'Su respuesta fue enviada a DASAVENA para revisión',
            'non_conformity' => $this->format($nc, true),
        ], 201);
    }

    /**
     * POST /non-conformities/{id}/responses/{responseId}/review
     * El ingeniero acepta el plan o lo regresa al proveedor con comentario.
     * Regresarlo NO reinicia ningún plazo.
     */
    public function reviewResponse(Request $request, $id, $responseId): JsonResponse
    {
        $nc       = NonConformity::findOrFail($id);
        $response = $nc->responses()->whereKey($responseId)->firstOrFail();

        if ($response->review_status !== NonConformityResponse::REVIEW_PENDING) {
            return response()->json(['message' => 'Esta respuesta ya fue revisada'], 422);
        }

        $data = $request->validate([
            'decision' => 'required|in:accept,return',
            'comment'  => 'required_if:decision,return|nullable|string|max:5000',
        ], [
            'comment.required_if' => 'Escribe al proveedor qué debe corregir',
        ]);

        $accepted = $data['decision'] === 'accept';

        DB::transaction(function () use ($nc, $response, $data, $accepted) {
            $response->update([
                'review_status'  => $accepted ? NonConformityResponse::REVIEW_ACCEPTED : NonConformityResponse::REVIEW_RETURNED,
                'review_comment' => $data['comment'] ?? null,
                'reviewed_at'    => now(),
                'reviewed_by'    => auth()->id(),
            ]);

            $nc->update([
                'status' => $accepted ? NonConformity::STATUS_AWAITING_EVIDENCE : NonConformity::STATUS_PLAN_RETURNED,
            ]);
        });

        $nc = $nc->fresh(self::WITH);
        $this->notifyReviewed($nc, $response->fresh());

        return response()->json([
            'message'        => $accepted
                ? "Plan de {$nc->folio} aceptado. El proveedor ya puede entregar sus evidencias."
                : "Plan de {$nc->folio} regresado al proveedor para corrección.",
            'non_conformity' => $this->format($nc),
        ]);
    }

    // ── Sección 3: evidencias y cierre ───────────────────────────────────────

    /** Acción de la vuelta ACEPTADA de la NC (solo esas reciben evidencia) */
    private function acceptedAction(NonConformity $nc, $actionId): NonConformityAction
    {
        $accepted = $nc->acceptedResponse()->first();
        abort_if(!$accepted, 422, 'El plan de acción aún no ha sido aceptado');

        return $accepted->actions()->whereKey($actionId)->firstOrFail();
    }

    /**
     * POST /provider/non-conformities/{id}/actions/{actionId}/evidences  (multipart: file, comment)
     * Una evidencia por acción; si la anterior "No cumple", se sube un nuevo intento.
     */
    public function uploadEvidence(Request $request, $id, $actionId): JsonResponse
    {
        $provider = $this->currentProvider($request);
        $nc = NonConformity::where('provider_id', $provider->id)->findOrFail($id);

        if ($nc->status !== NonConformity::STATUS_AWAITING_EVIDENCE) {
            return response()->json(['message' => 'Esta No Conformidad no está esperando evidencias'], 422);
        }

        $action = $this->acceptedAction($nc, $actionId);
        if (!$action->can_upload_evidence) {
            return response()->json(['message' => 'Esta acción ya tiene una evidencia en revisión o aceptada'], 422);
        }

        $request->validate([
            'file'    => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:10240',
            'comment' => 'nullable|string|max:2000',
        ], [
            'file.required' => 'Selecciona el archivo de la evidencia',
            'file.mimes'    => 'Formatos permitidos: PDF, imagen, Word o Excel',
            'file.max'      => 'El archivo debe pesar menos de 10 MB',
        ]);

        $file = $request->file('file');

        $evidence = DB::transaction(function () use ($action, $file, $request, $nc) {
            $attempt = (int) $action->evidences()->max('attempt') + 1;
            $path = $file->store("non-conformities/{$nc->id}/evidences", 'documents');

            return $action->evidences()->create([
                'attempt'          => $attempt,
                'path'             => $path,
                'original_name'    => $file->getClientOriginalName(),
                'file_extension'   => strtolower($file->getClientOriginalExtension()),
                'size_kb'          => (int) round($file->getSize() / 1024),
                'provider_comment' => $request->input('comment'),
                'submitted_at'     => now(),
                'submitted_by'     => $request->user()->id,
                'review_status'    => NonConformityEvidence::REVIEW_PENDING,
            ]);
        });

        $nc = $nc->fresh(self::WITH);
        $this->notifyEvidenceSubmitted($nc, $action, $evidence);

        return response()->json([
            'message'        => 'Evidencia enviada a DASAVENA para revisión',
            'non_conformity' => $this->format($nc, true),
        ], 201);
    }

    /**
     * POST /non-conformities/{id}/evidences/{evidenceId}/review  { decision: accept|reject, comment }
     */
    public function reviewEvidence(Request $request, $id, $evidenceId): JsonResponse
    {
        $nc = NonConformity::findOrFail($id);
        if ($nc->status !== NonConformity::STATUS_AWAITING_EVIDENCE) {
            return response()->json(['message' => 'Esta No Conformidad no está en etapa de evidencias'], 422);
        }

        $evidence = NonConformityEvidence::with('action.response')->findOrFail($evidenceId);
        if ((int) $evidence->action?->response?->non_conformity_id !== (int) $nc->id) abort(404);

        if ($evidence->review_status !== NonConformityEvidence::REVIEW_PENDING) {
            return response()->json(['message' => 'Esta evidencia ya fue revisada'], 422);
        }

        $data = $request->validate([
            'decision' => 'required|in:accept,reject',
            'comment'  => 'required_if:decision,reject|nullable|string|max:2000',
        ], [
            'comment.required_if' => 'Indica al proveedor por qué la evidencia no cumple',
        ]);

        $evidence->update([
            'review_status'  => $data['decision'] === 'accept' ? NonConformityEvidence::REVIEW_ACCEPTED : NonConformityEvidence::REVIEW_REJECTED,
            'review_comment' => $data['comment'] ?? null,
            'reviewed_at'    => now(),
            'reviewed_by'    => auth()->id(),
        ]);

        $nc = $nc->fresh(self::WITH);
        $this->notifyEvidenceReviewed($nc, $evidence->fresh('action'));

        return response()->json([
            'message'        => $data['decision'] === 'accept'
                ? 'Evidencia marcada como Cumple'
                : 'Evidencia marcada como No cumple; el proveedor deberá enviar otra',
            'non_conformity' => $this->format($nc),
        ]);
    }

    /**
     * POST /non-conformities/{id}/close  { comment? }
     * Firma electrónica de cierre: usuario + fecha. Solo con todas las evidencias en "Cumple".
     */
    public function close(Request $request, $id): JsonResponse
    {
        $nc = NonConformity::with(self::WITH)->findOrFail($id);

        if (!$nc->can_close) {
            return response()->json(['message' => 'Para cerrar la No Conformidad, todas las acciones deben tener su evidencia en "Cumple"'], 422);
        }

        $data = $request->validate(['comment' => 'nullable|string|max:5000']);

        $nc->update([
            'status'          => NonConformity::STATUS_CLOSED,
            'closed_at'       => now(),
            'closed_by'       => auth()->id(),
            'closing_comment' => $data['comment'] ?? null,
        ]);

        $nc = $nc->fresh(self::WITH);
        $this->notifyClosed($nc);

        return response()->json([
            'message'        => "No Conformidad {$nc->folio} cerrada ({$nc->display_status})",
            'non_conformity' => $this->format($nc),
        ]);
    }

    /**
     * GET /non-conformity-evidences/{evidenceId} — descarga (internos y el proveedor dueño)
     */
    public function downloadEvidence(Request $request, $evidenceId)
    {
        $evidence = NonConformityEvidence::with('action.response.nonConformity')->findOrFail($evidenceId);
        $nc       = $evidence->action?->response?->nonConformity;
        if (!$nc) return response()->json(['message' => 'Evidencia no encontrada'], 404);

        $user = $request->user();
        if ($user->hasRole('proveedor')) {
            $ownProviderId = Provider::where('email', $user->email)->value('id');
            if ((int) $ownProviderId !== (int) $nc->provider_id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        if (!Storage::disk('documents')->exists($evidence->path)) {
            return response()->json(['message' => 'El archivo no existe en el servidor'], 404);
        }

        return Storage::disk('documents')->download($evidence->path, $evidence->original_name);
    }

    // ── PDF del formato F-CAL-24 ──────────────────────────────────────────────

    /** GET /non-conformities/{id}/pdf — internos */
    public function pdf($id)
    {
        return $this->renderPdf(NonConformity::with(self::WITH)->findOrFail($id));
    }

    /** GET /provider/non-conformities/{id}/pdf — solo las del proveedor */
    public function myPdf(Request $request, $id)
    {
        $provider = $this->currentProvider($request);
        return $this->renderPdf(NonConformity::with(self::WITH)->where('provider_id', $provider->id)->findOrFail($id));
    }

    /** Imagen del disco documents como data URI (dompdf no carga imágenes protegidas por URL) */
    private function imageDataUri(string $path): ?string
    {
        if (!Storage::disk('documents')->exists($path)) return null;
        $mime = Storage::disk('documents')->mimeType($path) ?: 'image/jpeg';
        if (!str_starts_with($mime, 'image/')) return null;
        return "data:{$mime};base64," . base64_encode(Storage::disk('documents')->get($path));
    }

    private function renderPdf(NonConformity $nc)
    {
        // Sección 2: la vuelta aceptada; si no hay, la más reciente (marcada "en revisión")
        $response = $nc->acceptedResponse
            ? $nc->responses->firstWhere('id', $nc->acceptedResponse->id)
            : $nc->responses->first();

        $logoPath = public_path('images/logo.png');

        $pdf = Pdf::loadView('pdf.non-conformity', [
            'nc'              => $nc,
            'format'          => [
                'name'     => NonConformity::FORMAT_NAME,
                'code'     => NonConformity::FORMAT_CODE,
                'revision' => NonConformity::FORMAT_REVISION,
                'issued'   => NonConformity::FORMAT_ISSUED,
            ],
            'logo'            => is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null,
            'descriptionHtml' => RichText::toHtml($nc->description),
            // Las fotos se consultan aparte: la carga normal (self::WITH) no trae la columna path
            'photos'          => $nc->photos()->get(['id', 'path'])
                ->map(fn ($p) => $p->path ? $this->imageDataUri($p->path) : null)
                ->filter()->values(),
            'response'        => $response,
            'responseIsFinal' => $response && $response->review_status === NonConformityResponse::REVIEW_ACCEPTED,
            'rootCauseHtml'   => $response ? RichText::toHtml($response->root_cause) : '',
            'responseDays'    => NonConformity::RESPONSE_DAYS,
            'evidenceDays'    => NonConformity::EVIDENCE_DAYS,
        ])->setPaper('letter');

        return $pdf->download("{$nc->folio} - " . NonConformity::FORMAT_CODE . '.pdf');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Correo al proveedor (usuario ligado por email); no truena si no tiene usuario */
    private function providerUser(NonConformity $nc): ?User
    {
        $email = Provider::whereKey($nc->provider_id)->value('email');
        return $email ? User::where('email', $email)->first() : null;
    }

    private function notifyEvidenceSubmitted(NonConformity $nc, NonConformityAction $action, NonConformityEvidence $evidence): void
    {
        try {
            NotificationDispatcher::notifyRoles(
                ['ingeniero_alimentos'],
                auth()->id(),
                'nc_evidence_submitted',
                "Evidencia para {$nc->folio}",
                ($nc->provider?->business_name ?? 'El proveedor') . " subió la evidencia de la acción {$action->sort_order}",
                ['non_conformity_id' => $nc->id, 'link' => '/non-conformities']
            );
            foreach (User::role('ingeniero_alimentos')->get() as $user) {
                Mail::to($user->email, $user->name)->queue(new NonConformityEvidenceSubmittedMail($nc, $action, $evidence));
            }
        } catch (\Exception $e) {
            Log::error("Error notificando evidencia de {$nc->folio}: " . $e->getMessage());
        }
    }

    private function notifyEvidenceReviewed(NonConformity $nc, NonConformityEvidence $evidence): void
    {
        try {
            if ($user = $this->providerUser($nc)) {
                Mail::to($user->email, $user->name)->queue(new NonConformityEvidenceReviewedMail($nc, $evidence));
            }
        } catch (\Exception $e) {
            Log::error("Error notificando revisión de evidencia de {$nc->folio}: " . $e->getMessage());
        }
    }

    private function notifyClosed(NonConformity $nc): void
    {
        try {
            if ($user = $this->providerUser($nc)) {
                Mail::to($user->email, $user->name)->queue(new NonConformityClosedMail($nc));
            }
        } catch (\Exception $e) {
            Log::error("Error notificando cierre de {$nc->folio}: " . $e->getMessage());
        }
    }

    /** Aviso a los ingenieros de alimentos: correo + campanita */
    private function notifyResponseSubmitted(NonConformity $nc, NonConformityResponse $response): void
    {
        try {
            $providerName = $nc->provider?->business_name ?? 'El proveedor';

            NotificationDispatcher::notifyRoles(
                ['ingeniero_alimentos'],
                auth()->id(),
                'nc_response_submitted',
                "Respuesta a {$nc->folio}",
                "{$providerName} envió su acción correctiva" . ($response->round > 1 ? " (vuelta {$response->round})" : ''),
                ['non_conformity_id' => $nc->id, 'link' => '/non-conformities']
            );

            foreach (User::role('ingeniero_alimentos')->get() as $user) {
                Mail::to($user->email, $user->name)->queue(new NonConformityResponseSubmittedMail($nc, $response));
            }
        } catch (\Exception $e) {
            Log::error("Error notificando la respuesta de {$nc->folio}: " . $e->getMessage());
        }
    }

    /** Aviso al proveedor del resultado de la revisión */
    private function notifyReviewed(NonConformity $nc, NonConformityResponse $response): void
    {
        try {
            $email = Provider::whereKey($nc->provider_id)->value('email');
            $user  = $email ? User::where('email', $email)->first() : null;
            if (!$user) return;

            Mail::to($user->email, $user->name)->queue(new NonConformityReviewedMail($nc, $response));
        } catch (\Exception $e) {
            Log::error("Error notificando la revisión de {$nc->folio} al proveedor: " . $e->getMessage());
        }
    }

    private function notifyProvider(NonConformity $nc): void
    {
        try {
            $user = User::where('email', $nc->provider?->email ?? Provider::whereKey($nc->provider_id)->value('email'))->first();
            if (!$user) {
                Log::warning("NC {$nc->folio}: el proveedor no tiene usuario, no se envió el correo");
                return;
            }
            Mail::to($user->email, $user->name)->queue(new NonConformityIssuedMail($nc));
        } catch (\Exception $e) {
            Log::error("Error notificando la NC {$nc->folio} al proveedor: " . $e->getMessage());
        }
    }

    private function format(NonConformity $nc, bool $forProvider = false): array
    {
        $data = [
            'id'                    => $nc->id,
            'folio'                 => $nc->folio,
            'provider'              => $nc->provider ? ['id' => $nc->provider->id, 'business_name' => $nc->provider->business_name, 'rfc' => $nc->provider->rfc] : null,
            'product'               => $nc->productService ? ['id' => $nc->productService->id, 'name' => $nc->productService->name] : null,
            'lot'                   => $nc->lot,
            'deviation_date'        => $nc->deviation_date?->format('Y-m-d'),
            'reception_date'        => $nc->reception_date?->format('Y-m-d'),
            'quantity_involved'     => $nc->quantity_involved,
            'unit'                  => $nc->unit ? ['id' => $nc->unit->id, 'abbreviation' => $nc->unit->abbreviation] : null,
            'type'                  => $nc->type ? ['id' => $nc->type->id, 'name' => $nc->type->name] : null,
            'description'           => $nc->description,
            'immediate_action'      => $nc->immediate_action,
            'immediate_action_date' => $nc->immediate_action_date?->format('Y-m-d'),
            'status'                => $nc->status,
            'display_status'        => $nc->display_status,
            'issued_at'             => $nc->issued_at?->format('Y-m-d'),
            'response_due_date'     => $nc->response_due_date?->format('Y-m-d'),
            'response_received_at'  => $nc->response_received_at?->format('Y-m-d'),
            'evidence_due_date'     => $nc->evidence_due_date?->format('Y-m-d'),
            'current_due_date'      => $nc->current_due_date?->format('Y-m-d'),
            'days_left'             => $nc->days_left,
            'is_overdue'            => $nc->is_overdue,
            'is_editable'           => $nc->is_editable,
            'closed_at'             => $nc->closed_at?->format('Y-m-d H:i'),
            'closed_by'             => $nc->closedBy?->name,
            'closing_comment'       => $nc->closing_comment,
            'created_by'            => $nc->createdBy?->name,
            'photos'                => $nc->photos->map(fn ($p) => ['id' => $p->id, 'original_name' => $p->original_name])->values(),
            // ── Sección 2 ──
            'stage_label'           => $nc->stage_label,
            'can_respond'           => $nc->can_respond,
            // ── Sección 3 ──
            'accepted_response_id'  => $nc->relationLoaded('acceptedResponse') ? $nc->acceptedResponse?->id : null,
            'evidence_progress'     => $nc->evidence_progress,
            'can_close'             => $nc->can_close,
            'responses'             => $nc->relationLoaded('responses')
                ? $nc->responses->map(fn ($r) => [
                    'id'             => $r->id,
                    'round'          => $r->round,
                    'team_leader'    => $r->team_leader,
                    'area'           => $r->area,
                    'root_cause'     => $r->root_cause,
                    'submitted_at'   => $r->submitted_at?->format('Y-m-d H:i'),
                    'review_status'  => $r->review_status,
                    'review_label'   => $r->review_label,
                    'review_comment' => $r->review_comment,
                    'reviewed_at'    => $r->reviewed_at?->format('Y-m-d H:i'),
                    'reviewed_by'    => $forProvider ? null : $r->reviewedBy?->name,
                    'actions'        => $r->actions->map(fn ($a) => [
                        'id'                  => $a->id,
                        'sort_order'          => $a->sort_order,
                        'action'              => $a->action,
                        'responsible'         => $a->responsible,
                        'commitment_date'     => $a->commitment_date?->format('Y-m-d'),
                        'completion_date'     => $a->completion_date?->format('Y-m-d'),
                        'evidence_to_deliver' => $a->evidence_to_deliver,
                        // ── Sección 3 ──
                        'evidence_status'     => $a->evidence_status,
                        'can_upload_evidence' => $a->can_upload_evidence,
                        'evidences'           => $a->relationLoaded('evidences')
                            ? $a->evidences->map(fn ($e) => [
                                'id'               => $e->id,
                                'attempt'          => $e->attempt,
                                'original_name'    => $e->original_name,
                                'file_extension'   => $e->file_extension,
                                'size_kb'          => $e->size_kb,
                                'provider_comment' => $e->provider_comment,
                                'submitted_at'     => $e->submitted_at?->format('Y-m-d H:i'),
                                'review_status'    => $e->review_status,
                                'review_label'     => $e->review_label,
                                'review_comment'   => $e->review_comment,
                                'reviewed_at'      => $e->reviewed_at?->format('Y-m-d H:i'),
                                'reviewed_by'      => $forProvider ? null : $e->reviewedBy?->name,
                            ])->values()
                            : [],
                    ])->values(),
                ])->values()
                : [],
        ];

        // El proveedor no ve el seguimiento interno de DASAVENA
        if ($forProvider) {
            unset($data['immediate_action'], $data['immediate_action_date'], $data['created_by'], $data['is_editable'], $data['can_close'], $data['closed_by']);
        }

        return $data;
    }
}