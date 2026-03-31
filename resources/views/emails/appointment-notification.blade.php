{{-- resources/views/emails/appointment-notification.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $action === 'scheduled' ? '📅 Nueva Cita Agendada' : '❌ Cita Cancelada' }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        @if($action === 'scheduled')
            <p>El equipo de Compras de DASAVENA ha agendado una cita para usted.</p>
        @else
            <p>Le informamos que la siguiente cita ha sido <strong>cancelada</strong>.</p>
        @endif
    </div>

    <div class="info-box">
        <div class="info-box-title">Detalles de la Cita</div>

        <div class="info-row">
            <div class="info-label">Tipo:</div>
            <div class="info-value"><strong>{{ $typeLabel }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Fecha:</div>
            <div class="info-value" style="text-transform: capitalize;"><strong>{{ $date }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Hora:</div>
            <div class="info-value"><strong>{{ $time }}</strong> hrs</div>
        </div>
        @if($notes)
        <div class="info-row">
            <div class="info-label">Observaciones:</div>
            <div class="info-value">{{ $notes }}</div>
        </div>
        @endif
        @if($hasAttachment)
        <div class="info-row">
            <div class="info-label">Documento adjunto:</div>
            <div class="info-value" style="color: #6A2C75;">
                Disponible en el portal para descarga
            </div>
        </div>
        @endif
    </div>

    @if($action === 'cancelled' && $cancelReason)
    <div class="alert alert-warning" style="border-left: 4px solid #dc2626; background: #fef2f2;">
        <strong>Motivo de cancelación:</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">{{ $cancelReason }}</p>
    </div>
    @endif

    @if($action === 'scheduled')
    <div class="alert" style="border-left: 4px solid #6A2C75; background: #F5F0F6; padding: 16px; border-radius: 6px; margin: 16px 0;">
        Por favor preséntese puntualmente en las instalaciones de DASAVENA con el vehículo y personal registrado.
        @if($hasAttachment)
        Descargue el documento adjunto desde su portal antes de la visita.
        @endif
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">
            📋 Ver mis Citas
        </a>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px;
                background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            Sistema de Gestión de Proveedores
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection