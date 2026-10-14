@livewire(\App\Livewire\CommentsChat::class, ['record' => $record], key('comments-chat-'.($record ? $record->getMorphClass().'-'.$record->getKey() : 'inbox')))
