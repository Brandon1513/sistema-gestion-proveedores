@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $accion === 'creada' ? 'Nueva Nota de Crédito Registrada' : 'Actualización de tu Nota de Crédito' }}
    </div>

    <div class="email-content">
        <p>Hola <strong>{{ $providerName }}</strong>,</p>
        <p>
            @if($accion === 'creada')
                Se ha registrado una nueva nota de crédito en tu cuenta de proveedor del
                Sistema de Gestión de Proveedores de DASAVENA.
            @else
                El estatus de tu nota de crédito ha sido actualizado en el
                Sistema de Gestión de Proveedores de DASAVENA.
            @endif
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Información de la solicitud</div>
        <div class="info-row">
            <div class="info-label">Motivo:</div>
            <div class="info-value"><strong>{{ $tipoLabel }}</strong></div>
        </div>
        @if($facturaFolio)
        <div class="info-row">
            <div class="info-label">Factura relacionada:</div>
            <div class="info-value">{{ $facturaFolio }}</div>
        </div>
        @endif
        @if($montoSolicitado)
        <div class="info-row">
            <div class="info-label">Monto:</div>
            <div class="info-value">${{ number_format($montoSolicitado, 2) }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Estatus actual:</div>
            <div class="info-value" style="color: #D6A644; font-weight: 600;">
                {{ $statusLabel }}
            </div>
        </div>
    </div>

    <div class="email-content">
        <p><strong>Descripción / motivo:</strong></p>
        <p style="background-color: #f9fafb; padding: 15px; border-radius: 8px; color: #4a5568;">
            {{ $descripcion }}
        </p>
    </div>

    @if($accion === 'creada')
    <div class="alert alert-warning">
        <strong>Siguiente paso:</strong> Genera el CFDI de tu nota de crédito y súbelo
        desde el Portal de Proveedores para que Finanzas pueda validarlo.
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">
             Ver en el Portal de Proveedores
        </a>
    </div>

    <div class="email-content">
        <p style="color: #6b7280; font-size: 14px;">
            <strong>¿Tienes dudas?</strong> Contacta a tu ejecutivo de compras en DASAVENA.
        </p>
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