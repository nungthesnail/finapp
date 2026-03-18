<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

