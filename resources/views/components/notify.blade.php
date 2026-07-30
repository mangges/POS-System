{{--
    Shared notification/toast component. Self-contained (own CSS + JS) so it
    renders identically across layouts that don't share CSS variables or a
    bundled JS entry (app / auth / receipt).

    Trigger it two ways:
      1. Session flash (works with redirects): ->with('message', '...')->with('type', 'success')
      2. Livewire event (no reload needed):    $this->dispatch('notify', message: '...', type: 'success')

    Type values: success, danger, warning, info
--}}
<div
    x-data="posNotifyBoard()"
    x-init="init()"
    class="pos-notify-container"
    aria-live="polite"
    aria-atomic="true"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pos-notify-toast"
            :class="{
                'pos-notify--leaving': toast.leaving,
                'pos-notify--dragging': toast.dragging,
                ['pos-notify--' + toast.type]: true
            }"
            :style="{ '--pos-notify-drag-y': toast.dragY + 'px' }"
            @pointerdown="dragStart($event, toast)"
            @pointermove="dragMove($event, toast)"
            @pointerup="dragEnd($event, toast)"
            @pointercancel="dragEnd($event, toast)"
        >
            <span class="pos-notify-icon" aria-hidden="true">
                <template x-if="toast.type === 'success'">
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="none"><path d="M5 10.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </template>
                <template x-if="toast.type === 'danger'">
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="none"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="none"><path d="M10 3l8 14H2L10 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 8.5v3.5M10 14.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="none"><path d="M10 9v5M10 6.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/></svg>
                </template>
            </span>
            <span class="pos-notify-text" x-text="toast.message"></span>
            <button type="button" class="pos-notify-close" @click="dismiss(toast.id)" aria-label="Tutup notifikasi">&times;</button>
        </div>
    </template>
</div>

<script>
    function posNotifyBoard() {
        return {
            toasts: [],
            _seq: 0,
            _timers: {},

            init() {
                @if (session()->has('message'))
                    this.push(@js(session('message')), @js(session('type', 'success')));
                @endif

                Livewire.on('notify', (event) => {
                    this.push(event.message, event.type ?? 'info');
                });
            },

            push(message, type = 'info') {
                const id = ++this._seq;
                this.toasts.push({ id, message, type, leaving: false, dragging: false, dragY: 0 });
                this._timers[id] = setTimeout(() => this.dismiss(id), 3000);
            },

            dismiss(id) {
                const toast = this.toasts.find(t => t.id === id);
                if (!toast || toast.leaving) return;

                clearTimeout(this._timers[id]);
                delete this._timers[id];
                toast.leaving = true;

                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 250);
            },

            dragStart(e, toast) {
                toast.dragging = true;
                toast._startY = e.clientY;
                clearTimeout(this._timers[toast.id]);
                e.currentTarget.setPointerCapture(e.pointerId);
            },

            dragMove(e, toast) {
                if (!toast.dragging) return;
                toast.dragY = Math.min(0, e.clientY - toast._startY);
            },

            dragEnd(e, toast) {
                if (!toast.dragging) return;
                toast.dragging = false;

                if (toast.dragY < -40) {
                    this.dismiss(toast.id);
                    return;
                }

                toast.dragY = 0;
                this._timers[toast.id] = setTimeout(() => this.dismiss(toast.id), 3000);
            },
        };
    }
</script>

<style>
    .pos-notify-container {
        --pos-notify-success-bg: rgba(21, 128, 61, 0.92);
        --pos-notify-danger-bg: rgba(185, 28, 28, 0.92);
        --pos-notify-warning-bg: rgba(180, 83, 9, 0.92);
        --pos-notify-info-bg: rgba(29, 78, 216, 0.92);
        --pos-notify-fg: #ffffff;
        --pos-notify-radius: 6px;
        --pos-notify-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);

        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: min(360px, calc(100vw - 32px));
        pointer-events: none;
    }

    .pos-notify-toast {
        pointer-events: auto;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: var(--pos-notify-radius);
        box-shadow: var(--pos-notify-shadow);
        color: var(--pos-notify-fg);
        font: 500 14px/1.4 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--pos-notify-info-bg);
        backdrop-filter: saturate(160%) blur(4px);
        -webkit-backdrop-filter: saturate(160%) blur(4px);
        transform: translateY(var(--pos-notify-drag-y, 0px));
        animation: pos-notify-in 0.28s cubic-bezier(.21, 1.02, .73, 1) both;
        touch-action: none;
    }

    .pos-notify-toast:not(.pos-notify--dragging) {
        transition: transform 0.2s ease;
    }

    .pos-notify--success { background: var(--pos-notify-success-bg); }
    .pos-notify--danger { background: var(--pos-notify-danger-bg); }
    .pos-notify--warning { background: var(--pos-notify-warning-bg); }
    .pos-notify--info { background: var(--pos-notify-info-bg); }

    .pos-notify--leaving {
        animation: pos-notify-out 0.22s ease-in forwards;
    }

    .pos-notify--dragging {
        transition: none;
    }

    .pos-notify-icon {
        display: flex;
        flex-shrink: 0;
    }

    .pos-notify-text {
        flex: 1;
        line-height: 1.4;
    }

    .pos-notify-close {
        flex-shrink: 0;
        background: none;
        border: none;
        color: inherit;
        opacity: 0.75;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
    }

    .pos-notify-close:hover {
        opacity: 1;
    }

    @keyframes pos-notify-in {
        from { transform: translateY(-130%); opacity: 0; }
        to { transform: translateY(var(--pos-notify-drag-y, 0px)); opacity: 1; }
    }

    @keyframes pos-notify-out {
        from { transform: translateY(var(--pos-notify-drag-y, 0px)); opacity: 1; }
        to { transform: translateY(-130%); opacity: 0; }
    }

    @media print {
        .pos-notify-container {
            display: none !important;
        }
    }
</style>
