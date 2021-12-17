<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UsesUuid;

class Permission extends Model
{
    use HasFactory, UsesUuid;


    protected $guarded = [];

    public function role() {
        return $this->belongsTo(Role::class);
    }
}
