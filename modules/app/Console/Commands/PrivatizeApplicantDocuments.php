<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrivatizeApplicantDocuments extends Command
{
    protected $signature = 'applicants:privatize {--execute : Copy, verify, repoint records, then remove public copies}';
    protected $description = 'Safely migrate existing applicant documents into private storage; dry run by default';

    public function handle(): int
    {
        $failed = false;
        foreach (DB::table('application_documents')->where('disk', 'public')->orderBy('id')->cursor() as $row) {
            $this->line('Document '.$row->id.($this->option('execute') ? ': migrating' : ': would migrate'));
            if (!$this->option('execute')) continue;
            try {
                if (!str_starts_with($row->filepath, 'application_documents/') || str_contains($row->filepath, '..')) throw new \RuntimeException('Invalid storage path.');
                $public = Storage::disk('public'); $private = Storage::disk('applicant_private');
                if (!$public->exists($row->filepath) && $row->sha256 && $private->exists($row->filepath) && hash_equals($row->sha256, hash('sha256', $private->get($row->filepath)))) {
                    DB::table('application_documents')->where('id', $row->id)->update(['disk' => 'applicant_private', 'updated_at' => now()]);
                    continue;
                }
                $content = $public->get($row->filepath);
                if ($content === null) throw new \RuntimeException('Public source file is missing.');
                if ($private->exists($row->filepath) && hash('sha256', $private->get($row->filepath)) !== hash('sha256', $content)) throw new \RuntimeException('Private destination differs; preserving both copies.');
                $private->put($row->filepath, $content);
                if (hash('sha256', $private->get($row->filepath)) !== hash('sha256', $content)) throw new \RuntimeException('Verification failed.');
                DB::table('application_documents')->where('id', $row->id)->update(['sha256' => hash('sha256', $content)]);
                // Remove public exposure before repointing. A retry can recover using the verified private copy.
                if (!$public->delete($row->filepath)) throw new \RuntimeException('Could not remove public copy.');
                DB::table('application_documents')->where('id', $row->id)->update(['disk' => 'applicant_private', 'updated_at' => now()]);
            } catch (\Throwable $error) { $this->error('Document '.$row->id.': '.$error->getMessage()); $failed = true; }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
