<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\TallyCompany;
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

        $this->upsertCompany($conn, $request);

        TallyInboundLog::create([
            'tenant_id'           => $conn->tenant_id,
            'tally_connection_id' => $conn->id,
            'endpoint'            => $request->path(),
            'payload'             => $request->all(),
        ]);

        return $conn;
    }

    private function upsertCompany(TallyConnection $conn, Request $request): void
    {
        $first = collect($request->input('Data') ?? $request->input('data', []))->first();
        $guid  = data_get($first, 'CompanyGUID');

        if (empty($guid)) {
            return;
        }

        TallyCompany::updateOrCreate(
            ['tally_connection_id' => $conn->id, 'company_guid' => $guid],
            [
                'company_name'   => data_get($first, 'CompanyName'),
                'licence_type'   => data_get($first, 'LicenceType'),
                'licence_number' => data_get($first, 'LicenceNumber'),
            ]
        );
    }

    protected function logAudit(TallyConnection $conn, string $entity, array $counts = []): void
    {
        AuditEvent::log(
            "tally.inbound.{$entity}.synced",
            array_merge(['entity' => $entity], $counts),
            null,
            (string) $conn->tenant_id,
            'integration',
        );
    }

    protected function logResponse($log): array
    {
        return [
            'status'  => $log->status,
            'created' => $log->records_created,
            'updated' => $log->records_updated,
            'deleted' => $log->records_deleted,
            'skipped' => $log->records_skipped,
            'failed'  => $log->records_failed,
        ];
    }

    protected function resolveConnectionByCompanyId(Request $request, string $companyId): TallyConnection
    {
        $conn = $this->resolveConnection($request);

        abort_if($conn->company_id !== $companyId, 403, 'Company ID mismatch.');

        return $conn;
    }
}
