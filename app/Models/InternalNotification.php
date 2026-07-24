<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class InternalNotification extends DatabaseNotification
{
    protected $table = 'notifications';

    protected $guarded = ['*'];
}
