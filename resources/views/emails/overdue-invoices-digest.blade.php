@extends('emails.layout')

@section('content')
    <div class="email-title">
        Resumen Diario — Facturas Vencidas
    </div>

    <div class="email-content">
        <p>Hola,</p>
        <p>
            Este es el resumen diario de facturas de proveedores vencidas en el
            Sistema de Gestión de Proveedores de DASAVENA.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Resumen</div>
        <div class="info-row">
            <div class="info-label">Facturas vencidas:</div>
            <div class="info-value"><strong>{{ $count }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Monto total vencido:</div>
            <div class="info-value" style="color: #D6A644; font-weight: 600;">
                ${{ number_format($totalAmount, 2) }}
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Top {{ count($rows) }} facturas por monto:</strong>
    </div>

    <div class="email-content" style="background-color: #f9fafb; padding: 15px; border-radius: 8px;">
        @foreach($rows as $row)
            <p style="font-size: 13px; color: #4a5568; margin: 4px 0;">{{ $row }}</p>
        @endforeach

        @if($hasMore)
            <p style="font-size: 13px; color: #6b7280; margin-top: 10px;">
                Y {{ $remainingCount }} factura(s) más — revisa el detalle completo en el sistema.
            </p>
        @endif
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $actionUrl }}" class="button">
            📋 Ver Estado de Cuenta Completo
        </a>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            Sistema de Gestión de Proveedores
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection