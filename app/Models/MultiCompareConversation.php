<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MultiCompareConversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'selected_models' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MultiCompareMessage::class, 'conversation_id');
    }

    public function getLastUserMessageAttribute()
    {
        return $this->messages()
            ->where('role', 'user')
            ->latest()
            ->first();
    }

    public function getMessageCountAttribute()
    {
        return $this->messages()->count();
    }
}
