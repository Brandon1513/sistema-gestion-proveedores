{{-- resources/views/emails/non-conformity-response-submitted.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">Respuesta a la No Conformidad {{ $folio }}</div>

    <div class="email-content">
        <p><strong>{{ $providerName }}</strong> envió su análisis de causa raíz y acciones correctivas
        @if($round > 1) (<strong>vuelta {{ $round }}</strong>) @endif
        y está listo para su revisión.</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Resumen</div>
        <div class="info-row"><div class="info-label">Folio:</div><div class="info-value"><strong>{{ $folio }}</strong></div></div>
        <div class="info-row"><div class="info-label">Tipo:</div><div class="info-value">{{ $typeName }}</div></div>
        <div class="info-row"><div class="info-label">Producto:</div><div class="info-value">{{ $productName }}</div></div>
        <div class="info-row"><div class="info-label">Líder del equipo:</div><div class="info-value">{{ $teamLeader }} ({{ $area }})</div></div>
        <div class="info-row"><div class="info-label">Acciones correctivas:</div><div class="info-value">{{ $actionsCount }}</div></div>
        @if($evidenceDue)
        <div class="info-row"><div class="info-label">Límite de evidencias:</div><div class="info-value">{{ $evidenceDue }}</div></div>
        @endif
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">Revisar respuesta</a>
    </div>
@endsection