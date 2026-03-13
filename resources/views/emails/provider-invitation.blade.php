@extends('emails.layout')

@section('content')
    <div class="email-title">
        ¡Bienvenido a DASAVENA!
    </div>

    <div class="email-content">
        <p>Has sido invitado a registrarte como proveedor en nuestro Sistema de Gestión de Proveedores.</p>
        <p>Estamos emocionados de iniciar esta colaboración contigo.</p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Información de tu invitación</div>
        <div class="info-row">
            <div class="info-label">Tipo de Proveedor:</div>
            <div class="info-value"><strong>{{ $invitation->providerType->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Fecha de Invitación:</div>
            <div class="info-value">{{ $invitation->created_at->format('d/m/Y H:i') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Válida hasta:</div>
            <div class="info-value" style="color: #D6A644; font-weight: 600;">
                {{ $invitation->expires_at->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Importante:</strong> Esta invitación expira en <strong>7 días</strong>. 
        Por favor, completa tu registro antes de la fecha de expiración.
    </div>

    <div class="email-content">
        <p><strong>Para completar tu registro, sigue estos pasos:</strong></p>
        <ol style="margin-left: 20px; color: #4a5568; line-height: 1.8;">
            <li>Haz clic en el botón "Completar Registro"</li>
            <li>Crea tu contraseña de acceso</li>
            <li>Completa tu información de contacto</li>
            <li>Listo, ya podrás acceder al sistema</li>
        </ol>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $registrationUrl }}" class="button">
            ✓ Completar Registro
        </a>
    </div>

    <div class="email-content" style="background-color: #f9fafb; padding: 15px; border-radius: 8px;">
        <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">
            <strong>¿No puedes hacer clic en el botón?</strong>
        </p>
        <p style="font-size: 13px; color: #6b7280;">
            Copia y pega este enlace en tu navegador:
        </p>
        <p style="font-size: 12px; color: #6A2C75; word-break: break-all; margin-top: 8px;">
            {{ $registrationUrl }}
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Beneficios del sistema</div>
        <ul style="margin-left: 20px; color: #4a5568; line-height: 1.8;">
            <li>Cargar y actualizar documentos de forma digital</li>
            <li>Consultar el estado de tus documentos en tiempo real</li>
            <li>Recibir notificaciones automáticas de vencimientos</li>
            <li>Mantener tu información siempre actualizada</li>
            <li>Proceso de validación más rápido y eficiente</li>
        </ul>
    </div>

    <div class="email-content">
        <p style="color: #6b7280; font-size: 14px;">
            <strong>¿Necesitas ayuda?</strong> Si tienes alguna duda sobre el proceso de registro 
            o necesitas asistencia, no dudes en contactarnos.
        </p>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            ¡Esperamos trabajar contigo muy pronto!
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection