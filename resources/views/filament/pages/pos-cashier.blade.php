<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/css/cashier.css', 'resources/js/app.js'])

    <style>
        .fi-main .pos-header { display: none; }
        .fi-main .fi-page-header-main-ctn { padding-block: 0; }
        .fi-main { padding-inline: 0; }
        .fi-main .pos-layout { height: calc(100vh - 8rem); }
    </style>

    <livewire:pos.cashier />
</x-filament-panels::page>
