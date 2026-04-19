<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyVoucherLedgerEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_voucher_id', 'tally_ledger_id',
        'ledger_name', 'ledger_group',
        'ledger_amount', 'is_deemed_positive', 'is_party_ledger',
        'igst_rate', 'hsn_code', 'cess_rate',
        'bills_allocation',
    ];

    protected $casts = [
        'ledger_amount'      => 'decimal:2',
        'is_deemed_positive' => 'boolean',
        'is_party_ledger'    => 'boolean',
        'bills_allocation'   => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(TallyVoucher::class, 'tally_voucher_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(TallyLedger::class, 'tally_ledger_id');
    }
}
