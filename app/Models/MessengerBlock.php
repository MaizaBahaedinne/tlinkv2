<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessengerBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];
}
