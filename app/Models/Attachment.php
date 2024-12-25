<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $hidden = [
        'record_id', 'type', 'created_at', 'updated_at'
    ];

    protected $fillable = [
        'attachment', 'attachment_type', 'attachment_thumb', 'record_id', 'type'
    ];
}
