<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostLike extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['post_id', 'employee_id'];
}
