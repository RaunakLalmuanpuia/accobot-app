<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyVoucherInventoryEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_voucher_id', 'tally_stock_item_id',
        'stock_item_name', 'item_code', 'group_name', 'hsn_code', 'unit',
        'igst_rate', 'cess_rate', 'is_deemed_positive',
        'actual_qty', 'billed_qty', 'rate', 'discount_percent',
        'amount', 'tax_amount', 'mrp',
        'sales_ledger', 'godown_name', 'batch_name',
        'batch_allocations', 'accounting_allocations',
    ];

    protected $casts = [
        'igst_rate'              => 'decimal:2',
        'cess_rate'              => 'decimal:2',
        'is_deemed_positive'     => 'boolean',
        'actual_qty'             => 'decimal:4',
        'billed_qty'             => 'decimal:4',
        'rate'                   => 'decimal:2',
        'discount_percent'       => 'decimal:2',
        'amount'                 => 'decimal:2',
        'tax_amount'             => 'decimal:2',
        'mrp'                    => 'decimal:2',
        'batch_allocations'      => 'array',
        'accounting_allocations' => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(TallyVoucher::class, 'tally_voucher_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(TallyStockItem::class, 'tally_stock_item_id');
    }
}
