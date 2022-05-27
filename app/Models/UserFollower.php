<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFollower extends Model
{
    use HasFactory;

    protected $table = "user_followers";

    protected $guarded = [];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }
}