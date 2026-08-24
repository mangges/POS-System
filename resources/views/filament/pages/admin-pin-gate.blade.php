<x-filament-panels::page>
    <div
        class="pin-gate"
        x-data="{
            pin: @entangle('pin'),
            appendPin(digit) { if (this.pin.length < 6) this.pin += digit; },
            backspacePin() { this.pin = this.pin.slice(0, -1); },
            clearPin() { this.pin = ''; },
            handleKeydown(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                if (e.key >= '0' && e.key <= '9') {
                    this.appendPin(e.key);
                } else if (e.key === 'Backspace') {
                    this.backspacePin();
                } else if (e.key === 'Escape') {
                    this.clearPin();
                } else if (e.key === 'Enter' && this.pin.length === 6) {
                    $wire.submit();
                }
            },
        }"
        @keydown.window="handleKeydown($event)"
    >
        <div class="pin-gate__card">
            <svg class="pin-gate__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>

            <h1 class="pin-gate__title">Perlu PIN Admin</h1>
            <p class="pin-gate__subtitle">
                Akses ke <strong>{{ str($resource)->headline() }}</strong> dikunci. Minta admin memasukkan PIN untuk membuka.
            </p>

            <div class="pin-gate__form">
                <div class="pin-gate__display" role="group" aria-label="PIN admin, 6 digit">
                    <template x-for="i in 6" :key="i">
                        <div class="pin-gate__dot" :class="pin.length >= i ? 'is-filled' : ''"></div>
                    </template>
                </div>

                <div class="pin-gate__keypad" role="group" aria-label="Keypad angka PIN">
                    @foreach (range(1, 9) as $digit)
                        <button
                            type="button"
                            class="pin-gate__key"
                            x-on:click="appendPin('{{ $digit }}')"
                        >{{ $digit }}</button>
                    @endforeach

                    <button type="button" class="pin-gate__key pin-gate__key--muted" x-on:click="clearPin()">
                        C
                    </button>
                    <button type="button" class="pin-gate__key" x-on:click="appendPin('0')">0</button>
                    <button type="button" class="pin-gate__key pin-gate__key--muted" x-on:click="backspacePin()" aria-label="Hapus satu digit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-6-6 6-6Z" />
                            <path d="M13 10l4 4m0-4-4 4" />
                        </svg>
                    </button>
                </div>

                <x-filament::button
                    type="button"
                    size="lg"
                    class="pin-gate__submit"
                    x-on:click="$wire.submit()"
                    x-bind:disabled="pin.length < 6"
                >
                    Buka Akses
                </x-filament::button>
            </div>
        </div>
    </div>

    <style>
        .fi-header-heading, .site-header {
            display: none;
        }
        .pin-gate {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-block: clamp(1rem, 5vh, 3rem);
        }

        .pin-gate__card {
            width: 100%;
            max-width: 22rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 2rem 1.5rem;
            border-radius: 1rem;
            background-color: var(--gray-50, #f9fafb);
            border: 1px solid var(--gray-200, #e5e7eb);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            animation: pin-gate-fade-in 0.4s ease-out forwards;
        }

        @keyframes pin-gate-fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        :is(.dark) .pin-gate__card {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .pin-gate__icon {
            width: 2.75rem;
            height: 2.75rem;
            color: var(--primary-600, #2563eb);
            margin-bottom: 0.25rem;
        }

        .pin-gate__title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-950, #030712);
            text-align: center;
        }

        :is(.dark) .pin-gate__title {
            color: var(--gray-50, #f9fafb);
        }

        .pin-gate__subtitle {
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--gray-500, #6b7280);
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .pin-gate__subtitle strong {
            color: var(--gray-700, #374151);
            font-weight: 600;
        }

        :is(.dark) .pin-gate__subtitle strong {
            color: var(--gray-300, #d1d5db);
        }

        .pin-gate__form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
        }

        .pin-gate__display {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .pin-gate__dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--gray-100, #f1f5f9);
            border: 2px solid var(--gray-200, #e5e7eb);
            transition: all 0.2s ease;
        }

        .pin-gate__dot.is-filled {
            background: var(--primary-600, #2563eb);
            border-color: var(--primary-600, #2563eb);
            box-shadow: 0 0 8px rgba(37, 99, 235, 0.4);
        }

        :is(.dark) .pin-gate__dot {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .pin-gate__keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            width: 100%;
        }

        .pin-gate__key {
            appearance: none;
            border: 1px solid var(--gray-200, #e2e8f0);
            background-color: var(--gray-50, #f8fafc);
            color: var(--gray-950, #030712);
            border-radius: 0.625rem;
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1;
            aspect-ratio: 1.5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease;
        }

        .pin-gate__key svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .pin-gate__key:hover {
            background-color: var(--gray-100, #f1f5f9);
            border-color: var(--gray-300, #cbd5e1);
        }

        .pin-gate__key:active {
            transform: scale(0.98);
            background-color: var(--gray-200, #e2e8f0);
        }

        .pin-gate__key:focus-visible {
            outline: 2px solid var(--primary-600, #2563eb);
            outline-offset: 2px;
        }

        :is(.dark) .pin-gate__key {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: var(--gray-50, #f9fafb);
        }

        :is(.dark) .pin-gate__key:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .pin-gate__key--muted {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-500, #64748b);
        }

        .pin-gate__key--muted:hover {
            color: var(--danger-600, #ef4444);
        }

        .pin-gate__submit {
            width: 100%;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }

        .pin-gate__cancel {
            appearance: none;
            background: none;
            border: none;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-500, #6b7280);
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .pin-gate__cancel:hover {
            color: var(--gray-700, #374151);
        }

        @media (prefers-reduced-motion: reduce) {
            .pin-gate__key, .pin-gate__card {
                transition: none;
                animation: none;
            }
        }
    </style>
</x-filament-panels::page>
