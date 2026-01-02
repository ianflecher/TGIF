<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;

class EmployeeController extends Controller
{
    public function checkClockStatus()
{
    $user = Auth::user();
    $today = now()->toDateString();
    
    $attendance = Attendance::where('user_id', $user->id)
        ->whereDate('created_at', $today)
        ->first();
    
    $clockedIn = $attendance && $attendance->clock_in && !$attendance->clock_out;
    
    return response()->json([
        'success' => true,
        'clockedIn' => $clockedIn,
        'clockInTime' => $clockedIn ? $attendance->clock_in : null
    ]);
}

public function clockIn(Request $request)
{
    $user = Auth::user();
    $today = now()->toDateString();
    
    // Check if already clocked in today
    $existing = Attendance::where('user_id', $user->id)
        ->whereDate('created_at', $today)
        ->whereNull('clock_out')
        ->first();
    
    if ($existing) {
        return response()->json([
            'success' => false,
            'message' => 'Already clocked in today'
        ]);
    }
    
    $attendance = Attendance::create([
        'user_id' => $user->id,
        'clock_in' => now(),
        'date' => $today
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Clocked in successfully',
        'attendance' => $attendance
    ]);
}

public function clockOut(Request $request)
{
    $user = Auth::user();
    $today = now()->toDateString();
    
    $attendance = Attendance::where('user_id', $user->id)
        ->whereDate('created_at', $today)
        ->whereNull('clock_out')
        ->first();
    
    if (!$attendance) {
        return response()->json([
            'success' => false,
            'message' => 'No active clock in found'
        ]);
    }
    
    $attendance->update([
        'clock_out' => now(),
        'total_hours' => now()->diffInHours($attendance->clock_in)
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Clocked out successfully'
    ]);
}

public function syncClockState(Request $request)
{
    // Just acknowledge the sync - state is already saved in database
    return response()->json(['success' => true]);
}
}
