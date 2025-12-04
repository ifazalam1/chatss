<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandTheme extends Model
{
    use HasFactory;

     protected $fillable = [
        'user_id', 'company_name', 'tag', 'address', 
        'website', 'phone', 'personal_name', 'visible_fields'
    ];

    protected $casts = [
        'visible_fields' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
