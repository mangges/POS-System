<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Login - POS System' }}</title>

        <!-- Custom Auth CSS -->
        <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
        
        <!-- Livewire Styles -->
        @livewireStyles
    </head>
    <body>
        {{ $slot }}

        <!-- Livewire Scripts -->
        @livewireScripts
    </body>
</html>
