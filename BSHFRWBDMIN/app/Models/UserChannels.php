<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChannels extends Model
{
    protected $connection = 'channel';
    protected $table= 'user_channel';
    public $timestamps=false;    
    
}