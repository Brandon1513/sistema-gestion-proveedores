@extends('emails.layout')

@section('content')
    <div class="email-title">
        Restablecer Contraseña
    </div>

    <div class="email-content">
        <p>Hola <strong>{{ $userName }}</strong>,</p>
        <p>
            Recibimos una solicitud para restablecer la contraseña de tu cuenta en el
            Sistema de Gestión de Proveedores de DASAVENA.
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Información de la solicitud</div>
        <div class="info-row">
            <div class="info-label">Cuenta:</div>
            <div class="info-value"><strong>{{ $userEmail }}</strong></div>
        </div>
        <div class="info-row">
            <div class="info-label">Válido por:</div>
            <div class="info-value" style="color: #D6A644; font-weight: 600;">
                60 minutos
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Importante:</strong> Si no solicitaste restablecer tu contraseña,
        puedes ignorar este correo. Tu contraseña no cambiará.
    </div>

    <div class="email-content">
        <p><strong>Para restablecer tu contraseña, sigue estos pasos:</strong></p>
        <ol style="margin-left: 20px; color: #4a5568; line-height: 1.8;">
            <li>Haz clic en el botón "Restablecer Contraseña"</li>
            <li>Ingresa tu nueva contraseña (mínimo 8 caracteres)</li>
            <li>Confirma tu nueva contraseña</li>
            <li>Inicia sesión con tus nuevas credenciales</li>
        </ol>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}" class="button">
            🔐 Restablecer Contraseña
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
            {{ $resetUrl }}
        </p>
    </div>

    <div class="info-box">
        <div class="info-box-title">Recomendaciones de seguridad</div>
        <ul style="margin-left: 20px; color: #4a5568; line-height: 1.8;">
            <li>Usa una contraseña de al menos 8 caracteres</li>
            <li>Combina letras mayúsculas, minúsculas y números</li>
            <li>Agrega caracteres especiales para mayor seguridad</li>
            <li>No compartas tu contraseña con nadie</li>
            <li>Este enlace expira en <strong>60 minutos</strong></li>
        </ul>
    </div>

    <div class="email-content">
        <p style="color: #6b7280; font-size: 14px;">
            <strong>¿Necesitas ayuda?</strong> Si tienes algún problema para restablecer
            tu contraseña, contacta al administrador del sistema.
        </p>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%); border-radius: 8px;">
        <p style="font-size: 14px; color: #6A2C75; font-weight: 600;">
            Tu seguridad es nuestra prioridad
        </p>
        <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
            Equipo DASAVENA
        </p>
    </div>
@endsection