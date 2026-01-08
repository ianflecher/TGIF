<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.applicant')] class extends Component
{
    use WithFileUploads;

    public $application;
    public $documents = [];
    public $interviewDetails = null;
    
    #[Validate([
        'newDocuments' => 'max:5', // Maximum 5 files per upload
        'newDocuments.*' => 'file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png'
    ])]
    public $newDocuments = [];
    
    public function mount()
    {
        $user = Auth::user();
        
        // Get the latest application for the user
        $this->application = DB::table('job_applications')
            ->where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->first();
            
        // Get uploaded documents if any
        if ($this->application) {
            $this->documents = DB::table('application_documents')
                ->where('application_id', $this->application->application_id)
                ->where('user_id', $user->user_id) // Security: ensure user owns the documents
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
            
            // Get interview details if exists
            if ($this->application->interview_date) {
                $this->interviewDetails = DB::table('job_applications as ja')
                    ->select(
                        'ja.*',
                        'interviewer.full_name as interviewer_name',
                        'interviewer.email as interviewer_email',
                        DB::raw('DATE(ja.interview_date) as interview_date_only'),
                        DB::raw('TIME(ja.interview_date) as interview_time_only')
                    )
                    ->leftJoin('users as interviewer', 'ja.interviewer_id', '=', 'interviewer.user_id')
                    ->where('ja.application_id', $this->application->application_id)
                    ->first();
            }
        }
    }
    
    public function uploadDocuments()
    {
        try {
            // Validate the uploaded files
            $this->validate([
                'newDocuments' => 'max:5',
                'newDocuments.*' => 'file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png'
            ]);
            
            if (!$this->application) {
                throw new \Exception('No application found. Please apply for a position first.');
            }
            
            if (empty($this->newDocuments)) {
                throw new \Exception('Please select at least one file to upload.');
            }
            
            DB::beginTransaction();
            
            $uploadedCount = 0;
            $failedUploads = [];
            
            foreach ($this->newDocuments as $document) {
                try {
                    // Store the file in public disk under application_documents/{application_id}/
                    $path = $document->store('application_documents/' . $this->application->application_id, 'public');
                    
                    // Insert document record into application_documents table
                    DB::table('application_documents')->insert([
                        'application_id' => $this->application->application_id,
                        'user_id' => Auth::id(),
                        'filename' => $document->getClientOriginalName(),
                        'filepath' => $path,
                        'filetype' => $document->getMimeType(),
                        'filesize' => $document->getSize(),
                        'uploaded_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $uploadedCount++;
                    
                } catch (\Exception $e) {
                    // Log the error for this specific file
                    $failedUploads[] = [
                        'filename' => $document->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            // Prepare success message
            $message = "{$uploadedCount} document(s) uploaded successfully!";
            
            if (!empty($failedUploads)) {
                $failedNames = array_column($failedUploads, 'filename');
                $message .= " Failed to upload: " . implode(', ', $failedNames);
            }
            
            session()->flash('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Upload failed: ' . $e->getMessage());
        }
        
        $this->newDocuments = [];
        $this->mount(); // Refresh documents list
    }
    
    public function deleteDocument($documentId)
    {
        try {
            $document = DB::table('application_documents')
                ->where('id', $documentId)
                ->where('user_id', Auth::id()) // Security: user can only delete their own documents
                ->first();
            
            if (!$document) {
                throw new \Exception('Document not found or you do not have permission to delete it.');
            }
            
            // Delete the physical file
            if (Storage::disk('public')->exists($document->filepath)) {
                Storage::disk('public')->delete($document->filepath);
            }
            
            // Delete the database record
            DB::table('application_documents')->where('id', $documentId)->delete();
            
            $this->mount(); // Refresh documents list
            session()->flash('success', 'Document deleted successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Format file size to human readable format
     */
    public function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get file icon based on file type
     */
    public function getFileIcon($filetype)
    {
        if (str_contains($filetype, 'pdf')) {
            return ['icon' => 'fas fa-file-pdf', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
        } elseif (str_contains($filetype, 'word') || str_contains($filetype, 'doc')) {
            return ['icon' => 'fas fa-file-word', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
        } elseif (str_contains($filetype, 'excel') || str_contains($filetype, 'sheet')) {
            return ['icon' => 'fas fa-file-excel', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } elseif (str_contains($filetype, 'image')) {
            return ['icon' => 'fas fa-file-image', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
        } else {
            return ['icon' => 'fas fa-file', 'color' => 'text-gray-600', 'bg' => 'bg-gray-100'];
        }
    }
}
?>

<div>
    <!-- Flash Messages -->
    @if (session()->has('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg" 
         x-data="{ show: true }" 
         x-show="show" 
         x-transition
         x-init="setTimeout(() => show = false, 5000)">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
            </div>
            <div class="ml-auto pl-3">
                <button @click="show = false" class="text-green-500 hover:text-green-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg" 
         x-data="{ show: true }" 
         x-show="show" 
         x-transition
         x-init="setTimeout(() => show = false, 5000)">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
            </div>
            <div class="ml-auto pl-3">
                <button @click="show = false" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Page Header -->
    <div class="page-header mb-8">
        <h1 class="text-3xl font-bold">Applicant Dashboard</h1>
        <p class="text-lg opacity-90">Track your application and manage your documents</p>
    </div>

    <!-- Application Status Card -->
    <div class="page-card mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Application Status</h2>
            @if($application)
                <span class="status-badge status-{{ strtolower($application->status) }}">
                    <i class="fas fa-circle text-xs"></i>
                    {{ ucfirst($application->status) }}
                </span>
            @endif
        </div>
        
        @if($application)
            <div class="space-y-6">
                <!-- Application Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-700 mb-2">Position Applied</h3>
                        <p class="text-lg font-medium text-career-700">{{ $application->position_applied }}</p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-gray-700 mb-2">Experience</h3>
                        <p class="text-lg font-medium text-career-700">{{ $application->years_experience }} years</p>
                    </div>
                </div>
                
                <!-- Notes Section -->
                @if($application->notes)
                <div class="mt-6">
                    <h3 class="font-semibold text-gray-700 mb-3">Application Notes</h3>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-sticky-note text-blue-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-800">{{ $application->notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Interview Details Section -->
                @if($interviewDetails)
                <div class="mt-6">
                    <h3 class="font-semibold text-gray-700 mb-4">Interview Details</h3>
                    <div class="bg-white border border-purple-200 rounded-lg overflow-hidden shadow-sm">
                        <!-- Header -->
                        <div class="bg-purple-50 px-6 py-4 border-b border-purple-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-purple-600 text-xl mr-3"></i>
                                    <div>
                                        <h4 class="text-lg font-semibold text-purple-800">Your Interview</h4>
                                        <p class="text-sm text-purple-600">Please be prepared for your scheduled interview</p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                                    <i class="fas fa-clock mr-1"></i>
                                    @if($interviewDetails->interview_status)
                                        {{ ucfirst($interviewDetails->interview_status) }}
                                    @else
                                        Scheduled
                                    @endif
                                </span>
                            </div>
                        </div>
                        
                        <!-- Interview Details -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Date & Time -->
                                <div class="space-y-4">
                                    <div>
                                        <h5 class="text-sm font-medium text-gray-500 mb-1">Date & Time</h5>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-day text-career-600 mr-3"></i>
                                            <div>
                                                <p class="font-medium text-gray-900">
                                                    {{ \Carbon\Carbon::parse($interviewDetails->interview_date)->format('l, F j, Y') }}
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    {{ \Carbon\Carbon::parse($interviewDetails->interview_date)->format('g:i A') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Interview Type -->
                                    <div>
                                        <h5 class="text-sm font-medium text-gray-500 mb-1">Interview Type</h5>
                                        <div class="flex items-center">
                                            @php
                                                $interviewTypeIcons = [
                                                    'in_person' => 'fas fa-user',
                                                    'video' => 'fas fa-video',
                                                    'phone' => 'fas fa-phone',
                                                    'technical' => 'fas fa-code',
                                                    'hr' => 'fas fa-user-tie',
                                                ];
                                            @endphp
                                            <i class="{{ $interviewTypeIcons[$interviewDetails->interview_type] ?? 'fas fa-question-circle' }} text-career-600 mr-3"></i>
                                            <span class="font-medium text-gray-900">
                                                {{ ucfirst(str_replace('_', ' ', $interviewDetails->interview_type)) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Interviewer Details -->
                                <div class="space-y-4">
                                    <div>
                                        <h5 class="text-sm font-medium text-gray-500 mb-1">Interviewer</h5>
                                        <div class="flex items-center">
                                            <i class="fas fa-user-tie text-career-600 mr-3"></i>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $interviewDetails->interviewer_name ?? 'To be assigned' }}</p>
                                                @if($interviewDetails->interviewer_email)
                                                <p class="text-sm text-gray-600">{{ $interviewDetails->interviewer_email }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <!-- Interview Notes -->
                            @if($interviewDetails->interview_notes)
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h5 class="text-sm font-medium text-gray-500 mb-2">Interview Instructions & Notes</h5>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-gray-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-gray-700">{{ $interviewDetails->interview_notes }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Preparation Tips -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h5 class="text-sm font-medium text-gray-500 mb-2">Preparation Tips</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-green-50 p-4 rounded-lg">
                                        <div class="flex items-start">
                                            <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                            <div>
                                                <h6 class="font-medium text-green-800 mb-1">Be Prepared</h6>
                                                <p class="text-sm text-green-700">Review the job description and prepare questions</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <div class="flex items-start">
                                            <i class="fas fa-clock text-blue-500 mt-1 mr-3"></i>
                                            <div>
                                                <h6 class="font-medium text-blue-800 mb-1">Be On Time</h6>
                                                <p class="text-sm text-blue-700">Join 5-10 minutes early for virtual interviews</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Status Timeline -->
                <div class="mt-8">
                    <h3 class="font-semibold text-gray-700 mb-4">Application Timeline</h3>
                    <div class="relative">
                        <!-- Timeline line -->
                        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-career-200"></div>
                        
                        <!-- Timeline steps -->
                        <div class="space-y-8 relative">
                            <!-- Step 1: Applied -->
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-career-500 flex items-center justify-center">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Application Submitted</h4>
                                    <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($application->application_date)->format('F j, Y') }}</p>
                                    <p class="text-sm text-gray-600 mt-1">Your application has been received and is under review.</p>
                                </div>
                            </div>
                            
                            <!-- Step 2: Review Status -->
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full 
                                    {{ in_array($application->status, ['reviewed', 'shortlisted', 'hired']) ? 'bg-career-500' : 'bg-gray-300' }} 
                                    flex items-center justify-center">
                                    @if(in_array($application->status, ['reviewed', 'shortlisted', 'hired']))
                                        <i class="fas fa-check text-white text-sm"></i>
                                    @else
                                        <i class="fas fa-clock text-gray-500 text-sm"></i>
                                    @endif
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Under Review</h4>
                                    <p class="text-sm text-gray-600">HR Department is reviewing your application</p>
                                    @if($application->status === 'reviewed')
                                        <p class="text-sm text-career-600 mt-1 font-medium">
                                            <i class="fas fa-check-circle mr-1"></i> Your application has been reviewed
                                        </p>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Step 3: Interview Status (if scheduled) -->
                            @if($interviewDetails)
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center">
                                    <i class="fas fa-calendar-check text-white text-sm"></i>
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Interview Scheduled</h4>
                                    <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($interviewDetails->interview_date)->format('F j, Y \a\t g:i A') }}</p>
                                    <p class="text-sm text-purple-600 mt-1 font-medium">
                                        <i class="fas fa-video mr-1"></i> 
                                        @if($interviewDetails->interview_type === 'video')
                                            Virtual interview scheduled
                                        @elseif($interviewDetails->interview_type === 'phone')
                                            Phone interview scheduled
                                        @elseif($interviewDetails->interview_type === 'in_person')
                                            In-person interview scheduled
                                        @else
                                            Interview scheduled
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Step 4: Shortlisted Status -->
                            @if($application->status === 'shortlisted' || $application->status === 'hired')
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full 
                                    {{ $application->status === 'shortlisted' || $application->status === 'hired' ? 'bg-career-500' : 'bg-gray-300' }} 
                                    flex items-center justify-center">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Shortlisted</h4>
                                    <p class="text-sm text-gray-600">Congratulations! You have been shortlisted</p>
                                    <p class="text-sm text-career-600 mt-1 font-medium">
                                        <i class="fas fa-star mr-1"></i> You're among the selected candidates
                                    </p>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Step 5: Hired Status -->
                            @if($application->status === 'hired')
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-career-500 flex items-center justify-center">
                                    <i class="fas fa-trophy text-white text-sm"></i>
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Hired!</h4>
                                    <p class="text-sm text-gray-600">Welcome to the TGIF team!</p>
                                    <p class="text-sm text-career-600 mt-1 font-medium">
                                        <i class="fas fa-party-horn mr-1"></i> Congratulations on your new position!
                                    </p>
                                </div>
                            </div>
                            @endif
                            
                            @if($application->status === 'rejected')
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-500 flex items-center justify-center">
                                    <i class="fas fa-times text-white text-sm"></i>
                                </div>
                                <div class="ml-6">
                                    <h4 class="font-medium text-gray-800">Application Not Successful</h4>
                                    <p class="text-sm text-gray-600">We appreciate your interest in TGIF</p>
                                    <p class="text-sm text-red-600 mt-1 font-medium">
                                        <i class="fas fa-info-circle mr-1"></i> Please check other available positions
                                    </p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-8">
                <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 mb-2">No Application Found</h3>
                <p class="text-gray-500 mb-4">You haven't submitted any job applications yet.</p>
                <a href="{{ route('applicant.jobs') }}" class="inline-flex items-center px-4 py-2 bg-career-600 text-white rounded-lg hover:bg-career-700 transition-colors">
                    <i class="fas fa-search mr-2"></i> Browse Available Positions
                </a>
            </div>
        @endif
    </div>

    <!-- Document Upload Section -->
    @if($application && in_array($application->status, ['pending', 'reviewed', 'shortlisted']))
    <div class="page-card">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Upload Additional Documents</h2>
        
        <div class="space-y-6">
            <!-- Upload Form -->
            <div class="border-2 border-dashed border-career-300 rounded-xl p-8 bg-career-50">
                <form wire:submit="uploadDocuments" class="space-y-4">
                    <div class="text-center">
                        <i class="fas fa-cloud-upload-alt text-4xl text-career-500 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Upload Supporting Documents</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Upload additional documents like certificates, references, or portfolio (PDF, DOC, DOCX, JPG, PNG)
                            <br>Maximum 5 files per upload, 5MB per file
                        </p>
                    </div>
                    
                    <!-- File Input -->
                    <div>
                        <input type="file" 
                               wire:model="newDocuments" 
                               multiple 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-career-500 focus:border-transparent file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-career-50 file:text-career-700 hover:file:bg-career-100"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                               id="document-upload">
                        @error('newDocuments')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('newDocuments.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        
                        <!-- Selected files preview -->
                        @if($newDocuments)
                        <div class="mt-3 space-y-2">
                            <p class="text-sm font-medium text-gray-700">Selected files:</p>
                            <ul class="text-sm text-gray-600 space-y-1">
                                @foreach($newDocuments as $index => $document)
                                <li class="flex items-center">
                                    <i class="fas fa-file mr-2 text-gray-400"></i>
                                    <span class="truncate">{{ $document->getClientOriginalName() }}</span>
                                    <span class="ml-2 text-xs text-gray-500">
                                        ({{ $this->formatFileSize($document->getSize()) }})
                                    </span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                    
                    <!-- Upload Button -->
                    <div class="text-center">
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-career-600 text-white rounded-lg hover:bg-career-700 focus:outline-none focus:ring-2 focus:ring-career-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                wire:loading.attr="disabled"
                                {{ empty($newDocuments) ? 'disabled' : '' }}>
                            <i class="fas fa-upload mr-2"></i>
                            <span wire:loading.remove wire:target="uploadDocuments">Upload Documents</span>
                            <span wire:loading wire:target="uploadDocuments">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Uploading...
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Uploaded Documents -->
            @if(count($documents) > 0)
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-700">Uploaded Documents</h3>
                    <span class="text-sm text-gray-500">{{ count($documents) }} document(s)</span>
                </div>
                <div class="space-y-3">
                    @foreach($documents as $document)
                    @php $fileIcon = $this->getFileIcon($document->filetype); @endphp
                    <div class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 {{ $fileIcon['bg'] }} rounded-lg flex items-center justify-center">
                                <i class="{{ $fileIcon['icon'] }} {{ $fileIcon['color'] }} text-lg"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-medium text-gray-800 truncate">{{ $document->filename }}</h4>
                                <div class="flex items-center space-x-3 text-sm text-gray-500">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        {{ \Carbon\Carbon::parse($document->uploaded_at)->format('M d, Y') }}
                                    </span>
                                    <span>•</span>
                                    <span>
                                        <i class="fas fa-file mr-1"></i>
                                        {{ strtoupper(pathinfo($document->filename, PATHINFO_EXTENSION)) }}
                                    </span>
                                    <span>•</span>
                                    <span>
                                        <i class="fas fa-database mr-1"></i>
                                        {{ $this->formatFileSize($document->filesize) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="{{ Storage::url($document->filepath) }}" 
                               target="_blank"
                               class="inline-flex items-center px-3 py-2 text-sm bg-career-50 text-career-700 rounded-lg hover:bg-career-100 transition-colors"
                               title="View document">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="{{ Storage::url($document->filepath) }}" 
                               download="{{ $document->filename }}"
                               class="inline-flex items-center px-3 py-2 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors"
                               title="Download document">
                                <i class="fas fa-download mr-1"></i> Download
                            </a>
                            <button wire:click="deleteDocument({{ $document->id }})"
                                    onclick="return confirm('Are you sure you want to delete \"{{ $document->filename }}\"?')"
                                    class="inline-flex items-center px-3 py-2 text-sm bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors"
                                    title="Delete document">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-600 mb-2">No Documents Uploaded</h3>
                <p class="text-gray-500 mb-4">Upload additional documents to support your application.</p>
                <p class="text-sm text-gray-400">Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Important Notes -->
    @if($application && in_array($application->status, ['shortlisted', 'hired']))
    <div class="page-card bg-career-50 border-career-200">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-career-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-medium text-career-800">Next Steps</h3>
                <ul class="mt-2 space-y-2 text-career-700">
                    @if($application->status === 'shortlisted')
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-career-500"></i>
                            <span>You will be contacted by our HR team for an interview</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-career-500"></i>
                            <span>Please ensure all your documents are up to date</span>
                        </li>
                    @elseif($application->status === 'hired')
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-career-500"></i>
                            <span>Our HR team will contact you with onboarding details</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-career-500"></i>
                            <span>Please prepare the necessary documents for employment</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-career-500"></i>
                            <span>You Can now login to Employee Portal Using the same login Details</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Upload Restrictions Note -->
    @if($application && !in_array($application->status, ['pending', 'reviewed', 'shortlisted']))
    <div class="page-card bg-gray-50 border-gray-200">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-gray-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-800">Document Upload</h3>
                <p class="mt-1 text-gray-700">
                    @if($application->status === 'hired')
                        Document upload is no longer available as your application has been marked as hired.
                    @elseif($application->status === 'rejected')
                        Document upload is no longer available as your application has been rejected.
                    @else
                        Document upload is only available for applications with pending, reviewed, or shortlisted status.
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif
</div>