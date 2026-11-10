{{-- resources/views/emails/non-conformity-reviewed.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $accepted ? 'Plan de acción aceptado' : 'Se requieren correcciones' }} — {{ $folio }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        @if($accepted)
            <p>El equipo de Calidad de DASAVENA revisó y <strong>aceptó</strong> su análisis de causa raíz y
            acciones correctivas para la No Conformidad <strong>{{ $folio }}</strong> ({{ $typeName }}).</p>
        @else
            <p>El equipo de Calidad de DASAVENA revisó su respuesta a la No Conformidad
            <strong>{{ $folio }}</strong> ({{ $typeName }}) y <strong>requiere correcciones</strong>
            antes de aceptarla.</p>
        @endif
    </div>

    @if($comment)
    <div class="alert {{ $accepted ? 'alert-success' : 'alert-warning' }}">
        <strong>Comentarios de DASAVENA:</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">{{ $comment }}</p>
    </div>
    @endif

    <div class="alert alert-info">
        @if($accepted)
            <strong>Siguiente paso:</strong> entregue la evidencia de cada acción correctiva a más tardar el
            <strong>{{ $evidenceDueDate }}</strong>.
        @else
            <strong>Importante:</strong> corrija su respuesta y vuelva a enviarla desde su portal. Los plazos
            no se reinician: la fecha límite de evidencias sigue siendo el <strong>{{ $evidenceDueDate }}</strong>.
        @endif
        <br><br>
        <strong>El hacer caso omiso a la No Conformidad influirá en su calificación como proveedor.</strong>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">Ver No Conformidad</a>
    </div>
@endsection