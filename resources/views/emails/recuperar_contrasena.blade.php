<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        .container {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }
        .content {
            background: #ffffff;
            padding: 24px;
            border-radius: 10px;
            max-width: 520px;
            margin: 0 auto;
        }
        .btn {
            background: #0d6efd;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 7px;
            display: inline-block;
        }
        .nota {
            color: #666666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h2>Recuperación de acceso</h2>

            <p>Hola, {{ $usuario->nombre }}.</p>

            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Usa el siguiente botón para elegir una nueva:</p>

            <p>
                <a href="{{ $url }}" class="btn" style="color: white;">Restablecer contraseña</a>
            </p>

            <p class="nota">El enlace es de un solo uso y caduca en {{ $minutos }} minutos.</p>

            <p class="nota">Si el botón no funciona, copia y pega esta dirección en tu navegador:<br>{{ $url }}</p>

            <p class="nota">Si no solicitaste este cambio, ignora este mensaje: tu contraseña seguirá igual.</p>
        </div>
    </div>
</body>
</html>
