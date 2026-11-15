<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebmailContact extends Model
{
    protected $table = 'webmail_contacts';

    protected $fillable = ['mailbox_id', 'name', 'email'];
}
