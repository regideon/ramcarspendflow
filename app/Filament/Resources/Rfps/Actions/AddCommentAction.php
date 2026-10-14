<?php

namespace App\Filament\Resources\Rfps\Actions;

use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

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
                    ->mentions([static::mentionProvider($record)]),
                FileUpload::make('attachments')
                    ->helperText('PDF or image, up to 10 MB each.')
                    ->multiple()
                    ->disk('local')
                    ->directory('rfp-comments')
                    ->visibility('private')
                    ->acceptedFileTypes(RfpForm::DOCUMENT_TYPES)
                    ->maxSize(10_240)
                    ->maxFiles(5),
            ])
            ->action(function (Rfp $record, array $data): void {
                $author = auth()->user();

                $comment = $record->comments()->create([
                    'user_id' => $author->id,
                    'body' => $data['body'],
                    'attachments' => $data['attachments'] ?: null,
                ]);

                $mentioned = $record->mentionableUsers()
                    ->whereKey($comment->mentionedUserIds())
                    ->whereKeyNot($author->id)
                    ->get();

                $mentioned->each(fn (User $user) => Notification::make()
                    ->title("{$author->name} mentioned you on {$record->rfp_number}")
                    ->body(Str::limit(strip_tags($comment->body), 120))
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->actions([
                        Action::make('view')->label('Open RFP')->url(static::urlFor($record, $user))->markAsRead(),
                    ])
                    ->sendToDatabase($user));

                $record->load('comments.user');

                Notification::make()
                    ->title('Comment posted')
                    ->body($mentioned->isNotEmpty() ? 'Notified: '.$mentioned->pluck('name')->join(', ') : null)
                    ->success()
                    ->send();
            });
    }

    /**
     * The @ picker: only people who can open this RFP. Also renders stored mentions as names.
     */
    public static function mentionProvider(?Rfp $rfp = null): MentionProvider
    {
        return MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => ($rfp?->mentionableUsers() ?? User::query()->whereRaw('0 = 1'))
                ->where('name', 'like', "%{$search}%")
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => User::query()->whereKey($ids)->pluck('name', 'id')->all());
    }

    /**
     * The requestor opens it under My Requests; AP and Admin under RFP Approvals.
     */
    protected static function urlFor(Rfp $record, User $user): string
    {
        return $user->id === $record->requestor_id || ! $user->hasAnyRole(RoleName::rfpApprovalsUsers())
            ? RfpResource::getUrl('view', ['record' => $record])
            : RfpApprovalResource::getUrl('view', ['record' => $record]);
    }
}
