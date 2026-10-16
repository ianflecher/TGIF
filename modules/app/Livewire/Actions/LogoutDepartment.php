<?php
namespace App\Livewire\Actions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class LogoutDepartment
{
    public function __invoke(Request $request)
    {
        $department = $request->input('department');
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route(is_string($department) && array_key_exists($department, config('portals')) ? 'portals.'.$department.'.login' : 'portals.index');
    }
}
