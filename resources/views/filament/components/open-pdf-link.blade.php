@php
    $attachments = $attachments ?? (isset($getState) ? $getState() : []) ?? [];
    $viewerUrl = $viewerUrl ?? null;

    // Fall back to the first stored file when no dedicated viewer URL is given.
    if ($viewerUrl === null && filled($attachments)) {
        $first = is_array($attachments) ? reset($attachments) : $attachments;
        $path = is_array($first) ? ($first['path'] ?? null) : $first;

        if (filled($path)) {
            $viewerUrl = Storage::disk('public')->url($path);
        }
    }
@endphp

{{-- Read-only "view the uploaded PDF" link, used beside a disabled file field. --}}
<div style="font-size:0.875rem;">
    @if (filled($attachments) && $viewerUrl)
        <a href="{{ $viewerUrl }}" target="_blank" rel="noopener"
            style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; font-weight:500; color:#fff; background-color:#2563eb; border-radius:6px;">
            View File Uploaded
        </a>
    @else
        <span style="color:#6b7280;">No file uploaded.</span>
    @endif
</div>
