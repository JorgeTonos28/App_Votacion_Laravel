<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Invitación a Innovamente</title>
</head>
<body style="font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #17233d; background-color: #f8fafc; margin: 0; padding: 30px 20px;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <tr>
            <td style="background: #042e80; padding: 24px 32px; text-align: left;">
                <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em;">Innovamente</h1>
                <p style="color: #93c5fd; margin: 4px 0 0; font-size: 13px;">Plataforma de Evaluación y Votación</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h2 style="margin: 0 0 16px; font-size: 18px; color: #0f172a;">Hola, {{ $user->name }}</h2>
                <p style="margin: 0 0 16px; font-size: 15px; color: #334155;">Has sido invitado a formar parte del equipo de gestión en la plataforma <strong>Innovamente</strong> con el rol de <strong>{{ $user->role === 'Administrator' ? 'Administrador' : ($user->role === 'Operator' ? 'Operador' : 'Auditor') }}</strong>.</p>
                <p style="margin: 0 0 24px; font-size: 14px; color: #64748b;">Para activar tu cuenta y configurar tu contraseña de acceso personal, haz clic en el siguiente botón:</p>
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ $activationUrl }}" style="display: inline-block; padding: 14px 28px; background: #042e80; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; box-shadow: 0 2px 4px rgba(4, 46, 128, 0.2);">Activar mi cuenta y crear contraseña</a>
                </div>
                <p style="margin: 24px 0 8px; font-size: 12px; color: #64748b;">Este enlace de activación expirará en 3 días. Si no reconoces esta invitación, puedes ignorar este mensaje.</p>
                <p style="margin: 0; font-size: 12px; color: #94a3b8; word-break: break-all;">Si el botón no funciona, copia y pega este enlace en tu navegador:<br><a href="{{ $activationUrl }}" style="color: #0284c7;">{{ $activationUrl }}</a></p>
            </td>
        </tr>
        <tr>
            <td style="background: #f1f5f9; padding: 16px 32px; text-align: center; font-size: 12px; color: #64748b;">
                © {{ date('Y') }} Innovamente. Todos los derechos reservados.
            </td>
        </tr>
    </table>
</body>
</html>
