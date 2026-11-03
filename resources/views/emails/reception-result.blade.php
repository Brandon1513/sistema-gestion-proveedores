{{-- resources/views/emails/reception-result.blade.php --}}
@extends('emails.layout')

@section('content')
    <div class="email-title">
        @if($hasReturns && $status === 'rejected')
            ❌ Entrega Rechazada
        @elseif($hasReturns)
            ⚠️ Entrega con Devoluciones
        @else
            ⚠️ Observaciones en su Entrega
        @endif
    </div>

    <div class="email-content">
        <p>Estimado proveedor <strong>{{ $providerName }}</strong>,</p>
        <p>
            El equipo de Calidad de DASAVENA registró la recepción de su entrega y
            @if($hasReturns && $status === 'rejected')
                <strong>el producto fue rechazado</strong>.
            @elseif($hasReturns)
                <strong>se devolvió parte del producto</strong>.
            @else
                <strong>registró observaciones sobre las condiciones de la entrega</strong>.
            @endif
            A continuación encontrará el detalle.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Datos de la Entrega</div>
        <div class="info-row">
            <div class="info-label">Fecha:</div>
            <div class="info-value" style="text-transform: capitalize;"><strong>{{ $date }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Hora:</div>
            <div class="info-value"><strong>{{ $time }}</strong> hrs</div>
        </div>
        <div class="info-row">
            <div class="info-label">Resultado:</div>
            <div class="info-value" style="color: {{ $hasReturns && $status === 'rejected' ? '#dc2626' : '#d97706' }};"><strong>{{ $statusLabel }}</strong></div>
        </div>
    </div>

    @if($conditions->isNotEmpty())
    <div style="margin: 20px 0;">
        <p style="font-size: 14px; font-weight: 600; color: #6A2C75; margin-bottom: 10px;">Condiciones de la entrega</p>
        <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px;">
            @foreach($conditions as $c)
                <div style="padding: 6px 0; {{ !$loop->last ? 'border-bottom: 1px solid #f3f4f6;' : '' }}">
                    <span style="font-size: 13px; font-weight: 600; color: {{ $c['passed'] ? '#0f766e' : '#b91c1c' }};">
                        {{ $c['passed'] ? '✔' : '✘' }} {{ $c['label'] }}
                    </span>
                    <span style="font-size: 12px; color: {{ $c['passed'] ? '#0f766e' : '#b91c1c' }};"> — {{ $c['passed'] ? 'Cumple' : 'No cumple' }}</span>
                    @if(!$c['passed'] && !empty($c['notes']))
                        <p style="font-size: 13px; color: #374151; margin: 4px 0 0 18px;"><strong>Observación:</strong> {{ $c['notes'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div style="margin: 20px 0;">
        <p style="font-size: 14px; font-weight: 600; color: #6A2C75; margin-bottom: 10px;">Detalle por producto</p>

        @foreach($items as $item)
            <div style="border: 1px solid {{ $item['has_return'] ? '#fecaca' : '#e5e7eb' }};
                        background: {{ $item['has_return'] ? '#fef2f2' : ($item['not_delivered'] ? '#f8fafc' : '#f0fdfa') }};
                        border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="font-size: 14px; font-weight: 600; color: #111827;">{{ $item['name'] }}</td>
                        <td style="text-align: right; font-size: 12px; font-weight: 700;
                                   color: {{ $item['status'] === 'rejected' ? '#b91c1c' : ($item['status'] === 'partial' ? '#b45309' : ($item['not_delivered'] ? '#475569' : '#0f766e')) }};">
                            {{ $item['status_label'] }}
                        </td>
                    </tr>
                </table>

                @unless($item['not_delivered'])
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-top: 8px;">
                        <tr>
                            <td style="font-size: 12px; color: #6b7280; text-align: center;">Entregado<br><strong style="color: #1e40af;">{{ $item['received'] }} {{ $item['unit'] }}</strong></td>
                            <td style="font-size: 12px; color: #6b7280; text-align: center;">Aceptado<br><strong style="color: #166534;">{{ $item['accepted'] }} {{ $item['unit'] }}</strong></td>
                            <td style="font-size: 12px; color: #6b7280; text-align: center;">Devuelto<br><strong style="color: #991b1b;">{{ $item['rejected'] }} {{ $item['unit'] }}</strong></td>
                        </tr>
                    </table>
                @endunless

                @if($item['reason'])
                    <p style="font-size: 13px; color: #374151; margin: 8px 0 0;"><strong>Motivo de la devolución:</strong> {{ $item['reason'] }}</p>
                @endif
                @if($item['notes'])
                    <p style="font-size: 13px; color: #374151; margin: 4px 0 0;"><strong>Observaciones:</strong> {{ $item['notes'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="alert" style="border-left: 4px solid #6A2C75; background: #F5F0F6; padding: 16px; border-radius: 6px; margin: 16px 0;">
        En su portal puede consultar el detalle completo y las fotos de evidencia de la recepción.
        Si tiene dudas, comuníquese con el área de Compras de DASAVENA.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $portalUrl }}" class="button">
            📋 Ver detalle y evidencia
        </a>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px;
                background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            Sistema de Gestión de Proveedores
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection