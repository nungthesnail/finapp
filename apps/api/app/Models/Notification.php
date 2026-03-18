<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'read_at',
        'meta',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'meta' => 'array',
    ];
}

