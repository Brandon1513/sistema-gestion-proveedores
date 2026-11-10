{{-- resources/views/emails/non-conformity-closed.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">✅ No Conformidad {{ $folio }} cerrada</div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>Le informamos que la No Conformidad <strong>{{ $folio }}</strong> ({{ $typeName }} · {{ $productName }})
        fue <strong>cerrada</strong> el {{ $closedAt }}, una vez validadas todas sus evidencias.</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Resultado</div>
        <div class="info-row"><div class="info-label">Estado:</div><div class="info-value"><strong>{{ $displayStatus }}</strong></div></div>
        <div class="info-row"><div class="info-label">Fecha de cierre:</div><div class="info-value">{{ $closedAt }}</div></div>
    </div>

    @if($comment)
    <div class="alert alert-success">
        <strong>Comentarios de cierre:</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">{{ $comment }}</p>
    </div>
    @endif

    @if($isLate)
    <div class="alert alert-warning">
        La No Conformidad se cerró después del plazo establecido, lo cual se considera en su calificación como proveedor.
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">📋 Ver No Conformidad</a>
    </div>

    <div class="email-content">
        <p>Gracias por su atención y compromiso con la calidad.</p>
    </div>
@endsection