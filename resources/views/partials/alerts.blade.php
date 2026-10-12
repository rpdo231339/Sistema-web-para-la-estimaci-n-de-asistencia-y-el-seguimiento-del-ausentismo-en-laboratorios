@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center alerta-exito" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        <div><strong>¡Éxito!</strong> {{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <script>
        setTimeout(function () {
            let alerta = document.querySelector('.alerta-exito');

            if (alerta) {
                alerta.classList.remove('show');
                setTimeout(() => alerta.remove(), 500);
            }
        }, 3000);
    </script>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="fa-solid fa-circle-xmark me-2"></i>
        <div><strong>Error:</strong> {{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="fa-solid fa-circle-info me-2"></i>
        <div>{{ session('info') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif