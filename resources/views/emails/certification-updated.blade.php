{{-- resources/views/emails/certification-updated.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $action === 'created' ? 'Nueva Certificación Pendiente de Revisión' : 'Certificación Actualizada' }}
    </div>

    <div class="email-content">
        <p>El proveedor <strong>{{ $providerName }}</strong> ha
            {{ $action === 'created' ? 'registrado una nueva certificación' : 'modificado una certificación existente' }}
            que requiere tu revisión.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Detalles de la Certificación</div>
        <div class="info-row">
            <div class="info-label">Proveedor:</div>
            <div class="info-value"><strong>{{ $providerName }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">RFC:</div>
            <div class="info-value">{{ $providerRfc }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tipo de Certificación:</div>
            <div class="info-value"><strong>{{ $certificationName }}</strong></div>
        </div>
        @if($certificationNumber)
        <div class="info-row">
            <div class="info-label">Número:</div>
            <div class="info-value">{{ $certificationNumber }}</div>
        </div>
        @endif
        @if($certifyingBody)
        <div class="info-row">
            <div class="info-label">Organismo Certificador:</div>
            <div class="info-value">{{ $certifyingBody }}</div>
        </div>
        @endif
        @if($expiryDate)
        <div class="info-row">
            <div class="info-label">Vencimiento:</div>
            <div class="info-value" style="color: #D6A644; font-weight: 600;">{{ $expiryDate }}</div>
        </div>
        @endif
        @if($hasFile)
        <div class="info-row">
            <div class="info-label">Archivo adjunto:</div>
            <div class="info-value" style="color: #6A2C75;">✓ Archivo incluido</div>
        </div>
        @endif
    </div>

    <div class="alert alert-warning">
        <strong>Acción requerida:</strong> Esta certificación está pendiente de revisión.
        Por favor, revísala y valídala o recházala en el sistema.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $systemUrl }}" class="button">
             Revisar Certificación
        </a>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            Sistema de Gestión de Proveedores
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection