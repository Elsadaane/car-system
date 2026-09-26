<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar', 'name_en', 'alert_type', 'trigger_condition', 'configuration', 'status',
        'notification_day', 'user_id'
    ];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
