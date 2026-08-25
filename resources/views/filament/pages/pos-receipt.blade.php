<x-filament-panels::page>
    @vite(['resources/css/receipt.css'])

    <style>
        .fi-main .fi-page-header-main-ctn { padding-block: 0; }
        .fi-main { padding-inline: 0; }

        /* Same print technique as the old standalone receipt layout: hide
           everything, show only the receipt card. Being a generic
           hide-all/show-one rule (not naming Filament's chrome specifically),
           it hides the sidebar/topbar here for free. */
        @page {
            margin: 0;
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
                display: block !important;
                width: auto !important;
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

            .receipt-preview-wrapper::after {
                display: none !important;
            }

            #printable-receipt {
                margin: 0 auto !important;
                padding: 1.5rem !important;
            }

            #printable-receipt img {
                filter: grayscale(1) contrast(1.2) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <livewire:pos.receipt :order-id="$orderId" />
</x-filament-panels::page>
