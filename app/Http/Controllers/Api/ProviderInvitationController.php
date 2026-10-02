<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteProviderRequest;
use App\Mail\ProviderInvitation as ProviderInvitationMail;
use App\Models\ProviderInvitation;
use App\Models\User;
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
            $existingInvitation = ProviderInvitation::where('email', $request->email)
                ->where('status', 'pending')
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'message' => 'Ya existe una invitación pendiente para este correo',
                ], 422);
            }

            // ── Capa 1: alerta si ya existe una CUENTA (User) con login
            // funcional para este correo. No alertamos si solo existe el
            // registro Provider sin cuenta todavía — eso es el flujo normal
            // cuando se da de alta manual y se invita después para que
            // complete su acceso.
            $existingUser = User::where('email', $request->email)->first();

            if ($existingUser && !$request->boolean('confirm_send_anyway')) {
                return response()->json([
                    'message' => 'Ya existe una cuenta activa con este correo. El proveedor puede iniciar sesión directamente sin necesitar esta invitación.',
                    'code' => 'EMAIL_HAS_ACCOUNT',
                    'existing_account' => [
                        'name' => $existingUser->name,
                        'is_active' => (bool) $existingUser->is_active,
                    ],
                ], 409);
            }

            $invitation = ProviderInvitation::create([
                'email' => $request->email,
                'token' => ProviderInvitation::generateToken(),
                'provider_type_id' => $request->provider_type_id,
                'invited_by' => auth()->id(),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addDays(7),
            ]);

            try {
                Mail::to($invitation->email)->send(new ProviderInvitationMail($invitation));
            } catch (\Exception $mailError) {
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

        // ── Capa 2: si ya existe una cuenta (User) con login para este
        // correo, lo decimos explícitamente para que el frontend muestre
        // "ya tienes cuenta, inicia sesión" en vez del formulario completo.
        $existingUser = User::where('email', $invitation->email)->first();

        $existingProvider = \App\Models\Provider::where('email', $invitation->email)->first();

        return response()->json([
            'valid' => true,
            'invitation' => [
                'email' => $invitation->email,
                'provider_type' => $invitation->providerType,
                'expires_at' => $invitation->expires_at,
            ],
            // Ya hay cuenta con login — el frontend debe ofrecer
            // "Iniciar sesión" / "Restablecer contraseña" en vez del form.
            'existing_account' => $existingUser ? [
                'name' => $existingUser->name,
                'is_active' => (bool) $existingUser->is_active,
            ] : null,
            'existing_provider' => $existingProvider ? [
                'business_name'        => $existingProvider->business_name,
                'rfc'                  => $existingProvider->rfc,
                'tipo_persona'         => $existingProvider->tipo_persona,
                'legal_representative' => $existingProvider->legal_representative,
                'phone'                => $existingProvider->phone,
                'street'               => $existingProvider->street,
                'exterior_number'      => $existingProvider->exterior_number,
                'interior_number'      => $existingProvider->interior_number,
                'neighborhood'         => $existingProvider->neighborhood,
                'city'                 => $existingProvider->city,
                'state'                => $existingProvider->state,
                'postal_code'          => $existingProvider->postal_code,
                'bank'                 => $existingProvider->bank,
                'bank_branch'          => $existingProvider->bank_branch,
                'account_number'       => $existingProvider->account_number,
                'clabe'                => $existingProvider->clabe,
                'credit_amount'        => $existingProvider->credit_amount,
                'credit_days'          => $existingProvider->credit_days,
                'observations'         => $existingProvider->observations,
            ] : null,
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

        $invitation->update([
            'expires_at' => Carbon::now()->addDays(7),
        ]);

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