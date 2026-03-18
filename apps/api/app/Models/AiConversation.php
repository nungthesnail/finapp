<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'selected_model',
        'is_technical',
        'last_active_at',
    ];

    protected $casts = [
        'is_technical' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }
}

