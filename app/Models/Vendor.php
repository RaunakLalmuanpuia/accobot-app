<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'name', 'email', 'phone'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
