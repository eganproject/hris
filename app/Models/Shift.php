<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'start_time', 'end_time', 'break_minutes', 'is_active'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
        ];
    }
}
