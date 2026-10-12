@php
    // Ported from the legacy forms/fields/label-value partial: a "Label: value"
    // pair used for read-only header fields inside repeaters.
    $state = $getState();
    $formattedState = '-';

    if ($state !== null && $state !== '') {
        if ($state instanceof \Carbon\Carbon) {
            $formattedState = $state->format('M d, Y');
        } elseif (is_string($state) && strtotime($state) !== false) {
            $formattedState = \Carbon\Carbon::parse($state)->format('M d, Y');
        } elseif (is_array($state)) {
            $formattedState = implode(', ', $state);
        } else {
            $formattedState = $state;
        }
    }
@endphp

<div class="flex flex-wrap items-center gap-2">
    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $getLabel() }}:</span>
    <span class="text-gray-500 dark:text-gray-400">{{ $formattedState }}</span>
</div>
