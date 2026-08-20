{{--
    Notification bell for public pages (landing page). Session-only by design:
    state lives in an Alpine.store (registered once via alpine:init, which
    Alpine fires exactly once per page load regardless of how many times
    Livewire re-renders this component) fed by the 'notify' Livewire event
    (same event notify.blade.php's toast listens to — see the Echo listener
    in landing-page.blade.php). A per-component Livewire.on() would have
    re-subscribed on every Livewire re-render, stacking duplicate listeners —
    the store is registered outside that lifecycle so it only ever happens
    once. Nothing is persisted or fetched from the server, so a fresh page
    load (new customer scanning the same table) always starts empty.
--}}
<div x-data="{ open: false, toggle() { this.open = !this.open; if (this.open) { $store.notifBell.unread = 0; } } }" class="pos-bell" @click.outside="open = false">
    <button type="button" class="pos-bell-trigger" @click="toggle()" aria-label="Notifikasi">
        <i class="bi bi-bell"></i>
        <span class="pos-bell-badge" x-show="$store.notifBell.unread > 0" x-text="$store.notifBell.unread > 9 ? '9+' : $store.notifBell.unread" x-cloak></span>
    </button>

    <div class="pos-bell-panel" x-show="open" x-cloak @click.outside="open = false"
        x-transition:enter="pos-bell-panel-enter" x-transition:enter-start="pos-bell-panel-enter-start" x-transition:enter-end="pos-bell-panel-enter-end">
        <div class="pos-bell-panel-header">Notifikasi</div>
        <template x-if="$store.notifBell.items.length === 0">
            <div class="pos-bell-empty">Belum ada notifikasi</div>
        </template>
        <ul class="pos-bell-list">
            <template x-for="item in $store.notifBell.items" :key="item.id">
                <li class="pos-bell-item" :class="'pos-bell-item--' + item.type">
                    <span class="pos-bell-item-text" x-text="item.message"></span>
                    <span class="pos-bell-item-time" x-text="item.time"></span>
                </li>
            </template>
        </ul>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    .pos-bell { position: relative; margin-left: auto; }

    .pos-bell-trigger {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: 1px solid var(--color-border, #ececec);
        border-radius: var(--radius-full, 9999px);
        background: var(--color-bg, #fff);
        color: var(--color-text-main, #17181c);
        font-size: 1.1rem;
        cursor: pointer;
    }

    .pos-bell-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: var(--radius-full, 9999px);
        background: #e03131;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
    }

    .pos-bell-panel {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        width: 300px;
        max-height: 360px;
        overflow-y: auto;
        background: var(--color-bg, #fff);
        border: 1px solid var(--color-border, #ececec);
        border-radius: var(--radius-md, 10px);
        box-shadow: var(--shadow-lg, 0 12px 24px rgba(17,17,17,0.09));
        z-index: 50;
    }

    .pos-bell-panel-enter { transition: opacity 0.15s ease, transform 0.15s ease; }
    .pos-bell-panel-enter-start { opacity: 0; transform: translateY(-4px); }
    .pos-bell-panel-enter-end { opacity: 1; transform: translateY(0); }

    .pos-bell-panel-header {
        padding: 0.75rem 1rem;
        font-weight: 600;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--color-border, #ececec);
    }

    .pos-bell-empty {
        padding: 1.5rem 1rem;
        text-align: center;
        color: var(--color-text-muted, #74777d);
        font-size: 0.85rem;
    }

    .pos-bell-list { list-style: none; margin: 0; padding: 0.25rem 0; }

    .pos-bell-item {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        padding: 0.6rem 1rem;
        border-left: 3px solid var(--color-border, #ececec);
        font-size: 0.82rem;
    }

    .pos-bell-item--success { border-left-color: #2f9e44; }
    .pos-bell-item--danger { border-left-color: #e03131; }
    .pos-bell-item--warning { border-left-color: #f08c00; }
    .pos-bell-item--info { border-left-color: #1c7ed6; }

    .pos-bell-item-text { color: var(--color-text-main, #17181c); }
    .pos-bell-item-time { color: var(--color-text-muted, #74777d); font-size: 0.72rem; }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        if (window.__posNotifBellStoreRegistered) {
            return;
        }
        window.__posNotifBellStoreRegistered = true;

        Alpine.store('notifBell', {
            items: [],
            unread: 0,
            seq: 0,
        });

        Livewire.on('notify', (event) => {
            const store = Alpine.store('notifBell');
            store.items.unshift({
                id: ++store.seq,
                message: event.message,
                type: event.type ?? 'info',
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
            });
            store.unread++;
        });
    });
</script>
