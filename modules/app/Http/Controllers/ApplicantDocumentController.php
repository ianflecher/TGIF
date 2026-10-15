<?php
namespace App\Http\Controllers;

class ApplicantDocumentController extends Controller
{
    public function __invoke(int $document)
    {
        return app(\App\Services\ApplicantDocuments::class)->download($document);
    }
}
