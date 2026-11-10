{{-- resources/views/emails/non-conformity-evidence-reviewed.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $accepted ? '✅ Evidencia aceptada' : '⚠️ Evidencia no cumple' }} — {{ $folio }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>El equipo de Calidad de DASAVENA revisó la evidencia de la <strong>acción {{ $actionNumber }}</strong>
        y la marcó como <strong>{{ $accepted ? 'Cumple' : 'No cumple' }}</strong>.</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Acción correctiva {{ $actionNumber }}</div>
        <div class="info-row"><div class="info-label">Acción:</div><div class="info-value">{{ $actionText }}</div></div>
        <div class="info-row"><div class="info-label">Evidencia a entregar:</div><div class="info-value">{{ $evidenceToGive }}</div></div>
    </div>

    @if($comment)
    <div class="alert {{ $accepted ? 'alert-success' : 'alert-warning' }}">
        <strong>Comentarios de DASAVENA:</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">{{ $comment }}</p>
    </div>
    @endif

    @unless($accepted)
    <div class="alert alert-info">
        Suba una nueva evidencia para esta acción desde su portal a más tardar el <strong>{{ $evidenceDueDate }}</strong>.
        <br><br>
        <strong>El hacer caso omiso a la No Conformidad influirá en su calificación como proveedor.</strong>
    </div>
    @endunless

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">📋 Ver No Conformidad</a>
    </div>
@endsection