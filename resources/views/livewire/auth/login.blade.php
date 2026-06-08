<div class="auth-container">
    <div class="auth-header">
        <h1>POS System</h1>
        <p>Silakan masuk untuk melanjutkan</p>
    </div>

    <div class="mode-toggle">
        <button type="button" 
                class="mode-btn {{ $loginMode === 'pin' ? 'active' : '' }}"
                wire:click="switchMode('pin')">
            Login dengan PIN
        </button>
        <button type="button" 
                class="mode-btn {{ $loginMode === 'email' ? 'active' : '' }}"
                wire:click="switchMode('email')">
            Login dengan Email
        </button>
    </div>

    @if ($loginMode === 'pin')
        <div class="login-pin"
             x-data="{ 
                 pin: @entangle('pin'),
                 appendPin(digit) { if (this.pin.length < 6) this.pin += digit; },
                 backspacePin() { this.pin = this.pin.slice(0, -1); },
                 clearPin() { this.pin = ''; },
                 handleKeydown(e) {
                     if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                     if (e.key >= '0' && e.key <= '9') {
                         this.appendPin(e.key);
                     } else if (e.key === 'Backspace') {
                         this.backspacePin();
                     } else if (e.key === 'Escape' || e.key.toLowerCase() === 'c') {
                         this.clearPin();
                     } else if (e.key === 'Enter') {
                         if (this.pin.length === 6) {
                             $wire.loginWithPin();
                         }
                     }
                 }
             }"
             @keydown.window="handleKeydown($event)">
             
            <div class="pin-display">
                <template x-for="i in 6" :key="i">
                    <div class="pin-dot" :class="pin.length >= i ? 'filled' : ''"></div>
                </template>
            </div>
            
            @error('pin') <div class="error-message mb-4">{{ $message }}</div> @enderror

            <div class="pin-pad">
                @for ($i = 1; $i <= 9; $i++)
                    <button type="button" class="pin-btn" @click="appendPin('{{ $i }}')">{{ $i }}</button>
                @endfor
                <button type="button" class="pin-btn action-btn" @click="clearPin()">C</button>
                <button type="button" class="pin-btn" @click="appendPin('0')">0</button>
                <button type="button" class="pin-btn action-btn" @click="backspacePin()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="18" y1="9" x2="12" y2="15"></line><line x1="12" y1="9" x2="18" y2="15"></line></svg>
                </button>
            </div>

            <button type="button" class="btn-submit" wire:click="loginWithPin" x-bind:disabled="pin.length < 6">
                Masuk
            </button>
        </div>
    @else
        <form wire:submit.prevent="loginWithEmail" class="login-email">
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" wire:model="email" class="form-control" placeholder="nama@email.com" required autofocus>
                @error('email') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" wire:model="password" class="form-control" placeholder="••••••••" required>
                @error('password') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember" wire:model="remember">
                <label for="remember">Ingat Saya</label>
            </div>

            <button type="submit" class="btn-submit">
                Masuk
            </button>
        </form>
    @endif
</div>
