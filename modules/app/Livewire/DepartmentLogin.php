<?php
namespace App\Livewire;

use App\Models\User;
use App\Services\AccessControl;
use App\Support\PortalLoginLimiter;
use App\Support\PortalTwoFactor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.portal-auth')]
class DepartmentLogin extends Component
{
    #[Locked] public string $department;
    public string $username = ''; public string $password = ''; public bool $remember = false;
    public function mount(string $department): void { abort_unless(config('portals.'.$department), 404); $this->department = $department; }
    public function login()
    {
        $this->validate(['username' => 'required|string|max:150', 'password' => 'required|string|max:1000', 'remember' => 'boolean']);
        PortalLoginLimiter::check($this->username, 'username');
        $user = User::where(fn ($q) => $q->where('email', trim($this->username))->orWhere('username', trim($this->username)))->first();
        if (!$user || !Hash::check($this->password, $user->password) || !app(AccessControl::class)->allows($user, $this->department)) {
            throw ValidationException::withMessages(['username' => 'These credentials do not have access to this department. Ask your administrator to assign access.']);
        }
        $home = config('portals.'.$this->department.'.home');
        Auth::logout(); session()->forget('supplier'); session(['url.intended' => route($home)]);
        $credentials = ['email' => $user->email, 'password' => $this->password];
        if (PortalTwoFactor::challenge($credentials, $this->remember)) return redirect()->route('two-factor.login');
        if (!Auth::attempt($credentials, $this->remember)) throw ValidationException::withMessages(['username' => 'Unable to sign in.']);
        session()->regenerate(); session()->forget('url.intended');
        return redirect()->route($home);
    }
    public function render() { return view('livewire.department-login', ['portal' => config('portals.'.$this->department)]); }
}
