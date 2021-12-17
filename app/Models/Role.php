<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UsesUuid;



class Role extends Model
{
    use HasFactory, UsesUuid;


    protected $guarded = [];


    public function admins() {
        return $this->hasMany(Admin::class, 'role_id');
    }

    public function permission() {
        return $this->hasOne(Permission::class, 'id');
    }


}
