{{-- resources/views/emails/non-conformity-issued.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
         No Conformidad {{ $folio }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>
            El equipo de Calidad de DASAVENA detectó una desviación en su producto durante el proceso
            de producción y emitió la siguiente <strong>No Conformidad</strong>.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Datos de la No Conformidad</div>
        <div class="info-row"><div class="info-label">Folio:</div><div class="info-value"><strong>{{ $folio }}</strong></div></div>
        <div class="info-row"><div class="info-label">Tipo de desviación:</div><div class="info-value"><strong>{{ $typeName }}</strong></div></div>
        <div class="info-row"><div class="info-label">Producto:</div><div class="info-value">{{ $productName }}</div></div>
        @if($lot)
        <div class="info-row"><div class="info-label">Lote:</div><div class="info-value">{{ $lot }}</div></div>
        @endif
        <div class="info-row"><div class="info-label">Fecha de desviación:</div><div class="info-value">{{ $deviationDate }}</div></div>
        @if($receptionDate)
        <div class="info-row"><div class="info-label">Fecha de recepción:</div><div class="info-value">{{ $receptionDate }}</div></div>
        @endif
        @if($quantity)
        <div class="info-row"><div class="info-label">Cantidad involucrada:</div><div class="info-value">{{ $quantity }}</div></div>
        @endif
    </div>

    <div class="email-content">
        <p style="font-weight: 600; color: #6A2C75;">Descripción de la No Conformidad</p>
        {{-- descriptionHtml ya viene limpio (App\Support\RichText::toHtml) --}}
        <div style="font-size: 15px; color: #4a5568;">{!! $descriptionHtml !!}</div>
    </div>

    <div class="alert alert-warning">
        <strong>Plazo de respuesta:</strong> tiene hasta el <strong>{{ $responseDueDate }}</strong> ({{ $responseDays }} días)
        para enviar su análisis de causa raíz y acciones correctivas. Una vez enviadas, cuenta con otros
        {{ $evidenceDays }} días para entregar las evidencias.
        <br><br>
        <strong>El hacer caso omiso a la No Conformidad influirá en su calificación como proveedor.</strong>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button"> Ver No Conformidad</a>
    </div>

    <div class="email-content">
        <p style="color: #718096; font-size: 14px;">
            Las fotos y el detalle completo están disponibles en su portal de proveedor.
        </p>
    </div>
@endsection