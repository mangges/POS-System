<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System Kasir</title>
    @vite(['resources/css/app.css', 'resources/css/cashier.css', 'resources/js/app.js'])
    @livewireStyles
    <!-- Menggunakan font modern (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    {{ $slot }}

    @livewireScripts
</body>
</html>
