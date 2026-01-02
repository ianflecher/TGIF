<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new #[Layout('components.layouts.applicant')] class extends Component
{
    use WithFileUploads;

    public $application;
    public $documents = [];
    
    #[Validate(['documents.*' => 'file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png'])] // 5MB max
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
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        }
    }
    
    public function uploadDocuments()
    {
        $this->validate([
            'newDocuments.*' => 'file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png'
        ]);
        
        if (!$this->application) {
            $this->addError('newDocuments', 'No application found. Please apply for a position first.');
            return;
        }
        
        foreach ($this->newDocuments as $document) {
            $path = $document->store('application_documents/' . $this->application->application_id, 'public');
            
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
        }
        
        $this->newDocuments = [];
        $this->mount(); // Refresh documents list
        session()->flash('success', count($this->newDocuments) . ' document(s) uploaded successfully!');
    }
    
    public function deleteDocument($documentId)
    {
        $document = DB::table('application_documents')->where('id', $documentId)->first();
        
        if ($document && $document->user_id == Auth::id()) {
            Storage::disk('public')->delete($document->filepath);
            DB::table('application_documents')->where('id', $documentId)->delete();
            
            $this->mount(); // Refresh documents list
            session()->flash('success', 'Document deleted successfully!');
        }
    }
}
?>

<div>
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
                        <p class="text-lg font-medium text-career-700">{{ $application->years_experience }}</p>
                    </div>
                </div>
                
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
                            
                            <!-- Step 3: Shortlisted Status -->
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
                            
                            <!-- Step 4: Hired Status -->
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
                            Upload additional documents like certificates, references, or portfolio (PDF, DOC, JPG, PNG)
                        </p>
                    </div>
                    
                    <!-- File Input -->
                    <div>
                        <input type="file" wire:model="newDocuments" multiple 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-career-500 focus:border-transparent"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        @error('newDocuments.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-500 mt-2">Maximum file size: 5MB per file</p>
                    </div>
                    
                    <!-- Upload Button -->
                    <div class="text-center">
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 bg-career-600 text-white rounded-lg hover:bg-career-700 focus:outline-none focus:ring-2 focus:ring-career-500 focus:ring-offset-2 transition-colors"
                                wire:loading.attr="disabled">
                            <i class="fas fa-upload mr-2"></i>
                            <span wire:loading.remove>Upload Documents</span>
                            <span wire:loading>Uploading...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Uploaded Documents -->
            @if(count($documents) > 0)
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-4">Uploaded Documents</h3>
                <div class="space-y-3">
                    @foreach($documents as $document)
                    <div class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-career-100 rounded-lg flex items-center justify-center">
                                @if(str_contains($document->filetype, 'pdf'))
                                    <i class="fas fa-file-pdf text-career-600"></i>
                                @elseif(str_contains($document->filetype, 'word') || str_contains($document->filetype, 'doc'))
                                    <i class="fas fa-file-word text-blue-600"></i>
                                @elseif(str_contains($document->filetype, 'image'))
                                    <i class="fas fa-file-image text-green-600"></i>
                                @else
                                    <i class="fas fa-file text-gray-600"></i>
                                @endif
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800">{{ $document->filename }}</h4>
                                <p class="text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($document->uploaded_at)->format('M d, Y') }} • 
                                    {{ round($document->filesize / 1024) }} KB
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="{{ Storage::url($document->filepath) }}" 
                               target="_blank"
                               class="px-3 py-1 text-sm bg-career-100 text-career-700 rounded hover:bg-career-200 transition-colors">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <button wire:click="deleteDocument({{ $document->id }})"
                                    onclick="return confirm('Are you sure you want to delete this document?')"
                                    class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-600 mb-2">No Documents Uploaded</h3>
                <p class="text-gray-500">Upload additional documents to support your application.</p>
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
                    @endif
                </ul>
            </div>
        </div>
    </div>
    @endif
</div>