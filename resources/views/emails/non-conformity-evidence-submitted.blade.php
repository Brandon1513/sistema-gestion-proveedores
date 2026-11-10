{{-- resources/views/emails/non-conformity-evidence-submitted.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">📎 Evidencia recibida — {{ $folio }}</div>

    <div class="email-content">
        <p><strong>{{ $providerName }}</strong> subió la evidencia de la <strong>acción {{ $actionNumber }}</strong>
        @if($attempt > 1) (intento {{ $attempt }}) @endif y está lista para su revisión.</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Acción correctiva {{ $actionNumber }}</div>
        <div class="info-row"><div class="info-label">Acción:</div><div class="info-value">{{ $actionText }}</div></div>
        <div class="info-row"><div class="info-label">Evidencia comprometida:</div><div class="info-value">{{ $evidenceToGive }}</div></div>
        <div class="info-row"><div class="info-label">Archivo:</div><div class="info-value">{{ $fileName }}</div></div>
        @if($providerComment)
        <div class="info-row"><div class="info-label">Comentario:</div><div class="info-value">{{ $providerComment }}</div></div>
        @endif
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">Revisar evidencia</a>
    </div>
@endsection