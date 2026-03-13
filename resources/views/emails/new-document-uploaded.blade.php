<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Documento Pendiente - SGP</title>
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
        
        /* Header con gradiente DASAVENA */
        .email-header {
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo {
            max-width: 120px;
            height: auto;
        }
        
        .email-header h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }
        
        .email-header p {
            color: #E6D9E9;
            font-size: 14px;
            margin: 0;
        }
        
        /* Contenido principal */
        .email-content {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .greeting {
            font-size: 16px;
            color: #374151;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        /* Tarjeta de información */
        .info-card {
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
            border-left: 4px solid #6A2C75;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            box-shadow: 0 1px 3px rgba(106, 44, 117, 0.1);
        }
        
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid rgba(106, 44, 117, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6A2C75;
            min-width: 140px;
            font-size: 14px;
        }
        
        .info-value {
            color: #374151;
            font-size: 14px;
            flex: 1;
        }
        
        /* Badge de prioridad con dorado DASAVENA */
        .priority-badge {
            display: inline-block;
            background: linear-gradient(135deg, #D6A644 0%, #EED39B 100%);
            color: #4A3812;
            padding: 8px 20px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px 0 rgba(214, 166, 68, 0.39);
        }
        
        /* Botón de acción con morado DASAVENA */
        .cta-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 14px 0 rgba(106, 44, 117, 0.39);
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            background: linear-gradient(135deg, #5A2563 0%, #6A2C75 100%);
            box-shadow: 0 10px 40px 0 rgba(106, 44, 117, 0.2);
            transform: translateY(-2px);
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
        
        /* Divider decorativo con colores DASAVENA */
        .divider {
            height: 3px;
            background: linear-gradient(to right, #6A2C75, #D6A644, #6A2C75);
            margin: 30px 0;
            border-radius: 2px;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .email-header {
                padding: 30px 20px;
            }
            
            .email-content {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 22px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                min-width: auto;
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-container">
                <img src="{{ config('app.url') }}/images/logo.png" alt="DASAVENA Logo" class="logo">
            </div>
            <h1>📄 Nuevo Documento Pendiente</h1>
            <p>Sistema de Gestión de Proveedores</p>
        </div>
        
        <!-- Contenido -->
        <div class="email-content">
            <p class="greeting">Estimado equipo de Calidad,</p>
            
            <p class="message">
                Se ha cargado un nuevo documento que <strong>requiere validación inmediata</strong>. 
                Por favor, revise la información a continuación y proceda con la verificación correspondiente.
            </p>
            
            <div style="text-align: center;">
                <span class="priority-badge">⚡ Validación Pendiente</span>
            </div>
            
            <!-- Información del documento -->
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">🏢 Proveedor:</span>
                    <span class="info-value"><strong>{{ $providerName }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">🆔 RFC:</span>
                    <span class="info-value">{{ $providerRFC }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📋 Tipo de Documento:</span>
                    <span class="info-value"><strong>{{ $documentType }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">📎 Archivo:</span>
                    <span class="info-value">{{ $fileName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📅 Fecha de Carga:</span>
                    <span class="info-value">{{ $uploadedAt }}</span>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <p class="message" style="text-align: center; color: #6A2C75; font-weight: 500;">
                ⏱️ Se recomienda validar este documento en las próximas <strong>24 horas</strong>
            </p>
            
            <!-- Botón de acción -->
            <div class="cta-container">
                <a href="{{ $validationUrl }}" class="cta-button">
                    ✓ Ir a Validar Documentos
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <p class="footer-text">
                <strong>Nota:</strong> Este es un correo automático, por favor no responder directamente a este mensaje.
            </p>
            <p class="footer-text">
                Para cualquier duda, contacte al administrador del sistema.
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