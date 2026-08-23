<x-filament-panels::page>
    <div
        class="pin-gate"
        x-data="{
            press(digit) {
                const el = $refs.pinInput;
                if (el.value.length >= 6) return;
                el.value += digit;
                el.dispatchEvent(new Event('input'));
            },
            backspace() {
                const el = $refs.pinInput;
                el.value = el.value.slice(0, -1);
                el.dispatchEvent(new Event('input'));
            },
            clear() {
                const el = $refs.pinInput;
                el.value = '';
                el.dispatchEvent(new Event('input'));
                el.focus();
            },
        }"
    >
        <div class="pin-gate__card">
            <svg class="pin-gate__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>

            <h1 class="pin-gate__title">Perlu PIN Admin</h1>
            <p class="pin-gate__subtitle">
                Akses ke <strong>{{ str($resource)->headline() }}</strong> dikunci. Minta admin memasukkan PIN untuk membuka.
            </p>

            <form wire:submit="submit" class="pin-gate__form">
                <x-filament::input.wrapper class="pin-gate__input-wrapper">
                    <x-filament::input
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        wire:model="pin"
                        x-ref="pinInput"
                        placeholder="——————"
                        autofocus
                        autocomplete="one-time-code"
                        aria-label="PIN admin, 6 digit"
                        class="pin-gate__input"
                    />
                </x-filament::input.wrapper>

                <div class="pin-gate__keypad" role="group" aria-label="Keypad angka PIN">
                    @foreach (range(1, 9) as $digit)
                        <button
                            type="button"
                            class="pin-gate__key"
                            x-on:click="press('{{ $digit }}')"
                        >{{ $digit }}</button>
                    @endforeach

                    <button type="button" class="pin-gate__key pin-gate__key--muted" x-on:click="clear()">
                        Hapus
                    </button>
                    <button type="button" class="pin-gate__key" x-on:click="press('0')">0</button>
                    <button type="button" class="pin-gate__key pin-gate__key--muted" x-on:click="backspace()" aria-label="Hapus satu digit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-6-6 6-6Z" />
                            <path d="M13 10l4 4m0-4-4 4" />
                        </svg>
                    </button>
                </div>

                <x-filament::button type="submit" size="lg" class="pin-gate__submit">
                    Buka Akses
                </x-filament::button>
            </form>

            <button type="button" class="pin-gate__cancel" onclick="history.back()">
                Batal &amp; kembali
            </button>
        </div>
    </div>

    <style>
        .fi-main .fi-page-header-main-ctn { display: none; }

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

        .pin-gate__input-wrapper {
            width: 100%;
        }

        .pin-gate__input {
            text-align: center;
            font-size: 1.75rem;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.75rem;
            padding-inline-start: calc(0.75rem + 0.75em);
        }

        .pin-gate__keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.625rem;
            width: 100%;
        }

        .pin-gate__key {
            appearance: none;
            border: 1px solid var(--gray-200, #e5e7eb);
            background-color: var(--white, #ffffff);
            color: var(--gray-950, #030712);
            border-radius: 0.75rem;
            font-size: 1.375rem;
            font-weight: 500;
            line-height: 1;
            min-height: 3.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.1s ease, transform 0.05s ease;
        }

        .pin-gate__key svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .pin-gate__key:hover {
            background-color: var(--gray-50, #f9fafb);
        }

        .pin-gate__key:active {
            transform: scale(0.96);
            background-color: var(--gray-100, #f3f4f6);
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
            font-size: 0.9375rem;
            color: var(--gray-500, #6b7280);
        }

        .pin-gate__submit {
            width: 100%;
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
            .pin-gate__key {
                transition: none;
            }
        }
    </style>
</x-filament-panels::page>
