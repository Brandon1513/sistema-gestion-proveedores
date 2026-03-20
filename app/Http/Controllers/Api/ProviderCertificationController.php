<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderCertification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProviderCertificationController extends Controller
{
    // ─── Vista interna (Admin/Calidad) ────────────────────────────────────────

    public function index(Provider $provider): JsonResponse
    {
        $certifications = $provider->certifications()
            ->with('validator:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['certifications' => $certifications]);
    }

    public function store(Request $request, Provider $provider): JsonResponse
    {
        $validated = $request->validate([
            'certification_type'   => 'required|string|max:255',
            'other_name'           => 'nullable|string|max:255',
            'certification_number' => 'nullable|string|max:255',
            'certifying_body'      => 'nullable|string|max:255',
            'issue_date'           => 'nullable|date',
            'expiry_date'          => 'nullable|date|after_or_equal:issue_date',
        ]);

        $certification = $provider->certifications()->create($validated);

        return response()->json(['message' => 'Certificación creada', 'certification' => $certification], 201);
    }

    public function update(Request $request, Provider $provider, ProviderCertification $certification): JsonResponse
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json(['message' => 'Certificación no encontrada'], 404);
        }

        $validated = $request->validate([
            'certification_type'   => 'required|string|max:255',
            'other_name'           => 'nullable|string|max:255',
            'certification_number' => 'nullable|string|max:255',
            'certifying_body'      => 'nullable|string|max:255',
            'issue_date'           => 'nullable|date',
            'expiry_date'          => 'nullable|date|after_or_equal:issue_date',
        ]);

        $certification->update($validated);

        return response()->json(['message' => 'Certificación actualizada', 'certification' => $certification]);
    }

    public function destroy(Provider $provider, ProviderCertification $certification): JsonResponse
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json(['message' => 'Certificación no encontrada'], 404);
        }

        $this->deleteFile($certification);
        $certification->delete();

        return response()->json(['message' => 'Certificación eliminada']);
    }

    // ─── Validar certificación (Calidad) ─────────────────────────────────────

    /**
     * POST /api/providers/{provider}/certifications/{certification}/validate
     */
    public function validate(Request $request, Provider $provider, ProviderCertification $certification): JsonResponse
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json(['message' => 'Certificación no encontrada'], 404);
        }

        $request->validate([
            'status'   => 'required|in:approved,rejected',
            'comments' => 'nullable|string|max:1000',
        ], [
            'status.required' => 'El estado es requerido',
            'status.in'       => 'Estado inválido',
        ]);

        if ($request->status === 'rejected' && !$request->comments) {
            return response()->json([
                'message' => 'Los comentarios son obligatorios al rechazar',
                'errors'  => ['comments' => ['Debes indicar el motivo del rechazo']],
            ], 422);
        }

        $certification->update([
            'status'              => $request->status,
            'validation_comments' => $request->comments,
            'validated_by'        => auth()->id(),
            'validated_at'        => now(),
        ]);

        return response()->json([
            'message'       => $request->status === 'approved' ? 'Certificación aprobada' : 'Certificación rechazada',
            'certification' => $certification->fresh(['validator']),
        ]);
    }

    // ─── Listar todas (vista global) ─────────────────────────────────────────

    public function globalIndex(Request $request): JsonResponse
    {
        $query = ProviderCertification::with([
            'provider:id,business_name,rfc,provider_type_id',
            'provider.providerType:id,name',
            'validator:id,name',
        ]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('certification_type', 'like', "%{$request->search}%")
                  ->orWhere('other_name', 'like', "%{$request->search}%")
                  ->orWhere('certifying_body', 'like', "%{$request->search}%")
                  ->orWhereHas('provider', fn($p) =>
                      $p->where('business_name', 'like', "%{$request->search}%")
                        ->orWhere('rfc', 'like', "%{$request->search}%")
                  );
            });
        }

        if ($request->provider_type_id) {
            $query->whereHas('provider', fn($p) =>
                $p->where('provider_type_id', $request->provider_type_id)
            );
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $certifications = $query->orderBy('expiry_date')->get();

        // Stats
        $stats = [
            'total'    => $certifications->count(),
            'pending'  => $certifications->where('status', 'pending')->count(),
            'approved' => $certifications->where('status', 'approved')->count(),
            'rejected' => $certifications->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'certifications' => $certifications,
            'stats'          => $stats,
        ]);
    }

    /**
     * GET /api/certifications/pending-count — para el badge del sidebar
     */
    public function pendingCount(): JsonResponse
    {
        $count = ProviderCertification::where('status', 'pending')->count();
        return response()->json(['pending_certifications' => $count]);
    }

    // ─── Portal del proveedor ─────────────────────────────────────────────────

    public function myIndex(Request $request): JsonResponse
    {
        $provider = $this->getAuthProvider($request);
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $certifications = $provider->certifications()
            ->with('validator:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'is_editable_by_provider' => $c->is_editable_by_provider,
            ]));

        return response()->json(['certifications' => $certifications]);
    }

    /**
     * Crear certificación con archivo — proveedor
     */
    public function myStore(Request $request): JsonResponse
    {
        $provider = $this->getAuthProvider($request);
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $request->validate([
            'certification_type'   => 'required|string|max:255',
            'other_name'           => 'nullable|string|max:255',
            'certification_number' => 'nullable|string|max:255',
            'certifying_body'      => 'nullable|string|max:255',
            'issue_date'           => 'required|date',
            'expiry_date'          => 'required|date|after_or_equal:issue_date',
            'file'                 => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'file.required' => 'Debes adjuntar el archivo de la certificación',
            'file.mimes'    => 'Solo se permiten archivos PDF e imágenes',
            'file.max'      => 'El archivo no debe superar los 10MB',
            'expiry_date.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la de emisión',
        ]);

        // Guardar archivo
        $file      = $request->file('file');
        $path      = $file->store("certifications/provider_{$provider->id}", 'private');
        $extension = strtolower($file->getClientOriginalExtension());
        $sizeKb    = round($file->getSize() / 1024);

        $certification = $provider->certifications()->create([
            'certification_type'   => $request->certification_type,
            'other_name'           => $request->other_name,
            'certification_number' => $request->certification_number,
            'certifying_body'      => $request->certifying_body,
            'issue_date'           => $request->issue_date,
            'expiry_date'          => $request->expiry_date,
            'status'               => 'pending',
            'file_path'            => $path,
            'file_name'            => $file->getClientOriginalName(),
            'file_size_kb'         => $sizeKb,
            'file_extension'       => $extension,
        ]);

        // ✅ Notificar a Calidad por email
        $this->notifyQualityTeam($provider, $certification, 'created');

        return response()->json([
            'message'       => 'Certificación enviada para revisión',
            'certification' => $certification,
        ], 201);
    }

    /**
     * Actualizar certificación — solo si está en pending
     */
    public function myUpdate(Request $request, $certificationId): JsonResponse
    {
        $provider = $this->getAuthProvider($request);
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $certification = ProviderCertification::where('id', $certificationId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        // ✅ Bloquear si ya fue validada o rechazada
        if (!$certification->is_editable_by_provider) {
            return response()->json([
                'message' => 'No puedes modificar una certificación que ya fue revisada por Calidad.',
            ], 403);
        }

        $request->validate([
            'certification_type'   => 'required|string|max:255',
            'other_name'           => 'nullable|string|max:255',
            'certification_number' => 'nullable|string|max:255',
            'certifying_body'      => 'nullable|string|max:255',
            'issue_date'           => 'required|date',
            'expiry_date'          => 'required|date|after_or_equal:issue_date',
            'file'                 => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $data = [
            'certification_type'   => $request->certification_type,
            'other_name'           => $request->other_name,
            'certification_number' => $request->certification_number,
            'certifying_body'      => $request->certifying_body,
            'issue_date'           => $request->issue_date,
            'expiry_date'          => $request->expiry_date,
            'status'               => 'pending', // ← vuelve a pending al modificar
            'validated_by'         => null,
            'validated_at'         => null,
            'validation_comments'  => null,
        ];

        // Si adjunta nuevo archivo
        if ($request->hasFile('file')) {
            $this->deleteFile($certification);
            $file = $request->file('file');
            $path = $file->store("certifications/provider_{$provider->id}", 'private');
            $data['file_path']      = $path;
            $data['file_name']      = $file->getClientOriginalName();
            $data['file_size_kb']   = round($file->getSize() / 1024);
            $data['file_extension'] = strtolower($file->getClientOriginalExtension());
        }

        $certification->update($data);

        // ✅ Notificar a Calidad
        $this->notifyQualityTeam($provider, $certification, 'updated');

        return response()->json([
            'message'       => 'Certificación actualizada y enviada a revisión',
            'certification' => $certification,
        ]);
    }

    /**
     * Eliminar certificación — solo si está en pending
     */
    public function myDestroy(Request $request, $certificationId): JsonResponse
    {
        $provider = $this->getAuthProvider($request);
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $certification = ProviderCertification::where('id', $certificationId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        // ✅ Bloquear si ya fue validada o rechazada
        if (!$certification->is_editable_by_provider) {
            return response()->json([
                'message' => 'No puedes eliminar una certificación que ya fue revisada por Calidad.',
            ], 403);
        }

        $this->deleteFile($certification);
        $certification->delete();

        return response()->json(['message' => 'Certificación eliminada correctamente']);
    }

    /**
     * Descargar archivo de certificación
     * GET /api/provider/certifications/{id}/download
     */
    public function myDownload(Request $request, $certificationId)
    {
        $provider = $this->getAuthProvider($request);
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $certification = ProviderCertification::where('id', $certificationId)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        if (!$certification->file_path || !Storage::disk('private')->exists($certification->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('private')->download($certification->file_path, $certification->file_name);
    }

    /**
     * Descargar archivo de certificación (vista interna Calidad/Admin)
     * GET /api/providers/{provider}/certifications/{certification}/download
     */
    public function download(Provider $provider, ProviderCertification $certification)
    {
        if ($certification->provider_id !== $provider->id) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        if (!$certification->file_path || !Storage::disk('private')->exists($certification->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('private')->download($certification->file_path, $certification->file_name);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function getAuthProvider(Request $request): ?Provider
    {
        return Provider::where('email', $request->user()->email)->first();
    }

    private function deleteFile(ProviderCertification $certification): void
    {
        if ($certification->file_path) {
            Storage::disk('private')->delete($certification->file_path);
        }
    }

    private function notifyQualityTeam(Provider $provider, ProviderCertification $cert, string $action): void
    {
        try {
            $qualityUsers = User::whereHas('roles', fn($q) =>
                $q->whereIn('name', ['calidad', 'admin', 'super_admin'])
            )->get();

            $certName   = $cert->certification_type === 'Otro' ? $cert->other_name : $cert->certification_type;
            $systemUrl  = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'))
                          . '/documents?tab=certifications';

            foreach ($qualityUsers as $user) {
                Mail::send('emails.certification-updated', [
                    'providerName'        => $provider->business_name,
                    'providerRfc'         => $provider->rfc,
                    'certificationName'   => $certName,
                    'certificationNumber' => $cert->certification_number,
                    'certifyingBody'      => $cert->certifying_body,
                    'expiryDate'          => $cert->expiry_date?->format('d/m/Y'),
                    'hasFile'             => (bool) $cert->file_path,
                    'action'              => $action,
                    'systemUrl'           => $systemUrl,
                ], function ($message) use ($user, $certName, $action) {
                    $message->to($user->email, $user->name)
                        ->subject(($action === 'created' ? '🆕 Nueva' : '✏️ Actualizada')
                            . " certificación pendiente: {$certName} — SGP DASAVENA");
                });
            }
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de certificación: ' . $e->getMessage());
        }
    }
}