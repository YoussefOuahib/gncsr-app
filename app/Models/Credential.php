<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'tak_url',
        'tak_login',
        'tak_password',
        'sharepoint_url',
        'sharepoint_login',
        'sharepoint_password',
        'dynamics_url',
        'dynamics_login',
        'dynamics_password',
    ];

    protected $hidden = [
        'tak_password',
        'sharepoint_password',
        'dynamics_password',
    ];
    public function user() : BelongsTo {

        return $this->BelongsTo(User::class);
    }
}
