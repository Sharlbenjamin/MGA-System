<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftMail extends Model
{
    protected $fillable = ['mail_name', 'body_mail', 'status', 'type', 'new_status'];
}
