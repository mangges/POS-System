<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $loginMode = 'pin';

    public $pin = '';

    public $email = '';
    public $password = '';
    public $remember = false;

    public function switchMode($mode)
    {
        $this->loginMode = $mode;
        $this->resetValidation();
        $this->reset(['pin', 'email', 'password']);
    }

    public function appendPin($digit)
    {
        if (strlen($this->pin) < 6) {
            $this->pin .= $digit;
        }
    }

    public function backspacePin()
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    public function clearPin()
    {
        $this->pin = '';
    }

    public function loginWithPin()
    {
        $this->validate([
            'pin' => 'required|string',
        ]);

        // Find user by PIN
        $user = \App\Models\User::where('pin', $this->pin)->first();

        if ($user) {
            Auth::login($user);
            session()->regenerate();
            if ($user->role === 'admin') {
                return redirect('/admin');
            }
            return redirect('/cashier');
        }

        $this->addError('pin', 'PIN yang Anda masukkan salah.');
        $this->pin = '';
    }

    public function loginWithEmail()
    {
        $credentials = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();
            
            $user = Auth::user();
            if (in_array($user->role, ['admin'])) {
                return redirect('/admin');
            }
            return redirect('/cashier');
        }

        $this->addError('email', 'Kredensial yang diberikan tidak cocok dengan catatan kami.');
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.auth');
    }
}
