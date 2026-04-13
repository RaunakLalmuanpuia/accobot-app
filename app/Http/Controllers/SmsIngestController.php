<?php

namespace App\Http\Controllers;

use App\Actions\Banking\IngestSmsTransactionAction;
use App\Http\Requests\Banking\SmsIngestRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class SmsIngestController extends Controller
{
    public function __construct(private IngestSmsTransactionAction $action) {}

    public function __invoke(SmsIngestRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->action->execute(
            $request->raw_sms,
            $tenant,
            $request->input('bank_account_name', '')
        );

        return back()->with('success', 'SMS processed and transaction added for review.');
    }
}
