<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentChangeRequest;
use App\Models\DocumentChangeRequestImage;
use App\Models\DocumentType;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Models\User;
use App\Notifications\DocumentChangeRequestNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentChangeRequestController extends Controller
{
    private const DISK       = 'local';
    private const MAX_IMAGES = 5;

    // ═════════════════════════════════════════════════════════════════════════
    // PORTAL PROVEEDOR
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Estado de solicitudes de cambio del proveedor para un tipo de documento:
     * si hay una abierta, la versión personalizada vigente y la última respuesta.
     */
    public function providerShow(Request $request, int $documentTypeId): JsonResponse
    {
        $provider     = $this->resolveProvider($request);
        $documentType = DocumentType::findOrFail($documentTypeId);

        if (!$documentType->allows_change_requests) {
            return response()->json([
                'allows_change_requests' => false,
                'pending'                => null,
                'personalized'           => null,
                'last_resolved'          => null,
            ]);
        }

        $base = DocumentChangeRequest::where('provider_id', $provider->id)
            ->where('document_type_id', $documentType->id);

        $pending = (clone $base)->pending()->withCount('images')->first();

        $personalized = (clone $base)
            ->where('status', DocumentChangeRequest::STATUS_ATTENDED)
            ->whereNotNull('response_file_path')
            ->latest('attended_at')
            ->first();

        $lastResolved = (clone $base)
            ->whereIn('status', [DocumentChangeRequest::STATUS_ATTENDED, DocumentChangeRequest::STATUS_REJECTED])
            ->latest('attended_at')
            ->first();

        return response()->json([
            'allows_change_requests' => true,
            'pending' => $pending ? [
                'id'           => $pending->id,
                'description'  => $pending->description,
                'images_count' => $pending->images_count,
                'created_at'   => $pending->created_at,
            ] : null,
            'personalized' => $personalized ? [
                'id'          => $personalized->id,
                'filename'    => $personalized->response_original_filename,
                'attended_at' => $personalized->attended_at,
            ] : null,
            'last_resolved' => $lastResolved ? [
                'id'             => $lastResolved->id,
                'status'         => $lastResolved->status,
                'response_notes' => $lastResolved->response_notes,
                'attended_at'    => $lastResolved->attended_at,
            ] : null,
        ]);
    }

    /**
     * El proveedor solicita cambios a un convenio (texto + hasta 5 imágenes).
     */
    public function providerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|integer|exists:document_types,id',
            'description'      => 'required|string|min:10|max:3000',
            'images'           => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*'         => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'description.required' => 'Describe qué cambios necesitas.',
            'description.min'      => 'Describe los cambios con al menos 10 caracteres.',
            'description.max'      => 'La descripción no puede pasar de 3000 caracteres.',
            'images.max'           => 'Puedes adjuntar hasta ' . self::MAX_IMAGES . ' imágenes.',
            'images.*.image'       => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'images.*.mimes'       => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'images.*.max'         => 'Cada imagen puede pesar máximo 5 MB.',
        ]);

        $provider     = $this->resolveProvider($request);
        $documentType = DocumentType::findOrFail($validated['document_type_id']);

        if (!$documentType->allows_change_requests) {
            return response()->json(['message' => 'Este documento no admite solicitudes de cambio.'], 422);
        }

        $hasPending = DocumentChangeRequest::where('provider_id', $provider->id)
            ->where('document_type_id', $documentType->id)
            ->pending()
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Ya tienes una solicitud de cambios en revisión para este documento.',
            ], 422);
        }

        $alreadySubmitted = ProviderDocument::where('provider_id', $provider->id)
            ->where('document_type_id', $documentType->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($alreadySubmitted) {
            return response()->json([
                'message' => 'Ya cargaste este documento. Si necesitas modificarlo, contacta a Cuentas por Pagar.',
            ], 422);
        }

        $storedPaths = [];

        try {
            $changeRequest = DB::transaction(function () use ($request, $provider, $documentType, $validated, &$storedPaths) {
                $cr = DocumentChangeRequest::create([
                    'provider_id'      => $provider->id,
                    'document_type_id' => $documentType->id,
                    'requested_by'     => $request->user()->id,
                    'description'      => trim($validated['description']),
                    'status'           => DocumentChangeRequest::STATUS_PENDING,
                ]);

                foreach ($request->file('images', []) as $image) {
                    // Extensión deducida del contenido, no del nombre que manda el cliente
                    $name = Str::uuid() . '.' . strtolower($image->extension());
                    $path = $image->storeAs("change_requests/{$cr->id}", $name, self::DISK);

                    if (!$path) {
                        throw new \RuntimeException('No se pudo guardar una imagen.');
                    }
                    $storedPaths[] = $path;

                    $cr->images()->create([
                        'file_path'         => $path,
                        'original_filename' => Str::limit($image->getClientOriginalName(), 250, ''),
                        'mime_type'         => $image->getMimeType(),
                        'size_kb'           => max(1, (int) ceil($image->getSize() / 1024)),
                    ]);
                }

                return $cr;
            });
        } catch (QueryException $e) {
            Storage::disk(self::DISK)->delete($storedPaths);

            // 23505 = violación del índice único parcial (doble clic / dos pestañas)
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Ya tienes una solicitud de cambios en revisión para este documento.',
                ], 422);
            }

            Log::error('Error al registrar solicitud de cambios', ['provider_id' => $provider->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo registrar la solicitud. Intenta de nuevo.'], 500);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($storedPaths);
            Log::error('Error al registrar solicitud de cambios', ['provider_id' => $provider->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo registrar la solicitud. Intenta de nuevo.'], 500);
        }

        $changeRequest->load(['provider', 'documentType'])->loadCount('images');
        $this->notifyAccountsPayable($changeRequest);

        return response()->json([
            'message'        => 'Solicitud enviada. Te avisaremos por correo cuando tengamos la versión ajustada.',
            'change_request' => ['id' => $changeRequest->id, 'status' => $changeRequest->status],
        ], 201);
    }

    /**
     * Descarga de la versión personalizada del convenio (solo del propio proveedor).
     */
    public function providerDownloadPersonalized(Request $request, int $id)
    {
        $provider = $this->resolveProvider($request);

        $cr = DocumentChangeRequest::where('id', $id)
            ->where('provider_id', $provider->id)
            ->where('status', DocumentChangeRequest::STATUS_ATTENDED)
            ->whereNotNull('response_file_path')
            ->firstOrFail();

        abort_unless(Storage::disk(self::DISK)->exists($cr->response_file_path), 404, 'Archivo no encontrado');

        return Storage::disk(self::DISK)->download($cr->response_file_path, $cr->response_original_filename);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BANDEJA INTERNA (cuentas_por_pagar, admin, super_admin)
    // ═════════════════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,attended,rejected,all',
            'search' => 'nullable|string|max:100',
        ]);

        $status = $request->input('status', DocumentChangeRequest::STATUS_PENDING);

        $query = DocumentChangeRequest::with(['provider', 'documentType', 'attendedBy'])
            ->withCount('images');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $query->whereHas('provider', function ($q) use ($term) {
                $q->where('business_name', 'ilike', $term)
                  ->orWhere('rfc', 'ilike', $term)
                  ->orWhere('email', 'ilike', $term);
            });
        }

        $page = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(15)
            ->through(fn (DocumentChangeRequest $cr) => $this->serialize($cr));

        $counts = DocumentChangeRequest::selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(array_merge($page->toArray(), [
            'counts' => [
                'pending'  => (int) ($counts['pending'] ?? 0),
                'attended' => (int) ($counts['attended'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'all'      => (int) $counts->sum(),
            ],
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $cr = DocumentChangeRequest::with(['provider', 'documentType', 'attendedBy', 'images'])->findOrFail($id);

        return response()->json(['change_request' => $this->serialize($cr, true)]);
    }

    /**
     * Imagen adjunta (se muestra en línea en la bandeja).
     */
    public function downloadImage(int $id, int $imageId)
    {
        $image = DocumentChangeRequestImage::where('id', $imageId)
            ->where('change_request_id', $id)
            ->firstOrFail();

        abort_unless(Storage::disk(self::DISK)->exists($image->file_path), 404, 'Imagen no encontrada');

        return Storage::disk(self::DISK)->response($image->file_path, $image->original_filename, [
            'Content-Type'           => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=300',
        ]);
    }

    /**
     * Atender (con Word ajustado) o marcar como no procedente (con motivo).
     * Se usa POST porque PHP no lee multipart en PATCH/PUT.
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution'     => ['required', Rule::in([DocumentChangeRequest::STATUS_ATTENDED, DocumentChangeRequest::STATUS_REJECTED])],
            'file'           => 'required_if:resolution,attended|nullable|file|mimes:doc,docx,pdf|max:10240',
            'response_notes' => 'required_if:resolution,rejected|nullable|string|max:3000',
        ], [
            'file.required_if'           => 'Sube la versión ajustada del convenio.',
            'file.mimes'                 => 'La versión ajustada debe ser Word o PDF.',
            'file.max'                   => 'El archivo puede pesar máximo 10 MB.',
            'response_notes.required_if' => 'Explica al proveedor por qué no proceden los cambios.',
        ]);

        $cr = DocumentChangeRequest::findOrFail($id);

        if ($cr->status !== DocumentChangeRequest::STATUS_PENDING) {
            return response()->json(['message' => 'Esta solicitud ya fue atendida.'], 422);
        }

        $path     = null;
        $original = null;

        if ($validated['resolution'] === DocumentChangeRequest::STATUS_ATTENDED) {
            $file     = $request->file('file');
            $original = Str::limit($file->getClientOriginalName(), 250, '');
            $path     = $file->storeAs(
                "change_requests/{$cr->id}/response",
                Str::uuid() . '.' . strtolower($file->extension()),
                self::DISK
            );

            if (!$path) {
                return response()->json(['message' => 'No se pudo guardar el archivo. Intenta de nuevo.'], 500);
            }
        }

        // Update condicionado: si otra persona la atendió mientras tanto, no se pisa
        $updated = DocumentChangeRequest::where('id', $cr->id)
            ->where('status', DocumentChangeRequest::STATUS_PENDING)
            ->update([
                'status'                     => $validated['resolution'],
                'response_notes'             => isset($validated['response_notes']) ? trim($validated['response_notes']) : null,
                'response_file_path'         => $path,
                'response_original_filename' => $original,
                'attended_by'                => $request->user()->id,
                'attended_at'                => now(),
                'updated_at'                 => now(),
            ]);

        if (!$updated) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            return response()->json(['message' => 'Otra persona atendió esta solicitud mientras la revisabas.'], 409);
        }

        $cr->refresh()->load(['provider', 'documentType', 'attendedBy', 'images']);
        $this->notifyProvider($cr);

        return response()->json([
            'message'        => $cr->status === DocumentChangeRequest::STATUS_ATTENDED
                ? 'Versión ajustada enviada al proveedor.'
                : 'Solicitud marcada como no procedente. Se avisó al proveedor.',
            'change_request' => $this->serialize($cr, true),
        ]);
    }

    public function downloadResponseFile(int $id)
    {
        $cr = DocumentChangeRequest::whereNotNull('response_file_path')->findOrFail($id);

        abort_unless(Storage::disk(self::DISK)->exists($cr->response_file_path), 404, 'Archivo no encontrado');

        return Storage::disk(self::DISK)->download($cr->response_file_path, $cr->response_original_filename);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveProvider(Request $request): Provider
    {
        $provider = Provider::where('email', $request->user()->email)->first();
        abort_unless($provider, 404, 'Proveedor no encontrado');

        return $provider;
    }

    private function serialize(DocumentChangeRequest $cr, bool $withImages = false): array
    {
        $data = [
            'id'                => $cr->id,
            'status'            => $cr->status,
            'description'       => $cr->description,
            'response_notes'    => $cr->response_notes,
            'has_response_file' => !empty($cr->response_file_path),
            'response_filename' => $cr->response_original_filename,
            'images_count'      => $cr->images_count ?? ($cr->relationLoaded('images') ? $cr->images->count() : 0),
            'created_at'        => $cr->created_at,
            'attended_at'       => $cr->attended_at,
            'provider'          => $cr->provider ? [
                'id'            => $cr->provider->id,
                'business_name' => $cr->provider->business_name,
                'rfc'           => $cr->provider->rfc ?? null,
                'email'         => $cr->provider->email,
            ] : null,
            'document_type'     => $cr->documentType ? [
                'id'   => $cr->documentType->id,
                'name' => $cr->documentType->name,
                'code' => $cr->documentType->code,
            ] : null,
            'attended_by'       => $cr->attendedBy ? [
                'id'   => $cr->attendedBy->id,
                'name' => $cr->attendedBy->name,
            ] : null,
        ];

        if ($withImages) {
            $data['images'] = $cr->images->map(fn ($img) => [
                'id'                => $img->id,
                'original_filename' => $img->original_filename,
                'mime_type'         => $img->mime_type,
                'size_kb'           => $img->size_kb,
            ])->values();
        }

        return $data;
    }

    private function notifyAccountsPayable(DocumentChangeRequest $cr): void
    {
        try {
            $recipients = User::role('cuentas_por_pagar')->where('is_active', true)->get();

            if ($recipients->isEmpty()) {
                $recipients = User::role('finanzas')->where('is_active', true)->get();
            }

            foreach ($recipients as $user) {
                $user->notify(new DocumentChangeRequestNotification($cr, 'submitted'));
            }
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar a cuentas por pagar (solicitud de cambios)', [
                'change_request_id' => $cr->id,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    private function notifyProvider(DocumentChangeRequest $cr): void
    {
        try {
            $email = $cr->provider?->email;
            if (!$email) {
                return;
            }

            $notification = new DocumentChangeRequestNotification($cr, $cr->status);
            $user = User::where('email', $email)->first();

            // Proveedores dados de alta a mano pueden no tener cuenta users
            $user
                ? $user->notify($notification)
                : Notification::route('mail', $email)->notify($notification);
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar al proveedor (solicitud de cambios)', [
                'change_request_id' => $cr->id,
                'error'             => $e->getMessage(),
            ]);
        }
    }
}