<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id', 'id')->orderBy('id', 'asc');
        // foreignKey, localKey
    }

    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }
}
