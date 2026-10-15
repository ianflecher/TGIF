<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicantDocuments
{
    public function document(int $id): object
    {
        $user = auth()->user();
        abort_unless($user && !$user->trashed(), 403);
        $document = DB::table('application_documents as d')->join('job_applications as a', 'd.application_id', '=', 'a.application_id')
            ->where('d.id', $id)->whereNull('d.deleted_at')->select('d.*', 'a.user_id as owner_id')->first();
        abort_unless($document && ((int) $document->user_id === (int) $user->getKey() && (int) $document->owner_id === (int) $user->getKey() || app(AccessControl::class)->allows($user, 'hr')), 404);
        abort_unless(in_array($document->disk, ['public', 'applicant_private'], true) && str_starts_with($document->filepath, 'application_documents/') && !str_contains($document->filepath, '..'), 404);
        return $document;
    }

    public function download(int $id)
    {
        $document = $this->document($id);
        abort_unless(Storage::disk($document->disk)->exists($document->filepath), 404);
        return Storage::disk($document->disk)->download($document->filepath, basename($document->filename), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function archive(int $id): void
    {
        $document = $this->document($id);
        abort_unless((int) $document->user_id === (int) auth()->id(), 403);
        DB::transaction(function () use ($document) {
            DB::table('application_documents')->where('id', $document->id)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['action' => 'archive', 'table_name' => 'application_documents', 'record_id' => $document->id, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
