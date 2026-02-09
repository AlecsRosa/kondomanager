<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    protected $fillable = [
        'recipient', 
        'subject', 
        'mailer', 
        'status', 
        'error_message', 
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
