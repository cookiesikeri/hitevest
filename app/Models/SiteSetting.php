<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\UsesUuid;

class SiteSetting extends Model
{
    use HasFactory, UsesUuid;

    protected $guarded = [];

    protected $table = "site_settings";

}
