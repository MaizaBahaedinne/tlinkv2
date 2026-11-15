<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebmailLabel extends Model
{
    public const COLORS = ['blue' => 'Bleu', 'green' => 'Vert', 'orange' => 'Orange', 'red' => 'Rouge', 'purple' => 'Violet', 'gray' => 'Gris'];

    protected $table = 'webmail_labels';

    protected $fillable = ['mailbox_id', 'name', 'color'];

    public function assignments(): HasMany
    {
        return $this->hasMany(WebmailMessageLabel::class, 'label_id');
    }
}
