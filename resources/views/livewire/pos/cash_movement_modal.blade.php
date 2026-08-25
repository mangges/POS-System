<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>{{ __('pos.Cash In / Out') }}</h3>
        <button type="button" wire:click="closeCashMovementModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form wire:submit.prevent="recordCashMovement" class="cash-movement-form">
        <div class="cash-movement-type-toggle">
            <button type="button" wire:click="$set('cashMovementType', 'in')" class="cash-movement-type-btn @if($cashMovementType === 'in') active-in @endif">
                <i class="bi bi-box-arrow-in-down"></i> {{ __('pos.Cash In') }}
            </button>
            <button type="button" wire:click="$set('cashMovementType', 'out')" class="cash-movement-type-btn @if($cashMovementType === 'out') active-out @endif">
                <i class="bi bi-box-arrow-up"></i> {{ __('pos.Cash Out') }}
            </button>
        </div>

        <label for="cashMovementAmount">{{ __('pos.Nominal (Rp)') }}</label>
        <x-currency-input
            model="cashMovementAmount"
            id="cashMovementAmount"
            class="shift-gate-input"
            placeholder="Rp 0"
            autofocus
        />
        @error('cashMovementAmount') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <label for="cashMovementReason">{{ __('pos.Alasan') }}</label>
        <textarea id="cashMovementReason" wire:model="cashMovementReason" class="shift-gate-input cash-movement-reason" rows="2" placeholder="{{ __('pos.mis. setor ke bank, tambah modal...') }}"></textarea>
        @error('cashMovementReason') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <button type="submit" class="checkout-btn shift-gate-submit">
            <i class="bi bi-check-lg"></i>
            <span>{{ __('pos.Simpan') }}</span>
        </button>
    </form>
</div>
