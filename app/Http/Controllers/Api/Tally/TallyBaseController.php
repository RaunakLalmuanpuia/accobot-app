<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Models\TallyConnection;
use App\Models\TallyInboundLog;
use Illuminate\Http\Request;

class TallyBaseController extends Controller
{
    protected function resolveConnection(Request $request): TallyConnection
    {
        $token = $request->bearerToken();

        abort_if(empty($token), 401, 'Missing token.');

        $conn = TallyConnection::withoutGlobalScope('tenant')
            ->where('inbound_token', $token)
            ->where('is_active', true)
            ->first();

        abort_if(is_null($conn), 401, 'Invalid or inactive token.');

        $conn->update(['inbound_token_last_used_at' => now()]);

        return $conn;
    }

    protected function resolveAndLog(Request $request): TallyConnection
    {
        $conn = $this->resolveConnection($request);

        TallyInboundLog::create([
            'tenant_id'           => $conn->tenant_id,
            'tally_connection_id' => $conn->id,
            'endpoint'            => $request->path(),
            'payload'             => $request->all(),
        ]);

        return $conn;
    }

    protected function resolveConnectionByCompanyId(Request $request, string $companyId): TallyConnection
    {
        $conn = $this->resolveConnection($request);

        abort_if($conn->company_id !== $companyId, 403, 'Company ID mismatch.');

        return $conn;
    }
}
