<?php

namespace App\Http\Controllers;

use App\Actions\Banking\ProcessStatementAction;
use App\Http\Requests\Banking\StatementUploadRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class StatementUploadController extends Controller
{
    public function __construct(private ProcessStatementAction $action) {}

    public function __invoke(StatementUploadRequest $request, Tenant $tenant): RedirectResponse
    {
        set_time_limit(300);
        ini_set('max_execution_time', '300');

        $result  = $this->action->execute(
            $request->file('statement'),
            $tenant,
            $request->input('bank_account_name', '')
        );
        $message = $this->buildMessage($result);

        if ($result['total'] > 0 && $result['imported'] === 0 && $result['duplicates'] === 0) {
            return back()->withErrors(['statement' => $message]);
        }

        return back()->with('success', $message);
    }

    private function buildMessage(array $result): string
    {
        return sprintf(
            'Statement processed: %d imported, %d duplicates skipped, %d failed out of %d total transactions.',
            $result['imported'],
            $result['duplicates'],
            $result['failed'],
            $result['total']
        );
    }
}
