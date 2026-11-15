<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebmailMessageLabel extends Model
{
    public $timestamps = false;

    protected $table = 'webmail_message_labels';

    protected $fillable = ['label_id', 'folder', 'uid'];
}
