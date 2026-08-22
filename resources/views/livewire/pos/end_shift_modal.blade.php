<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>Tutup Shift</h3>
        <button type="button" wire:click="closeEndShiftModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form wire:submit.prevent="endShift" class="cash-movement-form">
        <label>Expected Cash (sistem)</label>
        <input type="text" class="shift-gate-input" value="Rp {{ number_format($endShiftExpectedCash ?? 0, 0, ',', '.') }}" disabled>

        <label for="endShiftActualCash">Actual Cash (hasil hitung fisik)</label>
        <x-currency-input
            model="endShiftActualCash"
            id="endShiftActualCash"
            class="shift-gate-input"
            placeholder="Rp 0"
            autofocus
        />
        @error('endShiftActualCash') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <label for="endShiftNote">Catatan (opsional)</label>
        <textarea id="endShiftNote" wire:model="endShiftNote" class="shift-gate-input cash-movement-reason" rows="2" placeholder="Catatan kalau ada selisih..."></textarea>

        <button type="submit" class="checkout-btn shift-gate-submit">
            <i class="bi bi-lock-fill"></i>
            <span>Tutup Shift</span>
        </button>
    </form>
</div>
