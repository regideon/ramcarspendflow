<?php

namespace App\Http\Controllers\Rfp;

use App\Http\Controllers\Controller;
use App\Services\Rfp\ReceiptScanner;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shows a supporting document inline in the Read receipt review, next to what the AI read.
 * Links come from ReceiptScanner::previewFiles(): signed, short-lived, and the file is encrypted
 * in the link so it can't be swapped for another path.
 */
class ReceiptPreviewController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        try {
            [$disk, $path] = json_decode(Crypt::decryptString((string) $request->query('file')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(404);
        }

        // Only new uploads and saved supporting documents
        $allowed = Str::startsWith($path, [FileUploadConfiguration::path('', false).'/', 'rfp-attachments/']);

        abort_unless($allowed && ! str_contains($path, '..') && Storage::disk($disk)->exists($path), 404);

        $mime = (string) Storage::disk($disk)->mimeType($path);

        abort_unless(in_array($mime, ReceiptScanner::READABLE_TYPES, true), 404);

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
