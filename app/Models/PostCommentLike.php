<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostCommentLike extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['post_comment_id', 'employee_id'];
}
