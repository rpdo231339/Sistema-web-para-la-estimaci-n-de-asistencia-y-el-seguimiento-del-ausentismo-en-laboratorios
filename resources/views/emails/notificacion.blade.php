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
            <h2>{{ $notificacion->titulo }}</h2>

            <p>{{ $notificacion->mensaje }}</p>

            @if ($url)
                <p>
                    <a href="{{ $url }}" class="btn" style="color: white;">Abrir en el sistema</a>
                </p>
            @endif

            <p class="nota">Este aviso también aparece en tu bandeja de notificaciones del sistema.</p>
        </div>
    </div>
</body>
</html>
