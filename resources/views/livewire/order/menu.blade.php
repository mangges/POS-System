<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pesan menu langsung dari meja Anda — {{ $table->name ?? 'Self Order' }}">
    <title>Pesan Sekarang — {{ $table->name ?? 'SavorPOS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{--
        Pass the validated table model into the LandingPage Livewire component.
        The component reads `table` as a mount() argument so it can display the
        table name banner and attach the table_id to the order when submitted.
        The session has already been written by OrderController so Livewire can
        also fall back to session('order_table_id') if needed.
    --}}
    <livewire:landing-page.landing-page :table="$table->name" />

    @livewireScripts
</body>
</html>
