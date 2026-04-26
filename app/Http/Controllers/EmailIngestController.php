<?php

namespace App\Http\Controllers;

use App\Actions\Banking\IngestEmailTransactionAction;
use App\Http\Requests\Banking\EmailIngestRequest;
use App\Models\AuditEvent;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class EmailIngestController extends Controller
{
    public function __construct(private IngestEmailTransactionAction $action) {}

    public function __invoke(EmailIngestRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->action->execute(
            $request->buildRawEmail(),
            $tenant,
            $request->input('bank_account_name', '')
        );

        AuditEvent::log('banking.email.ingested', [
            'bank_account_name' => $request->input('bank_account_name'),
        ]);

        return back()->with('success', 'Email processed and transaction added for review.');
    }
}
