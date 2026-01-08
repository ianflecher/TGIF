<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public string $leave_type = 'vacation';
    public string $start_date = '';
    public string $end_date = '';
    public int $total_days = 0;
    public string $reason = '';

    /**
     * Get logged-in employee_id (NOT user_id)
     */
    public function employeeId()
    {
        return DB::table('employees')
            ->where('user_id', Auth::id())
            ->value('employee_id');
    }

    /**
     * Leave history
     */
    public function getLeavesProperty()
    {
        return DB::table('leaves')
            ->where('employee_id', $this->employeeId())
            ->orderByDesc('created_at')
            ->get();
    }

    protected function rules()
    {
        return [
            'leave_type' => 'required',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|min:5',
        ];
    }

    public function updatedStartDate()
    {
        $this->calculateDays();
    }

    public function updatedEndDate()
    {
        $this->calculateDays();
    }

    private function calculateDays()
    {
        if ($this->start_date && $this->end_date) {
            $this->total_days =
                Carbon::parse($this->start_date)
                    ->diffInDays(Carbon::parse($this->end_date)) + 1;
        } else {
            $this->total_days = 0;
        }
    }

    public function submit()
    {
        $this->validate();

        $employeeId = $this->employeeId();

        if (!$employeeId) {
            abort(403, 'Employee record not found.');
        }

        DB::table('leaves')->insert([
            'employee_id' => $employeeId,
            'leave_type'  => $this->leave_type,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date,
            'total_days'  => $this->total_days,
            'reason'      => $this->reason,
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        session()->flash('success', 'Leave request submitted successfully.');

        $this->reset(['leave_type', 'start_date', 'end_date', 'total_days', 'reason']);
    }
};
?>

<div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4 md:p-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-4xl font-bold bg-gradient-to-r from-emerald-700 to-teal-600 bg-clip-text text-transparent">
                    Leave Management
                </h1>
                <p class="text-gray-600 mt-2">Submit and track your leave requests</p>
            </div>
            <div class="flex items-center space-x-2 text-emerald-700">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                <span class="text-lg font-semibold">Employee Portal</span>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LEAVE REQUEST FORM -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-2xl border border-emerald-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-500 p-8">
                        <h2 class="text-2xl font-bold text-white">New Leave Request</h2>
                        <p class="text-emerald-500 font-bold mt-1">Fill out the form below to submit your leave application</p>
                    </div>

                    @if (session()->has('success'))
                        <div class="mx-6 mt-6 p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 shadow-sm animate-pulse-once">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-emerald-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="font-semibold text-emerald-700">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="submit" class="p-8 space-y-8">
                        <!-- Leave Type -->
                        <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-100 rounded-2xl p-6 shadow-sm">
                            <label class="block mb-4">
                                <div class="flex items-center mb-2">
                                    <div class="w-2 h-6 bg-emerald-500 rounded-full mr-3"></div>
                                    <span class="text-lg font-bold text-gray-800">Leave Type</span>
                                </div>
                                <select wire:model="leave_type"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-emerald-200 bg-white
                                           focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition duration-200 cursor-pointer">
                                    <option value="vacation">🏖️ Vacation</option>
                                    <option value="sick">🤒 Sick Leave</option>
                                    <option value="emergency">🚨 Emergency</option>
                                    <option value="maternity">👶 Maternity</option>
                                    <option value="paternity">👨‍👦 Paternity</option>
                                    <option value="bereavement">😔 Bereavement</option>
                                    <option value="study">📚 Study</option>
                                    <option value="unpaid">💼 Unpaid</option>
                                    <option value="others">📝 Others</option>
                                </select>
                            </label>
                        </div>

                        <!-- Dates -->
                        <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-100 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center mb-4">
                                <div class="w-2 h-6 bg-emerald-500 rounded-full mr-3"></div>
                                <span class="text-lg font-bold text-gray-800">Leave Period</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                                    <input type="date" wire:model="start_date" onclick="this.showPicker()"
                                        class="w-full px-4 py-3 rounded-xl border-2 border-emerald-200 
                                               focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 cursor-pointer bg-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                                    <input type="date" wire:model="end_date" onclick="this.showPicker()"
                                        class="w-full px-4 py-3 rounded-xl border-2 border-emerald-200 
                                               focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 cursor-pointer bg-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Total Days</label>
                                    <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 border-2 border-emerald-200">
                                        <span class="text-2xl font-bold text-emerald-700">{{ $total_days }}</span>
                                        <span class="text-sm text-emerald-600 font-medium">days</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-100 rounded-2xl p-6 shadow-sm">
                            <label class="block">
                                <div class="flex items-center mb-4">
                                    <div class="w-2 h-6 bg-emerald-500 rounded-full mr-3"></div>
                                    <span class="text-lg font-bold text-gray-800">Reason for Leave</span>
                                </div>
                                <textarea wire:model="reason" rows="4" placeholder="Please provide details about your leave..."
                                    class="w-full px-4 py-3 rounded-xl border-2 border-emerald-200 bg-white
                                           focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition duration-200 resize-none"></textarea>
                                <div class="flex justify-between mt-2 text-sm text-gray-500">
                                    <span>Minimum 5 characters required</span>
                                    <span>{{ strlen($reason) }}/255</span>
                                </div>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-6 border-t border-emerald-100">
                            <button type="submit"
    class="w-full py-4 px-6 rounded-xl bg-emerald-600
           text-white font-bold text-lg shadow-md hover:shadow-lg
           hover:bg-emerald-700
           flex items-center justify-center gap-2
           transition-all duration-200">
    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
    </svg>
    <span>Submit Leave Request</span>
</button>

                        </div>
                    </form>
                </div>
            </div>

            <!-- QUICK STATS & GUIDELINES -->
            <div class="space-y-6">
                <!-- Stats -->
                <div class="bg-white rounded-3xl shadow-xl border border-emerald-100 p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Leave Overview</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center mr-3">
                                    <span class="text-emerald-600 font-bold">{{ $this->leaves->where('status', 'pending')->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Pending</span>
                            </div>
                            <div class="w-2 h-6 bg-yellow-500 rounded-full"></div>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center mr-3">
                                    <span class="text-emerald-600 font-bold">{{ $this->leaves->where('status', 'approved')->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Approved</span>
                            </div>
                            <div class="w-2 h-6 bg-green-500 rounded-full"></div>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center mr-3">
                                    <span class="text-emerald-600 font-bold">{{ $this->leaves->count() }}</span>
                                </div>
                                <span class="font-medium text-gray-700">Total Requests</span>
                            </div>
                            <div class="w-2 h-6 bg-emerald-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-3xl border border-emerald-200 p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        Quick Guidelines
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Submit requests at least 3 days in advance</span>
                        </li>
                        <li class="flex items-start">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Attach medical certificate for sick leave</span>
                        </li>
                        <li class="flex items-start">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mt-2 mr-3"></div>
                            <span class="text-sm text-gray-700">Check with your team before submission</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- LEAVE HISTORY TABLE -->
        <div class="bg-white rounded-3xl shadow-2xl border border-emerald-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-500 p-8">
                <h2 class="text-2xl font-bold text-white">Leave History</h2>
                <p class="text-emerald-500 mt-1">Track all your leave requests and their status</p>
            </div>

            <div class="p-6">
                @if($this->leaves->isNotEmpty())
                    <div class="overflow-x-auto rounded-xl border border-emerald-100">
                        <table class="min-w-full divide-y divide-emerald-100">
                            <thead class="bg-gradient-to-r from-emerald-50 to-teal-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Days</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-800 uppercase tracking-wider">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-emerald-50">
                                @foreach ($this->leaves as $leave)
                                    <tr class="hover:bg-emerald-50/50 transition duration-150">
                                        <td class="px-6 py-4 flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center mr-3">
                                                @switch($leave->leave_type)
                                                    @case('vacation') 🏖️ @break
                                                    @case('sick') 🤒 @break
                                                    @case('emergency') 🚨 @break
                                                    @case('maternity') 👶 @break
                                                    @case('paternity') 👨‍👦 @break
                                                    @case('bereavement') 😔 @break
                                                    @case('study') 📚 @break
                                                    @case('unpaid') 💼 @break
                                                    @default 📝
                                                @endswitch
                                            </div>
                                            <span class="font-medium text-gray-700">{{ ucfirst($leave->leave_type) }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $leave->start_date }} - {{ $leave->end_date }}</td>
                                        <td class="px-6 py-4 font-bold text-emerald-700">{{ $leave->total_days }}</td>
                                        <td class="px-6 py-4">
                                            @if($leave->status === 'approved')
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">Approved</span>
                                            @elseif($leave->status === 'pending')
                                                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-semibold">Pending</span>
                                            @else
                                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold">{{ ucfirst($leave->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $leave->reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">You have no leave history yet.</p>
                @endif
            </div>
        </div>

    </div>
</div>
