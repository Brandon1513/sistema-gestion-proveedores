{{-- resources/views/emails/certification-validated.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $status === 'approved' ? ' Certificación Aprobada' : ' Certificación Rechazada' }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>
            El equipo de Calidad de DASAVENA ha
            <strong>{{ $status === 'approved' ? 'aprobado' : 'rechazado' }}</strong>
            su certificación <strong>{{ $certificationName }}</strong>.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Detalles de la Certificación</div>

        <div class="info-row">
            <div class="info-label">Certificación:</div>
            <div class="info-value"><strong>{{ $certificationName }}</strong></div>
        </div>

        @if($certifyingBody)
        <div class="info-row">
            <div class="info-label">Organismo Certificador:</div>
            <div class="info-value">{{ $certifyingBody }}</div>
        </div>
        @endif

        @if($expiryDate)
        <div class="info-row">
            <div class="info-label">Vencimiento:</div>
            <div class="info-value">{{ $expiryDate }}</div>
        </div>
        @endif

        <div class="info-row">
            <div class="info-label">Estado:</div>
            <div class="info-value">
                @if($status === 'approved')
                    <span style="color: #16a34a; font-weight: 700;">Aprobada</span>
                @else
                    <span style="color: #dc2626; font-weight: 700;">Rechazada</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Comentarios: siempre visibles si existen, con estilo según el estado --}}
    @if($comments)
    <div class="alert {{ $status === 'approved' ? 'alert-info' : 'alert-warning' }}"
         style="{{ $status === 'rejected' ? 'border-left: 4px solid #dc2626; background: #fef2f2;' : 'border-left: 4px solid #16a34a; background: #f0fdf4;' }}">
        <strong>{{ $status === 'approved' ? 'Comentarios:' : 'Motivo del rechazo:' }}</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">{{ $comments }}</p>
    </div>
    @endif

    {{-- Mensaje de acción según estado --}}
    @if($status === 'rejected')
    <div class="alert alert-warning" style="border-left: 4px solid #f59e0b;">
        <strong>Acción requerida:</strong> Por favor, corrija su certificación y vuelva a subirla
        en el portal para que pueda ser revisada nuevamente por el equipo de Calidad.
    </div>
    @else
    <div class="alert" style="border-left: 4px solid #16a34a; background: #f0fdf4; padding: 16px; border-radius: 6px; margin: 16px 0;">
        Su certificación ha sido validada exitosamente. No se requiere ninguna acción adicional.
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">
             Ver mis Certificaciones
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