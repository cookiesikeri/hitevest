<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\UsesUuid;

// use App\Traits\UsesUuid;


class Admin extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, UsesUuid;

    protected $guarded = [];

    protected $guard = 'admin';

    protected $hidden = ['password', 'remember_token',];

     /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAuthPassword()
    {
     return $this->password;
    }

    public function isAdmin(){
        return $this->role == 'admin';
    }

    public function role() {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
}

