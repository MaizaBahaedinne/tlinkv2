<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'visibility',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];
}
