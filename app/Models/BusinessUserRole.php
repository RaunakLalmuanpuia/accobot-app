<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class BusinessUserRole extends Model
{
    //
    protected $table = 'business_user_roles';

    protected $fillable = ['user_id', 'business_id', 'role_id'];

    public function role() : BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
