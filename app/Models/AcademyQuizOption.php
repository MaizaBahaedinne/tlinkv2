<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademyQuizOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['question_id', 'text', 'is_correct', 'sort_order'];

    protected $casts = ['is_correct' => 'boolean'];
}
