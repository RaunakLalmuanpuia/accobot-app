<script setup>
defineProps({
    show:        { type: Boolean, default: false },
    voucherType: { type: String, default: 'Sales' }, // Sales | Purchase | CreditNote | DebitNote | Receipt | Payment | Contra | Journal
})
defineEmits(['close'])

const GUIDES = {
    Sales: {
        title: 'Sales Voucher Guide',
        color: 'emerald',
        steps: [
            {
                label: 'Choose Mode',
                icon: '①',
                body: 'Pick the mode that matches your transaction:',
                table: [
                    ['Item Invoice',       'Selling goods — stock items with GST invoice'],
                    ['Accounting Invoice', 'Selling services — no stock items, just income ledger'],
                    ['As Voucher',         'Sale without a formal GST invoice number'],
                ],
            },
            {
                label: 'Party A/c Name',
                icon: '②',
                body: 'Select the customer ledger from Sundry Debtors group. Buyer name, address, and GSTIN auto-fill from the ledger master.',
            },
            {
                label: 'Add Items (Item Invoice)',
                icon: '③',
                body: 'Fill Name of Item, Qty, and Rate. Amount auto-calculates. Expand ▼ each row to set HSN Code and IGST Rate % if needed.',
            },
            {
                label: 'Sales Ledger',
                icon: '④',
                body: 'Select the income account in the violet row below the items grid (e.g. "Sales Account"). It applies to all items at once and auto-fills the Accounting Allocations.',
            },
            {
                label: 'Ledger Allocations',
                icon: '⑤',
                body: 'Add accounting legs. Click "Suggest Tax Lines" first if IGST rates are set, then add the customer line manually:',
                ledger: [
                    { name: 'Customer ledger',   side: 'Dr', party: true,  note: 'Receivable — full invoice amount' },
                    { name: 'IGST Output ledger', side: 'Cr', party: false, note: 'Auto-suggested if IGST rate is set' },
                ],
            },
            {
                label: 'Grand Total',
                icon: '⑥',
                body: 'Auto-calculated from Items sub-total + Ledger totals. Click ↺ to reset if you have manually overridden it.',
            },
        ],
        mistakes: [
            ['Skipped Ledger Allocations', 'ledgerentries is empty — Tally rejects the voucher', 'Always add the customer ledger line (Dr + Party)'],
            ['No Sales Ledger on item',    'AccountingAllocations is empty',                    'Use the violet Sales Ledger row below the items grid'],
            ['Wrong Dr/Cr on party',       'Tally posts to the wrong side',                     'Customer = Dr for Sales'],
            ['Party checkbox not set',     'Bill-wise tracking breaks',                         'Check ✓ Party on the customer ledger line'],
        ],
    },

    Purchase: {
        title: 'Purchase Voucher Guide',
        color: 'blue',
        steps: [
            {
                label: 'Choose Mode',
                icon: '①',
                body: 'Pick the mode that matches your transaction:',
                table: [
                    ['Item Invoice',       'Receiving goods from supplier with tax invoice'],
                    ['Accounting Invoice', 'Purchasing services — no stock items'],
                    ['As Voucher',         'Purchase without a formal invoice number'],
                ],
            },
            {
                label: 'Party A/c Name',
                icon: '②',
                body: 'Select the supplier ledger from Sundry Creditors group. Enter Supplier\'s Inv. No. and Date in the header — these become the Reference fields in Tally.',
            },
            {
                label: 'Add Items (Item Invoice)',
                icon: '③',
                body: 'Fill Name of Item, Qty, and Rate. Amount auto-calculates. Expand ▼ to set HSN Code and IGST Rate % if needed.',
            },
            {
                label: 'Purchase Ledger',
                icon: '④',
                body: 'Select the purchase/expense account in the violet row below the items grid (e.g. "Purchase Accounts"). It applies to all items at once.',
            },
            {
                label: 'Ledger Allocations',
                icon: '⑤',
                body: 'Add accounting legs. Supplier goes Cr (liability increases). Tax input ledgers go Dr:',
                ledger: [
                    { name: 'Supplier ledger',         side: 'Cr', party: true,  note: 'Payable — full invoice amount' },
                    { name: 'IGST Input ledger',        side: 'Dr', party: false, note: 'Auto-suggested if IGST rate is set' },
                ],
            },
            {
                label: 'Grand Total',
                icon: '⑥',
                body: 'Auto-calculated. Click ↺ to reset if overridden.',
            },
        ],
        mistakes: [
            ['Skipped Ledger Allocations', 'ledgerentries is empty — Tally rejects the voucher',  'Always add the supplier ledger line (Cr + Party)'],
            ['No Purchase Ledger on item',  'AccountingAllocations is empty',                      'Use the violet Purchase Ledger row below the items grid'],
            ['Supplier set to Dr instead of Cr', 'Tally posts to wrong side',                     'Supplier = Cr for Purchase'],
            ['Party checkbox not set',      'Bill-wise tracking breaks',                           'Check ✓ Party on the supplier ledger line'],
        ],
    },

    Receipt: {
        title: 'Receipt Voucher Guide',
        color: 'violet',
        steps: [
            {
                label: 'Account (Bank / Cash)',
                icon: '①',
                body: 'Select the bank or cash account into which money is received. Amount syncs automatically from Particulars — click ↺ Sync if needed.',
            },
            {
                label: 'Particulars',
                icon: '②',
                body: 'Add one row per customer. Select the customer ledger and enter the amount received.',
            },
            {
                label: 'Bill References',
                icon: '③',
                body: 'Use "Agst Ref" to knock off a specific invoice, or "New Ref" for a fresh reference. Enter the invoice number in Reference field.',
            },
        ],
        mistakes: [
            ['No bill reference', 'Invoice stays open in outstanding', 'Add Agst Ref with the invoice number'],
            ['Wrong amount in Account', 'Voucher does not balance', 'Click ↺ Sync to match Particulars total'],
        ],
    },

    Payment: {
        title: 'Payment Voucher Guide',
        color: 'orange',
        steps: [
            {
                label: 'Account (Bank / Cash)',
                icon: '①',
                body: 'Select the bank or cash account from which money goes out.',
            },
            {
                label: 'Particulars',
                icon: '②',
                body: 'Add one row per payee — creditor or expense ledger. Enter the amount paid.',
            },
            {
                label: 'Bill References',
                icon: '③',
                body: 'Use "Agst Ref" to knock off a specific purchase bill.',
            },
        ],
        mistakes: [
            ['No bill reference', 'Bill stays open in outstanding', 'Add Agst Ref with the purchase bill number'],
        ],
    },

    Contra: {
        title: 'Contra Voucher Guide',
        color: 'gray',
        steps: [
            {
                label: 'Account (Deposit Into)',
                icon: '①',
                body: 'The account receiving money. Example: depositing cash to bank → select the Bank account here.',
            },
            {
                label: 'Particulars (Withdraw From)',
                icon: '②',
                body: 'The account sending money. Example: Cash account. Enter the amount here.',
            },
        ],
        mistakes: [
            ['Accounts reversed', 'Balance goes the wrong way', 'Account = destination (Dr), Particulars = source (Cr)'],
        ],
    },

    Journal: {
        title: 'Journal Voucher Guide',
        color: 'pink',
        steps: [
            {
                label: 'Add Ledger Lines',
                icon: '①',
                body: 'Click "+ Add Line" for each leg of the entry. Enter amount in the Dr column for debits, Cr column for credits.',
            },
            {
                label: 'Balance Check',
                icon: '②',
                body: 'The balance indicator at the bottom shows ✓ Balanced when Dr total = Cr total. Tally will reject an unbalanced journal.',
            },
            {
                label: 'Party Checkbox',
                icon: '③',
                body: 'Mark ✓ Party on any ledger that is a customer or supplier (for bill-wise tracking).',
            },
        ],
        mistakes: [
            ['Unbalanced entry', 'Tally rejects the voucher', 'Dr total must equal Cr total'],
        ],
    },

    CreditNote: {
        title: 'Credit Note Guide',
        color: 'yellow',
        steps: [
            {
                label: 'Original Invoice',
                icon: '①',
                body: 'Enter the original invoice number and date in the header (Original Inv No / Date). This links the credit note to the original sale in Tally.',
            },
            {
                label: 'Items & Ledgers',
                icon: '②',
                body: 'Fill items and ledger allocations exactly like a Sales voucher. Tally applies the reversal automatically.',
            },
        ],
        mistakes: [
            ['No original invoice reference', 'Credit note is unlinked in Tally', 'Always fill Original Inv No and Date'],
        ],
    },

    DebitNote: {
        title: 'Debit Note Guide',
        color: 'red',
        steps: [
            {
                label: 'Original Bill',
                icon: '①',
                body: 'Enter the original purchase bill number and date in the header (Original Bill No / Date).',
            },
            {
                label: 'Items & Ledgers',
                icon: '②',
                body: 'Fill items and ledger allocations exactly like a Purchase voucher. Tally applies the reversal automatically.',
            },
        ],
        mistakes: [
            ['No original bill reference', 'Debit note is unlinked in Tally', 'Always fill Original Bill No and Date'],
        ],
    },
}

const COLOR_MAP = {
    emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700', badge: 'bg-emerald-100 text-emerald-700', dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    blue:    { bg: 'bg-blue-50',    border: 'border-blue-200',    text: 'text-blue-700',    badge: 'bg-blue-100 text-blue-700',    dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    violet:  { bg: 'bg-violet-50',  border: 'border-violet-200',  text: 'text-violet-700',  badge: 'bg-violet-100 text-violet-700', dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    orange:  { bg: 'bg-orange-50',  border: 'border-orange-200',  text: 'text-orange-700',  badge: 'bg-orange-100 text-orange-700', dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    gray:    { bg: 'bg-gray-50',    border: 'border-gray-200',    text: 'text-gray-700',    badge: 'bg-gray-100 text-gray-700',    dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    pink:    { bg: 'bg-pink-50',    border: 'border-pink-200',    text: 'text-pink-700',    badge: 'bg-pink-100 text-pink-700',    dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    yellow:  { bg: 'bg-yellow-50',  border: 'border-yellow-200',  text: 'text-yellow-700',  badge: 'bg-yellow-100 text-yellow-700', dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
    red:     { bg: 'bg-red-50',     border: 'border-red-200',     text: 'text-red-700',     badge: 'bg-red-100 text-red-700',      dr: 'bg-blue-100 text-blue-700', cr: 'bg-orange-100 text-orange-700' },
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex justify-end">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/30" @click="$emit('close')" />

                <!-- Panel -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div v-if="show"
                         class="relative z-10 w-full max-w-md bg-white shadow-2xl flex flex-col h-full overflow-hidden">

                        <!-- Header -->
                        <div :class="['flex items-center justify-between px-5 py-4 border-b', COLOR_MAP[GUIDES[voucherType]?.color ?? 'gray'].border, COLOR_MAP[GUIDES[voucherType]?.color ?? 'gray'].bg]">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">📋</span>
                                <h2 :class="['text-base font-semibold', COLOR_MAP[GUIDES[voucherType]?.color ?? 'gray'].text]">
                                    {{ GUIDES[voucherType]?.title ?? voucherType + ' Guide' }}
                                </h2>
                            </div>
                            <button type="button" @click="$emit('close')"
                                    class="text-gray-400 hover:text-gray-600 text-xl leading-none px-1">✕</button>
                        </div>

                        <!-- Body -->
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                            <!-- Steps -->
                            <div v-for="(step, si) in GUIDES[voucherType]?.steps ?? []" :key="si"
                                 class="border border-gray-100 rounded-xl overflow-hidden">
                                <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                                    <span :class="['text-base font-bold', COLOR_MAP[GUIDES[voucherType]?.color ?? 'gray'].text]">{{ step.icon }}</span>
                                    <span class="text-sm font-semibold text-gray-700">{{ step.label }}</span>
                                </div>
                                <div class="px-4 py-3 space-y-3">
                                    <p class="text-sm text-gray-600 leading-relaxed">{{ step.body }}</p>

                                    <!-- Mode table -->
                                    <table v-if="step.table" class="w-full text-xs border-collapse">
                                        <tr v-for="row in step.table" :key="row[0]"
                                            class="border-b border-gray-100 last:border-0">
                                            <td class="py-1.5 pr-3 font-medium text-gray-700 whitespace-nowrap">{{ row[0] }}</td>
                                            <td class="py-1.5 text-gray-500">{{ row[1] }}</td>
                                        </tr>
                                    </table>

                                    <!-- Ledger example -->
                                    <div v-if="step.ledger" class="space-y-1.5">
                                        <div v-for="le in step.ledger" :key="le.name"
                                             class="flex items-center gap-2 text-xs">
                                            <span class="flex-1 font-medium text-gray-700 truncate">{{ le.name }}</span>
                                            <span :class="['px-2 py-0.5 rounded font-semibold', le.side === 'Dr' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700']">
                                                {{ le.side }}
                                            </span>
                                            <span v-if="le.party"
                                                  class="px-2 py-0.5 rounded bg-violet-100 text-violet-700 font-semibold">Party</span>
                                            <span v-else class="px-2 py-0.5 rounded bg-gray-100 text-gray-400 text-xs">—</span>
                                            <span class="text-gray-400 max-w-[120px] truncate">{{ le.note }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dr / Cr quick reference -->
                            <div class="border border-gray-100 rounded-xl overflow-hidden">
                                <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Dr / Cr Reference</span>
                                </div>
                                <div class="px-4 py-3 flex gap-4 text-xs">
                                    <div class="flex-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded font-semibold mb-1.5">Dr — Debit</span>
                                        <ul class="space-y-0.5 text-gray-500">
                                            <li>Asset increases</li>
                                            <li>Liability decreases</li>
                                            <li>Expense recorded</li>
                                        </ul>
                                    </div>
                                    <div class="flex-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-orange-100 text-orange-700 rounded font-semibold mb-1.5">Cr — Credit</span>
                                        <ul class="space-y-0.5 text-gray-500">
                                            <li>Asset decreases</li>
                                            <li>Liability increases</li>
                                            <li>Income recorded</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Common mistakes -->
                            <div v-if="GUIDES[voucherType]?.mistakes?.length" class="border border-red-100 rounded-xl overflow-hidden">
                                <div class="px-4 py-2.5 bg-red-50 border-b border-red-100">
                                    <span class="text-xs font-semibold text-red-600 uppercase tracking-wide">Common Mistakes</span>
                                </div>
                                <div class="divide-y divide-gray-50">
                                    <div v-for="m in GUIDES[voucherType].mistakes" :key="m[0]"
                                         class="px-4 py-2.5 space-y-0.5">
                                        <p class="text-xs font-semibold text-gray-700">{{ m[0] }}</p>
                                        <p class="text-xs text-red-500">{{ m[1] }}</p>
                                        <p class="text-xs text-emerald-600">Fix: {{ m[2] }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 text-xs text-gray-400 text-center">
                            Tally Prime voucher entry guide · Accobot
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
