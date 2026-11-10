{{-- resources/views/emails/non-conformity-reminder.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        {{ $dueToday ? '🚨 Su No Conformidad vence hoy' : '⚠️ Su No Conformidad vence en ' . $daysLeft . ' días' }}
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>Le recordamos que la No Conformidad <strong>{{ $folio }}</strong> ({{ $typeName }} · {{ $productName }})
        tiene pendiente: <strong>{{ $pendingTask }}</strong>.</p>
    </div>

    <div class="alert {{ $dueToday ? 'alert-danger' : 'alert-warning' }}">
        <strong>Fecha límite:</strong> {{ $dueDate }}
        <br><br>
        <strong>El hacer caso omiso a la No Conformidad influirá en su calificación como proveedor.</strong>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">📋 Atender No Conformidad</a>
    </div>
@endsection