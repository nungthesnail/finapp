<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportChat extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];
}

