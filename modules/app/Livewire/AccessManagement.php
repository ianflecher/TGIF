<?php
namespace App\Livewire;

use App\Services\AccessControl;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.erp')]
class AccessManagement extends Component
{
    public int $userId = 0; public bool $fullAccess = false; public array $matrix = [];
    #[\Livewire\Attributes\Locked] public int $loadedUserId = 0;
    #[\Livewire\Attributes\Locked] public string $selectedRole = 'admin';
    private function guard(): void { app(AccessControl::class)->authorize(auth()->user(), 'access', 'approve'); abort_unless(DB::table('users')->where('user_id', auth()->id())->value('module_permissions') === null, 403); }
    public function loadUser(): void
    {
        $this->guard(); $user = DB::table('users')->where('user_id', $this->userId)->whereIn('role', ['admin', 'employee', 'manager'])->whereNull('deleted_at')->first(); abort_unless($user, 404);
        $this->loadedUserId = $user->user_id; $this->selectedRole = $user->role;
        $this->fullAccess = $user->role === 'admin' && $user->module_permissions === null; $this->matrix = [];
        foreach (json_decode($user->module_permissions ?? '{}', true) as $module => $abilities) foreach ($abilities as $ability) $this->matrix[$module][$ability] = true;
    }
    public function save(): void
    {
        $this->guard(); $permissions = [];
        abort_unless($this->loadedUserId === $this->userId && $this->userId > 0, 422, 'Load the selected staff account before saving.');
        foreach (AccessControl::MODULES as $module) {
            $abilities = array_keys(array_filter($this->matrix[$module] ?? [], fn ($enabled) => $enabled === true));
            if ($abilities) $permissions[$module] = array_values(array_unique(array_merge(['read'], $abilities)));
        }
        if ($this->selectedRole !== 'admin') $permissions = array_intersect_key($permissions, ['inventory' => true]);
        app(AccessControl::class)->configure(auth()->user(), $this->userId, $this->selectedRole === 'admin' && $this->fullAccess ? null : $permissions);
        session()->flash('message', 'Access updated. New requests use these permissions immediately.');
    }
    public function render() { $this->guard(); return view('livewire.access-management', ['users' => DB::table('users')->whereIn('role', ['admin', 'employee', 'manager'])->whereNull('deleted_at')->select('user_id', 'full_name')->get(), 'modules' => AccessControl::MODULES]); }
}
