<?php

namespace App\Http\Controllers\Api;

use App\Agents\AccountingAgent;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Tools\CreateInvoiceTool;
use App\Tools\GetInvoiceTool;
use App\Tools\ListInvoicesTool;
use App\Tools\ManageClientTool;
use App\Tools\ManageNarrationHeadTool;
use App\Tools\ManageProductTool;
use App\Tools\NarrateTransactionTool;
use App\Tools\UpdateInvoiceTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantChatController extends Controller
{
    /**
     * POST /api/mobile/tenants/{tenant}/chat
     *
     * Send a message to the accounting assistant.
     * Returns an SSE stream — listen for `data:` events.
     *
     * Request body:
     *   message  string  required  — the user's message (max 2000 chars)
     *   history  array   optional  — prior turns: [{role: "user"|"assistant", content: "..."}]
     *
     * SSE events emitted:
     *   data: {"type":"reply","reply":"...","clients":[...],"products":[...],...,"success":true}
     *   data: [DONE]
     *
     * On error:
     *   data: {"type":"error","reply":"...","success":false}
     *   data: [DONE]
     */
    public function chat(Request $request, Tenant $tenant): StreamedResponse
    {
        set_time_limit(120);

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'array',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $message = $request->input('message');
        $history = $request->input('history', []);

        return response()->stream(function () use ($message, $history, $tenant) {
            // Disable output buffering so events are flushed immediately
            while (ob_get_level()) {
                ob_end_flush();
            }

            try {
                app()->singleton(ManageClientTool::class);
                app()->singleton(ManageProductTool::class);
                app()->singleton(ListInvoicesTool::class);
                app()->singleton(CreateInvoiceTool::class);
                app()->singleton(UpdateInvoiceTool::class);
                app()->singleton(GetInvoiceTool::class);
                app()->singleton(ManageNarrationHeadTool::class);
                app()->singleton(NarrateTransactionTool::class);

                $prompt   = $this->buildPrompt($message, $history);
                $agent    = AccountingAgent::make();
                $response = $agent->prompt($prompt, provider: 'openai');

                $payload = [
                    'type'            => 'reply',
                    'reply'           => (string) $response,
                    'clients'         => app(ManageClientTool::class)->getLastResults(),
                    'products'        => app(ManageProductTool::class)->getLastResults(),
                    'invoices'        => app(ListInvoicesTool::class)->getLastResults(),
                    'invoice'         => app(CreateInvoiceTool::class)->getLastInvoice()
                                     ?? app(UpdateInvoiceTool::class)->getLastInvoice()
                                     ?? app(GetInvoiceTool::class)->getLastInvoice(),
                    'created_client'  => app(ManageClientTool::class)->getLastClient(),
                    'created_product' => app(ManageProductTool::class)->getLastProduct(),
                    'success'         => true,
                ];

            } catch (\Exception $e) {
                Log::error('TenantChatController SSE error', [
                    'tenant' => $tenant->id,
                    'error'  => $e->getMessage(),
                ]);

                $payload = [
                    'type'    => 'error',
                    'reply'   => 'Sorry, I encountered an error: ' . $e->getMessage(),
                    'success' => false,
                ];
            }

            echo 'data: ' . json_encode($payload) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function buildPrompt(string $message, array $history): string
    {
        if (empty($history)) {
            return $message;
        }

        $context = collect($history)->map(function (array $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            return "{$role}: {$msg['content']}";
        })->implode("\n\n");

        return "{$context}\n\nUser: {$message}";
    }
}
