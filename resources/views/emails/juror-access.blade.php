<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Acceso de jurado</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.5;color:#17233d">
    <h1>Acceso de jurado</h1>
    <p>Hola {{ $juror->name }}, ya puedes acceder al panel de evaluación.</p>
    <p><strong>Evento:</strong> {{ $event->name }}<br><strong>Código del evento:</strong> {{ $event->code }}</p>
    <p><strong>Código personal:</strong> {{ $code }}</p>
    <p><a href="{{ $accessUrl }}" style="display:inline-block;padding:12px 18px;background:#042e80;color:#fff;text-decoration:none;border-radius:6px">Abrir acceso de jurado</a></p>
    <p>El enlace rellena automáticamente el código del evento y tu código personal. Si el enlace no funciona, entra a la página de acceso e introduce ambos códigos.</p>
</body>
</html>
