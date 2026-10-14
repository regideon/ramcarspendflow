<?php

namespace App\Filament\Resources\Rfps\Actions;

use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Filament\Support\CommentMentions;
use App\Models\Rfp\Rfp;
use App\Services\Comments\PostComment;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Adds a comment to an RFP (premium initiative #1). Mentioned users get an in-app notification.
 * A comment never changes the status; Return and Cancel stay separate actions.
 */
class AddCommentAction
{
    public static function make(): Action
    {
        return Action::make('addComment')
            ->label('Add comment')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalHeading(fn (Rfp $record): string => "Comment on {$record->rfp_number}")
            ->modalSubmitActionLabel('Post comment')
            ->schema(fn (Rfp $record): array => [
                RichEditor::make('body')
                    ->label('Comment')
                    ->helperText('Type @ to mention someone. They will be notified.')
                    ->required()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                    ->mentions([CommentMentions::provider($record)]),
                FileUpload::make('attachments')
                    ->helperText('PDF or image, up to 10 MB each.')
                    ->multiple()
                    ->disk('local')
                    ->directory(PostComment::attachmentDirectory($record))
                    ->visibility('private')
                    ->acceptedFileTypes(RfpForm::DOCUMENT_TYPES)
                    ->maxSize(10_240)
                    ->maxFiles(5),
            ])
            ->action(function (Rfp $record, array $data): void {
                $mentioned = app(PostComment::class)->handle($record, auth()->user(), $data);

                $record->load('comments.user');

                Notification::make()
                    ->title('Comment posted')
                    ->body($mentioned->isNotEmpty() ? 'Notified: '.$mentioned->pluck('name')->join(', ') : null)
                    ->success()
                    ->send();
            });
    }
}
