<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Receipt Transaksi' }} - POS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @livewireStyles

    @vite(['resources/css/receipt.css'])

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background-color: var(--color-bg-body, #f3f6f9);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        main {
            display: flex;
            justify-content: center;
        }

        main > .receipt-solo {
            margin: 4rem auto;
        }

        .back-to-pos {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-text-secondary, #334155);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            transition: color 0.2s ease;
        }

        .back-to-pos:hover {
            color: var(--color-primary, #2563eb);
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .receipt-sidebar {
                display: none !important;
            }

            .receipt-container,
            .receipt-container * {
                visibility: visible;
            }

            .receipt-container,
            .receipt-preview-wrapper,
            .receipt-scroll-area {
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                background: none !important;
            }

            .receipt-preview-wrapper {
                visibility: none !important;
            }

            .receipt-container {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .thermal-receipt {
                margin: 0 auto !important;
            }

            #printable-receipt img {
                filter: grayscale(1) contrast(1.2) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <x-notify />

    @if (request()->routeIs('cashier.receipt'))
        <a href="{{ route('filament.admin.pages.cashier') }}" class="back-to-pos" wire:navigate>
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Kasir
        </a>
    @endif

    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>