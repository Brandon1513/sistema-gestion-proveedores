<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProviderInvitation as ProviderInvitationMail;
use App\Models\ProviderInvitation;
use App\Models\ProviderRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ProviderRequestController extends Controller
{
    /**
     * Crear una solicitud de alta de proveedor.
     * Rol: emp_solicitante (y admin/compras si quieren solicitar también)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id'           => 'required|exists:departments,id',
            'provider_type_id'        => 'required|exists:provider_types,id',
            'provider_business_name'  => 'required|string|max:255',
            'provider_contact_name'   => 'required|string|max:255',
            'provider_contact_phone'  => 'required|string|max:20',
            'provider_contact_email'  => 'required|email|max:255',
            'notes'                   => 'nullable|string|max:2000',
        ], [
            'department_id.required'          => 'El departamento es obligatorio',
            'provider_type_id.required'        => 'El tipo de proveedor es obligatorio',
            'provider_business_name.required'  => 'La razón social del proveedor es obligatoria',
            'provider_contact_name.required'   => 'El nombre de contacto del proveedor es obligatorio',
            'provider_contact_phone.required'  => 'El teléfono de contacto es obligatorio',
            'provider_contact_email.required'  => 'El correo de contacto es obligatorio',
        ]);

        $providerRequest = ProviderRequest::create([
            ...$validated,
            'requested_by' => $request->user()->id,
            'status'       => 'pending',
        ]);

        return response()->json([
            'message'          => 'Solicitud enviada correctamente. El equipo de Compras la revisará.',
            'provider_request' => $providerRequest->load(['department', 'providerType']),
        ], 201);
    }

    /**
     * Mis solicitudes (rol emp_solicitante — ve solo las suyas).
     */
    public function myIndex(Request $request): JsonResponse
    {
        $requests = ProviderRequest::with(['department', 'providerType', 'provider:id,business_name,status', 'processedBy:id,name'])
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'requests' => $requests->map(fn($r) => $this->format($r)),
        ]);
    }

    /**
     * Todas las solicitudes (Compras/Admin).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProviderRequest::with([
            'department', 'providerType', 'requestedBy:id,name,email',
            'provider:id,business_name,status', 'processedBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $requests = $query->latest()->get();

        return response()->json([
            'requests' => $requests->map(fn($r) => $this->format($r)),
            'stats' => [
                'pending' => ProviderRequest::pending()->count(),
            ],
        ]);
    }

    /**
     * Aprobar solicitud → envía la invitación al proveedor (mismo flujo
     * que ya usa Compras hoy para invitaciones sueltas).
     */
    public function approve(Request $request, ProviderRequest $providerRequest): JsonResponse
    {
        if ($providerRequest->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden aprobar solicitudes pendientes'], 422);
        }

        // Evitar invitaciones pendientes duplicadas para el mismo correo
        ProviderInvitation::where('email', $providerRequest->provider_contact_email)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $invitation = ProviderInvitation::create([
            'email'                => $providerRequest->provider_contact_email,
            'token'                => ProviderInvitation::generateToken(),
            'provider_type_id'     => $providerRequest->provider_type_id,
            'provider_request_id'  => $providerRequest->id,
            'invited_by'           => $request->user()->id,
            'status'               => 'pending',
            'expires_at'           => Carbon::now()->addDays(7),
        ]);

        try {
            Mail::to($invitation->email)->send(new ProviderInvitationMail($invitation));
        } catch (\Exception $mailError) {
            \Log::warning('Solicitud aprobada pero no se pudo enviar invitación: ' . $mailError->getMessage());
        }

        $providerRequest->update([
            'status'                 => 'invited',
            'provider_invitation_id' => $invitation->id,
            'processed_by'           => $request->user()->id,
            'processed_at'           => now(),
        ]);

        return response()->json([
            'message'          => 'Solicitud aprobada. Se envió la invitación al proveedor.',
            'provider_request' => $this->format($providerRequest->fresh(['department', 'providerType', 'invitation'])),
        ]);
    }

    /**
     * Rechazar solicitud.
     */
    public function reject(Request $request, ProviderRequest $providerRequest): JsonResponse
    {
        if ($providerRequest->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden rechazar solicitudes pendientes'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [
            'rejection_reason.required' => 'Debes indicar el motivo del rechazo',
        ]);

        $providerRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'processed_by'     => $request->user()->id,
            'processed_at'     => now(),
        ]);

        return response()->json([
            'message'          => 'Solicitud rechazada',
            'provider_request' => $this->format($providerRequest->fresh(['department', 'providerType'])),
        ]);
    }

    private function format(ProviderRequest $r): array
    {
        return [
            'id'                      => $r->id,
            'department'              => $r->department ? ['id' => $r->department->id, 'name' => $r->department->name] : null,
            'provider_type'           => $r->providerType ? ['id' => $r->providerType->id, 'name' => $r->providerType->name] : null,
            'provider_business_name'  => $r->provider_business_name,
            'provider_contact_name'   => $r->provider_contact_name,
            'provider_contact_phone'  => $r->provider_contact_phone,
            'provider_contact_email'  => $r->provider_contact_email,
            'notes'                   => $r->notes,
            'status'                  => $r->status,
            'status_label'            => $r->status_label,
            'requested_by'            => $r->relationLoaded('requestedBy') && $r->requestedBy ? ['id' => $r->requestedBy->id, 'name' => $r->requestedBy->name] : null,
            'provider'                => $r->provider ? ['id' => $r->provider->id, 'business_name' => $r->provider->business_name, 'status' => $r->provider->status] : null,
            'processed_by'            => $r->processedBy?->name,
            'processed_at'            => $r->processed_at?->format('Y-m-d H:i'),
            'rejection_reason'        => $r->rejection_reason,
            'created_at'              => $r->created_at->format('Y-m-d H:i'),
        ];
    }
}