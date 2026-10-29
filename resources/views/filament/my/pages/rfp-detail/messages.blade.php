@php
    use Filament\Support\Icons\Heroicon;
@endphp

<section class="my-glass rounded-[20px] p-4 lg:p-6">
    <div class="flex items-center justify-between">
        <h2 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 lg:text-lg">
            <x-filament::icon :icon="Heroicon::OutlinedChatBubbleLeftRight" class="size-5 text-indigo-600" />
            Messages
        </h2>
        <span class="text-[11px] text-slate-400">{{ count($messages) }} messages</span>
    </div>

    <div class="mt-3 flex flex-col gap-2">
        @foreach ($messages as $message)
            <div @class([
                'max-w-[85%] rounded-2xl p-2.5',
                'ml-auto rounded-tr-sm bg-indigo-600 text-white' => $message['mine'],
                'rounded-tl-sm bg-white/80 text-slate-800' => ! $message['mine'],
            ])>
                <span @class(['mb-0.5 flex items-center justify-between gap-3 text-[10px] font-semibold', 'text-indigo-100' => $message['mine'], 'text-slate-500' => ! $message['mine']])>
                    <span>{{ $message['author'] }} · {{ $message['role'] }}</span>
                    <span class="font-normal">{{ $message['at'] }}</span>
                </span>
                <p class="text-xs leading-snug">{{ $message['body'] }}</p>
            </div>
        @endforeach
    </div>

    <button type="button" x-on:click="$dispatch('open-comments-inbox')" class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl bg-white/60 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-white">
        Open conversation
        <x-filament::icon :icon="Heroicon::ArrowRight" class="size-3.5" />
    </button>
</section>
