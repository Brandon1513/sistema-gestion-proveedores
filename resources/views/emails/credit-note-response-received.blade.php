@extends('emails.layout')

@section('content')
    <div class="email-title">
        CFDI de Nota de Crédito Recibido
    </div>

    <div class="email-content">
        <p>Hola,</p>
        <p>
            El proveedor <strong>{{ $providerName }}</strong> subió el comprobante fiscal (CFDI)
            de la nota de crédito que se le solicitó.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Detalle de la solicitud</div>
        <div class="info-row">
            <div class="info-label">Proveedor:</div>
            <div class="info-value"><strong>{{ $providerName }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Motivo:</div>
            <div class="info-value">{{ $tipoLabel }}</div>
        </div>
        @if($facturaFolio)
        <div class="info-row">
            <div class="info-label">Factura relacionada:</div>
            <div class="info-value">{{ $facturaFolio }}</div>
        </div>
        @endif
    </div>

    <div class="email-content">
        <p><strong>Descripción original:</strong></p>
        <p style="background-color: #f9fafb; padding: 15px; border-radius: 8px; color: #4a5568;">
            {{ $descripcion }}
        </p>
    </div>

    <div class="alert alert-warning">
        <strong>Siguiente paso:</strong> Valida el CFDI y actualiza el estatus de la solicitud
        hasta capturarla en NetSuite.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">
             Revisar en SGP
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