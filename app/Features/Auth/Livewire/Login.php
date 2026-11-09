<?php

namespace App\Features\Auth\Livewire;

use App\Features\Auth\Actions\LoginUserAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Login - StockApp')]
class Login extends Component
{
    public string $login = '';
    public string $password = '';
    public bool $remember = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(LoginUserAction $action): mixed
    {
        $this->validate();

        if ($action->execute($this->login, $this->password, $this->remember)) {
            $user = Auth::user();
            if ($user && $user->isAdmin()) {
                return $this->redirectRoute('admin.users', navigate: true);
            }

            return $this->redirectRoute('stocks.index', navigate: true);
        }

        $this->addError('login', 'Username/email atau password yang Anda masukkan tidak sesuai.');
        return null;
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
