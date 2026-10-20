@php
    $attachments = $getState() ?? [];
@endphp

@if (filled($attachments))
    <div style="display:flex; flex-direction:column; gap:4px;">
        @foreach ($attachments as $attachment)
            @php
                $path = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;
                $name = is_array($attachment) ? ($attachment['name'] ?? null) : null;
            @endphp

            @if (filled($path))
                <a href="{{ Storage::disk('public')->url($path) }}" target="_blank"
                    title="{{ $name ?? basename($path) }}"
                    style="color:var(--primary-600); font-size:0.875rem; display:inline-flex; align-items:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">
                    {{ $name ?? basename($path) }}
                </a>
            @endif
        @endforeach
    </div>
@else
    <span style="color:#6b7280; font-size:0.875rem;">No attachments</span>
@endif
