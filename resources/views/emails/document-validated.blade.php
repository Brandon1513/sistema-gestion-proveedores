<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $status === 'approved' ? 'Documento Aprobado' : 'Documento Rechazado' }} - SGP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #F5F0F6;
            padding: 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(106, 44, 117, 0.1), 0 10px 40px 0 rgba(106, 44, 117, 0.2);
        }
        
        /* Header - Morado DASAVENA para aprobado, Rosa para rechazado */
        .email-header-approved {
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .email-header-rejected {
            background: linear-gradient(135deg, #AA4969 0%, #C85A7E 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo {
            max-width: 120px;
            height: auto;
        }
        
        .email-header-approved h1,
        .email-header-rejected h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }
        
        .email-header-approved p,
        .email-header-rejected p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 0;
        }
        
        /* Status badge grande */
        .status-container {
            text-align: center;
            padding: 30px;
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
        }
        
        .status-badge-approved {
            display: inline-block;
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            color: #ffffff;
            padding: 16px 40px;
            border-radius: 30px;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 4px 14px 0 rgba(106, 44, 117, 0.39);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-badge-rejected {
            display: inline-block;
            background: linear-gradient(135deg, #AA4969 0%, #C85A7E 100%);
            color: #ffffff;
            padding: 16px 40px;
            border-radius: 30px;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 4px 14px 0 rgba(170, 73, 105, 0.39);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Contenido */
        .email-content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 16px;
            color: #374151;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        /* Info card */
        .info-card-approved {
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
            border-left: 4px solid #6A2C75;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            box-shadow: 0 1px 3px rgba(106, 44, 117, 0.1);
        }
        
        .info-card-rejected {
            background: linear-gradient(to bottom right, #FDF2F5, #FCE7ED);
            border-left: 4px solid #AA4969;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            box-shadow: 0 1px 3px rgba(170, 73, 105, 0.1);
        }
        
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label-approved {
            font-weight: 600;
            color: #6A2C75;
            min-width: 160px;
            font-size: 14px;
        }
        
        .info-label-rejected {
            font-weight: 600;
            color: #AA4969;
            min-width: 160px;
            font-size: 14px;
        }
        
        .info-value {
            color: #374151;
            font-size: 14px;
            flex: 1;
        }
        
        /* Comments box - Dorado DASAVENA */
        .comments-box {
            background: linear-gradient(135deg, #FDF8ED 0%, #FAEFD3 100%);
            border-left: 4px solid #D6A644;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            box-shadow: 0 1px 3px rgba(214, 166, 68, 0.1);
        }
        
        .comments-title {
            color: #946F23;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .comments-text {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            font-style: italic;
        }
        
        /* Alert box */
        .alert-box-success {
            background: linear-gradient(135deg, #E6D9E9 0%, #D4BBD9 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            border: 2px solid #BBA4C0;
        }
        
        .alert-box-danger {
            background: linear-gradient(135deg, #FCE7ED 0%, #F9CFD9 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            border: 2px solid #F5A8BC;
        }
        
        .alert-text-success {
            color: #491E50;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.6;
        }
        
        .alert-text-danger {
            color: #7D324D;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.6;
        }
        
        /* CTA Button */
        .cta-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .cta-button-approved {
            display: inline-block;
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 14px 0 rgba(106, 44, 117, 0.39);
        }
        
        .cta-button-rejected {
            display: inline-block;
            background: linear-gradient(135deg, #AA4969 0%, #C85A7E 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 14px 0 rgba(170, 73, 105, 0.39);
        }
        
        /* Divider */
        .divider-approved {
            height: 3px;
            background: linear-gradient(to right, #6A2C75, #D6A644, #6A2C75);
            margin: 30px 0;
            border-radius: 2px;
        }
        
        .divider-rejected {
            height: 3px;
            background: linear-gradient(to right, #AA4969, #D6A644, #AA4969);
            margin: 30px 0;
            border-radius: 2px;
        }
        
        /* Footer */
        .email-footer {
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
            padding: 30px;
            text-align: center;
            border-top: 1px solid #E6D9E9;
        }
        
        .footer-text {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.6;
            margin: 8px 0;
        }
        
        .footer-copyright {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 15px;
        }
        
        .footer-brand {
            color: #6A2C75;
            font-weight: 700;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .email-header-approved,
            .email-header-rejected {
                padding: 30px 20px;
            }
            
            .email-content {
                padding: 30px 20px;
            }
            
            .status-badge-approved,
            .status-badge-rejected {
                font-size: 16px;
                padding: 12px 30px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label-approved,
            .info-label-rejected {
                min-width: auto;
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header dinámico según status -->
        <div class="{{ $status === 'approved' ? 'email-header-approved' : 'email-header-rejected' }}">
            <div class="logo-container">
                <img src="{{ config('app.url') }}/images/logo.png" alt="DASAVENA Logo" class="logo">
            </div>
            <h1>{{ $status === 'approved' ? '✅ Documento Aprobado' : '❌ Documento Rechazado' }}</h1>
            <p>Sistema de Gestión de Proveedores</p>
        </div>
        
        <!-- Status badge -->
        <div class="status-container">
            <div class="{{ $status === 'approved' ? 'status-badge-approved' : 'status-badge-rejected' }}">
                {{ $statusText }}
            </div>
        </div>
        
        <!-- Contenido -->
        <div class="email-content">
            <p class="greeting">Estimado <strong>{{ $providerName }}</strong>,</p>
            
            <p class="message">
                Su documento ha sido revisado y {{ $status === 'approved' ? '<strong>aprobado</strong>' : '<strong>rechazado</strong>' }} 
                por nuestro equipo de Control de Calidad.
            </p>
            
            <!-- Información del documento -->
            <div class="{{ $status === 'approved' ? 'info-card-approved' : 'info-card-rejected' }}">
                <div class="info-row">
                    <span class="{{ $status === 'approved' ? 'info-label-approved' : 'info-label-rejected' }}">📋 Tipo de Documento:</span>
                    <span class="info-value"><strong>{{ $documentType }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="{{ $status === 'approved' ? 'info-label-approved' : 'info-label-rejected' }}">📎 Archivo:</span>
                    <span class="info-value">{{ $fileName }}</span>
                </div>
                <div class="info-row">
                    <span class="{{ $status === 'approved' ? 'info-label-approved' : 'info-label-rejected' }}">👤 Validado por:</span>
                    <span class="info-value">{{ $validatedBy }}</span>
                </div>
                <div class="info-row">
                    <span class="{{ $status === 'approved' ? 'info-label-approved' : 'info-label-rejected' }}">📅 Fecha de Validación:</span>
                    <span class="info-value">{{ $validatedAt }}</span>
                </div>
            </div>
            
            <!-- Comentarios -->
            @if($comments)
            <div class="comments-box">
                <div class="comments-title">💬 Comentarios del Validador</div>
                <p class="comments-text">{{ $comments }}</p>
            </div>
            @endif
            
            <div class="{{ $status === 'approved' ? 'divider-approved' : 'divider-rejected' }}"></div>
            
            <!-- Mensaje según status -->
            @if($status === 'rejected')
            <div class="alert-box-danger">
                <p class="alert-text-danger">
                    <strong>⚠️ Acción Requerida</strong>
                    <br><br>
                    Su documento ha sido rechazado. Por favor, revise los comentarios del validador, 
                    corrija los problemas señalados y vuelva a cargar el documento en el sistema.
                </p>
            </div>
            @else
            <div class="alert-box-success">
                <p class="alert-text-success">
                    <strong>✓ ¡Felicidades!</strong>
                    <br><br>
                    Su documento ha sido aprobado exitosamente. No se requiere ninguna acción adicional.
                </p>
            </div>
            @endif
            
            <!-- Botón de acción -->
            <div class="cta-container">
                <a href="{{ $documentsUrl }}" class="{{ $status === 'approved' ? 'cta-button-approved' : 'cta-button-rejected' }}">
                    📂 Ver Mis Documentos
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                <strong>Nota:</strong> Este es un correo automático, por favor no responder directamente a este mensaje.
            </p>
            <p class="footer-text">
                Si tiene dudas o necesita asistencia, por favor contacte a nuestro equipo de soporte.
            </p>
            <p class="footer-copyright">
                &copy; {{ date('Y') }} <span class="footer-brand">DASAVENA</span> - Sistema de Gestión de Proveedores
                <br>
                Todos los derechos reservados
            </p>
        </div>
    </div>
</body>
</html>