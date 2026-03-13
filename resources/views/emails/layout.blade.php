<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DASAVENA - Notificación' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #F5F0F6;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(106, 44, 117, 0.1), 0 10px 40px 0 rgba(106, 44, 117, 0.2);
        }
        .email-header {
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            padding: 40px 20px;
            text-align: center;
        }
        .email-logo-img {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
        .email-logo {
            font-size: 32px;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 10px;
        }
        .email-subtitle {
            color: #E6D9E9;
            font-size: 14px;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-title {
            font-size: 24px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 20px;
        }
        .email-content {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 30px;
        }
        .info-box {
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
            border-left: 4px solid #6A2C75;
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }
        .info-box-title {
            font-weight: 600;
            color: #6A2C75;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid rgba(106, 44, 117, 0.1);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6A2C75;
            min-width: 150px;
            font-size: 14px;
        }
        .info-value {
            color: #2d3748;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 14px 0 rgba(106, 44, 117, 0.39);
            transition: all 0.3s;
        }
        .button:hover {
            background: linear-gradient(135deg, #5A2563 0%, #6A2C75 100%);
            box-shadow: 0 10px 40px 0 rgba(106, 44, 117, 0.2);
            transform: translateY(-2px);
        }
        .button-success {
            background: linear-gradient(135deg, #6A2C75 0%, #8B4A95 100%);
        }
        .button-danger {
            background: linear-gradient(135deg, #AA4969 0%, #C85A7E 100%);
            box-shadow: 0 4px 14px 0 rgba(170, 73, 105, 0.39);
        }
        .button-warning {
            background: linear-gradient(135deg, #D6A644 0%, #EED39B 100%);
            color: #4A3812 !important;
            box-shadow: 0 4px 14px 0 rgba(214, 166, 68, 0.39);
        }
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .alert-warning {
            background: linear-gradient(135deg, #FDF8ED 0%, #FAEFD3 100%);
            border-left: 4px solid #D6A644;
            color: #946F23;
        }
        .alert-danger {
            background: linear-gradient(135deg, #FCE7ED 0%, #F9CFD9 100%);
            border-left: 4px solid #AA4969;
            color: #7D324D;
        }
        .alert-success {
            background: linear-gradient(135deg, #E6D9E9 0%, #D4BBD9 100%);
            border-left: 4px solid #6A2C75;
            color: #491E50;
        }
        .alert-info {
            background: linear-gradient(135deg, #F5F0F6 0%, #E6D9E9 100%);
            border-left: 4px solid #BBA4C0;
            color: #5A2563;
        }
        .email-footer {
            background: linear-gradient(to bottom right, #F5F0F6, #E6D9E9);
            color: #6b7280;
            padding: 30px 20px;
            text-align: center;
            font-size: 14px;
        }
        .footer-links {
            margin: 20px 0;
        }
        .footer-links a {
            color: #6A2C75;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 600;
        }
        .footer-copyright {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 20px;
        }
        .footer-brand {
            color: #6A2C75;
            font-weight: 700;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 30px 20px;
            }
            .info-row {
                flex-direction: column;
            }
            .info-label {
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F5F0F6; padding: 20px 0;">
        <tr>
            <td align="center">
                <div class="email-wrapper">
                    <!-- Header -->
                    <div class="email-header">
                        <img src="{{ config('app.url') }}/images/logo.png" alt="DASAVENA Logo" class="email-logo-img">
                        <div class="email-subtitle">Sistema de Gestión de Proveedores</div>
                    </div>

                    <!-- Body -->
                    <div class="email-body">
                        @yield('content')
                    </div>

                    <!-- Footer -->
                    <div class="email-footer">
                        <p><strong class="footer-brand">DASAVENA</strong></p>
                        <p style="color: #6b7280; margin-top: 5px;">Sistema de Gestión de Proveedores</p>
                        <div class="footer-links">
                            <a href="{{ config('app.url') }}">Ir al Sistema</a>
                            <a href="{{ config('app.url') }}/ayuda">Ayuda</a>
                            <a href="{{ config('app.url') }}/contacto">Contacto</a>
                        </div>
                        <p class="footer-copyright">
                            &copy; {{ date('Y') }} <span class="footer-brand">DASAVENA</span>. Todos los derechos reservados.
                        </p>
                        <p style="font-size: 12px; color: #9ca3af; margin-top: 15px;">
                            Este es un mensaje automático, por favor no responder a este correo.
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>