@extends('emails.layout')

@section('content')
    @php
        $isCritical = $urgencyLevel === 'critical';
        $isWarning = $urgencyLevel === 'warning';
        $alertClass = $isCritical ? 'alert-danger' : ($isWarning ? 'alert-warning' : 'alert-info');
        $emoji = $isCritical ? '🚨' : ($isWarning ? '⚠️' : '📅');
    @endphp

    <div class="email-title">
        {{ $emoji }} Su documento está próximo a vencer
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $provider->business_name }}</strong>,</p>
        <p>Le recordamos que uno de sus documentos está próximo a su fecha de vencimiento.</p>
    </div>

    <div class="alert {{ $alertClass }}">
        <strong>
            @if($isCritical)
                🚨 URGENTE
            @elseif($isWarning)
                ⚠️ ATENCIÓN
            @else
                📅 RECORDATORIO
            @endif
        </strong>
        - Este documento vence en <strong>{{ $daysUntilExpiry }} días</strong>
    </div>

    <div class="info-box">
        <div class="info-box-title">Información del Documento</div>
        <div class="info-row">
            <div class="info-label">Tipo de Documento:</div>
            <div class="info-value">{{ $documentType->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Fecha de Emisión:</div>
            <div class="info-value">{{ $document->issue_date ? $document->issue_date->format('d/m/Y') : 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Fecha de Vencimiento:</div>
            <div class="info-value" style="color: {{ $isCritical ? '#c53030' : '#d97706' }}; font-weight: 600;">
                {{ $document->expiry_date->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Días Restantes:</div>
            <div class="info-value" style="color: {{ $isCritical ? '#c53030' : '#d97706' }}; font-weight: 600;">
                {{ $daysUntilExpiry }} días
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Estado Actual:</div>
            <div class="info-value">
                @if($document->status === 'approved')
                    <span style="color: #38a169;">✓ Aprobado</span>
                @else
                    <span style="color: #718096;">{{ ucfirst($document->status) }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($isCritical)
    <div class="email-content">
        <p style="color: #c53030; font-weight: 600; font-size: 16px;">
            ⚠️ <strong>ACCIÓN INMEDIATA REQUERIDA</strong>
        </p>
        <p style="color: #742a2a;">
            Este documento vencerá en menos de una semana. Es <strong>urgente</strong> que actualice 
            su documentación para evitar interrupciones en su operación con nuestra empresa.
        </p>
    </div>
    @elseif($isWarning)
    <div class="email-content">
        <p style="color: #d97706; font-weight: 600;">
            ⚠️ <strong>Atención Requerida</strong>
        </p>
        <p style="color: #92400e;">
            Le recomendamos actualizar este documento a la brevedad para evitar contratiempos.
        </p>
    </div>
    @endif

    <div class="email-content">
        <p><strong>Pasos para renovar su documento:</strong></p>
        <ol style="margin-left: 20px; color: #4a5568;">
            <li style="margin-bottom: 8px;">Prepare el documento actualizado con la nueva fecha de vigencia</li>
            <li style="margin-bottom: 8px;">Ingrese al sistema SGP con sus credenciales</li>
            <li style="margin-bottom: 8px;">Navegue a la sección de documentos</li>
            <li style="margin-bottom: 8px;">Cargue el nuevo documento</li>
            <li style="margin-bottom: 8px;">Nuestro equipo lo validará en las próximas 24-48 horas</li>
        </ol>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $appUrl }}" class="button" style="background-color: {{ $isCritical ? '#f56565' : ($isWarning ? '#f39c12' : '#667eea') }};">
            Actualizar Documento Ahora
        </a>
    </div>

    @if($isCritical)
    <div class="alert alert-danger" style="border-left-color: #c53030;">
        <strong>⚠️ Importante:</strong> Si su documento vence sin renovación, podríamos tener que 
        suspender temporalmente nuestras operaciones con su empresa hasta que la documentación 
        esté al día.
    </div>
    @endif

    <div class="email-content">
        <p style="color: #718096; font-size: 14px;">
            <strong>¿Necesita ayuda?</strong> Si tiene alguna duda sobre el proceso de renovación 
            o los documentos requeridos, no dude en contactarnos.
        </p>
    </div>
@endsection