<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System Kasir</title>
    @vite(['resources/css/app.css', 'resources/css/cashier.css', 'resources/js/app.js'])
    @stack('scripts')
    @livewireStyles
    <!-- Menggunakan font modern (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    @if (session()->has('message'))
        <div id="alertModal" class="alert-modal alert-{{ session('type', 'success') }}">
            <span class="alert-text">{{ session('message') }}</span>
            <button type="button" class="alert-close" onclick="closeAlert()">&times;</button>
        </div>
    @endif

    {{ $slot }}

    @livewireScripts

    @if (session()->has('message'))
        <script>
            function closeAlert() {
                const alertEl = document.getElementById('alertModal');
                if (alertEl) {
                    alertEl.classList.add('alert-hide');
                    setTimeout(() => alertEl.remove(), 300);
                }
            }

            setTimeout(closeAlert, 3000);
        </script>
    @endif
</body>
</html>
