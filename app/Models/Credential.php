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
        'sharepoint_client_id',
        'sharepoint_client_secret',
        'sharepoint_tenant_id',
        'dynamics_url',
        'dynamics_client_id',
        'dynamics_client_secret',
    ];


    public function user() : BelongsTo {

        return $this->BelongsTo(User::class);
    }
}
