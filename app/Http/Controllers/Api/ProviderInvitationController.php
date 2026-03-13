<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteProviderRequest;
use App\Mail\ProviderInvitation as ProviderInvitationMail;
use App\Models\ProviderInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProviderInvitationController extends Controller
{
    /**
     * Lista de invitaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProviderInvitation::with(['providerType', 'invitedBy', 'provider']);

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('provider_type_id')) {
            $query->where('provider_type_id', $request->provider_type_id);
        }

        $invitations = $query->latest()->paginate(15);

        return response()->json($invitations);
    }

    /**
     * Enviar invitación a proveedor
     */
    public function store(InviteProviderRequest $request): JsonResponse
    {
        try {
            // Verificar si ya existe una invitación pendiente para este email
            $existingInvitation = ProviderInvitation::where('email', $request->email)
                ->where('status', 'pending')
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'message' => 'Ya existe una invitación pendiente para este correo',
                ], 422);
            }

            // Crear invitación
            $invitation = ProviderInvitation::create([
                'email' => $request->email,
                'token' => ProviderInvitation::generateToken(),
                'provider_type_id' => $request->provider_type_id,
                'invited_by' => auth()->id(),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addDays(7),
            ]);

            // Enviar email con el link de invitación
            try {
                Mail::to($invitation->email)->send(new ProviderInvitationMail($invitation));
            } catch (\Exception $mailError) {
                // Si falla el envío del email, marcar como fallida pero no detener el proceso
                \Log::error('Error al enviar email de invitación: ' . $mailError->getMessage());
            }

            return response()->json([
                'message' => 'Invitación enviada exitosamente',
                'invitation' => $invitation->load(['providerType', 'invitedBy']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar invitación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar invitación por token
     */
    public function verify(string $token): JsonResponse
    {
        $invitation = ProviderInvitation::where('token', $token)
            ->with('providerType')
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitación no encontrada',
            ], 404);
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Esta invitación ya fue utilizada',
                'status' => $invitation->status,
            ], 400);
        }

        if ($invitation->is_expired) {
            $invitation->markAsExpired();
            return response()->json([
                'message' => 'Esta invitación ha expirado',
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'invitation' => [
                'email' => $invitation->email,
                'provider_type' => $invitation->providerType,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Reenviar invitación
     */
    public function resend(ProviderInvitation $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden reenviar invitaciones pendientes',
            ], 422);
        }

        // Extender fecha de expiración
        $invitation->update([
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // Reenviar email
        try {
            Mail::to($invitation->email)->send(new ProviderInvitationMail($invitation));
        } catch (\Exception $mailError) {
            \Log::error('Error al reenviar email de invitación: ' . $mailError->getMessage());
        }

        return response()->json([
            'message' => 'Invitación reenviada exitosamente',
            'invitation' => $invitation,
        ]);
    }

    /**
     * Cancelar invitación
     */
    public function cancel(ProviderInvitation $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden cancelar invitaciones pendientes',
            ], 422);
        }

        $invitation->update(['status' => 'expired']);

        return response()->json([
            'message' => 'Invitación cancelada exitosamente',
        ]);
    }
}