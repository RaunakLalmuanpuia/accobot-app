<?php

namespace App\Http\Controllers;

use App\Agents\AccountingAgent;
use App\Models\AiUsageLog;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Tools\CreateInvoiceTool;
use App\Tools\GetInvoiceTool;
use App\Tools\ListInvoicesTool;
use App\Tools\ManageClientTool;
use App\Tools\ManageNarrationHeadTool;
use App\Tools\ManageProductTool;
use App\Tools\NarrateTransactionTool;
use App\Tools\UpdateInvoiceTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('Chat/Index');
    }

    public function chat(Request $request, Tenant $tenant): JsonResponse
    {
        set_time_limit(120);

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'array',
        ]);

        $userMessage = $request->input('message');
        $history     = $request->input('history', []);

        try {
            // Bind tools as singletons for this request so the agent and controller
            // share the same instances — enables getLastResults() / getLastInvoice().
            app()->singleton(ManageClientTool::class);
            app()->singleton(ManageProductTool::class);
            app()->singleton(ListInvoicesTool::class);
            app()->singleton(CreateInvoiceTool::class);
            app()->singleton(UpdateInvoiceTool::class);
            app()->singleton(GetInvoiceTool::class);
            app()->singleton(ManageNarrationHeadTool::class);
            app()->singleton(NarrateTransactionTool::class);

            $prompt = $this->buildPrompt($userMessage, $history);

            $agent    = AccountingAgent::make();
            $response = $agent->prompt($prompt, provider: 'openai');

            AiUsageLog::fromAgentResponse(
                response:  $response,
                agent:     'AccountingAgent',
                callType:  'chat',
                tenantId:  $tenant->id,
                userId:    auth()->id(),
                toolSteps: $response->steps->count(),
                context:   ['history_turns' => count($history)],
            );

            $clientTool  = app(ManageClientTool::class);
            $productTool = app(ManageProductTool::class);

            $clients        = $clientTool->getLastResults();
            $products       = $productTool->getLastResults();
            $invoices       = app(ListInvoicesTool::class)->getLastResults();
            $invoice        = app(CreateInvoiceTool::class)->getLastInvoice()
                           ?? app(UpdateInvoiceTool::class)->getLastInvoice()
                           ?? app(GetInvoiceTool::class)->getLastInvoice();
            $createdClient  = $clientTool->getLastClient();
            $createdProduct = $productTool->getLastProduct();

            AuditEvent::log('chat.message', [
                'channel'       => 'web',
                'message_length' => strlen($userMessage),
                'history_turns'  => count($history),
                'tool_steps'     => $response->steps->count(),
                'success'        => true,
            ]);

            return response()->json([
                'reply'           => (string) $response,
                'clients'         => $clients,
                'products'        => $products,
                'invoices'        => $invoices,
                'invoice'         => $invoice,
                'created_client'  => $createdClient,
                'created_product' => $createdProduct,
                'success'         => true,
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController error', ['error' => $e->getMessage()]);

            AuditEvent::log('chat.error', [
                'channel'        => 'web',
                'message_length' => strlen($userMessage),
                'history_turns'  => count($history),
                'error'          => $e->getMessage(),
                'success'        => false,
            ]);

            AiUsageLog::fromError(
                e:        $e,
                agent:    'AccountingAgent',
                callType: 'chat',
                tenantId: $tenant->id,
                userId:   auth()->id(),
                context:  ['history_turns' => count($history)],
            );

            return response()->json([
                'reply'   => "Sorry, I encountered an error: {$e->getMessage()}",
                'success' => false,
            ], 500);
        }
    }

    private function buildPrompt(string $userMessage, array $history): string
    {
        if (empty($history)) {
            return $userMessage;
        }

        $context = collect($history)->map(function ($msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            return "{$role}: {$msg['content']}";
        })->implode("\n\n");

        return "{$context}\n\nUser: {$userMessage}";
    }
}
