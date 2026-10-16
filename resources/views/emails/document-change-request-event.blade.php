@extends('emails.layout')

@section('content')
    <div class="email-title">{{ $title }}</div>

    <div class="email-content">
        <p>Hola,</p>
        <p>{{ $intro }}</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Detalle</div>
        <div class="info-row">
            <div class="info-label">Proveedor:</div>
            <div class="info-value"><strong>{{ $providerName }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Documento:</div>
            <div class="info-value">{{ $documentName }}</div>
        </div>
        @if($requestedAt)
        <div class="info-row">
            <div class="info-label">Solicitado:</div>
            <div class="info-value">{{ $requestedAt }}</div>
        </div>
        @endif
        @if(!is_null($imagesCount))
        <div class="info-row">
            <div class="info-label">Imágenes:</div>
            <div class="info-value">
                {{ $imagesCount > 0 ? $imagesCount . ' adjunta' . ($imagesCount > 1 ? 's' : '') . ' (ver en SGP)' : 'Sin imágenes' }}
            </div>
        </div>
        @endif
    </div>

    @if($description)
        <div class="email-content">
            <p><strong>Lo que pide el proveedor:</strong></p>
            <p style="background-color: #f9fafb; padding: 15px; border-radius: 8px; color: #4a5568;">{!! nl2br(e($description)) !!}</p>
        </div>
    @endif

    @if($notes)
        <div class="email-content">
            <p><strong>{{ $notesLabel }}:</strong></p>
            <p style="background-color: #f9fafb; padding: 15px; border-radius: 8px; color: #4a5568;">{!! nl2br(e($notes)) !!}</p>
        </div>
    @endif

    @if($alertText)
        <div class="alert alert-warning"><strong>Siguiente paso:</strong> {{ $alertText }}</div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">{{ $actionLabel }}</a>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">Sistema de Gestión de Proveedores</p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">Equipo DASAVENA</p>
    </div>
@endsection