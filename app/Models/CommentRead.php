<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The last comment a user has read on a record. Comments after it count as unread.
 */
#[Fillable(['user_id', 'last_read_comment_id'])]
class CommentRead extends Model
{
    //
}
