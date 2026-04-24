<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyVoucherEmployeeAllocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_voucher_id', 'tally_employee_id',
        'employee_name', 'employee_group', 'entries', 'net_payable',
    ];

    protected $casts = [
        'entries'     => 'array',
        'net_payable' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(TallyVoucher::class, 'tally_voucher_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TallyEmployee::class, 'tally_employee_id');
    }
}
