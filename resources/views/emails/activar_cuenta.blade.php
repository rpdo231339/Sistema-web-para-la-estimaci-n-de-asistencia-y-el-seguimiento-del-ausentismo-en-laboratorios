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
            background: #198754;
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
            <h2>Activa tu cuenta</h2>

            <p>Hola, {{ $usuario->nombre }}.</p>

            <p>El administrador creó una cuenta para ti en el sistema de asistencia con el rol de <strong>{{ $usuario->rol }}</strong>. Para empezar a usarla, elige tu propia contraseña con el siguiente botón:</p>

            <p>
                <a href="{{ $url }}" class="btn" style="color: white;">Activar mi cuenta</a>
            </p>

            <p class="nota">El enlace es de un solo uso y caduca en {{ $horas }} horas. Si caduca, pide al administrador que te envíe otro.</p>

            <p class="nota">Si el botón no funciona, copia y pega esta dirección en tu navegador:<br>{{ $url }}</p>

            <p class="nota">Si no esperabas este mensaje, ignóralo: la cuenta no se activará sin tu contraseña.</p>
        </div>
    </div>
</body>
</html>
