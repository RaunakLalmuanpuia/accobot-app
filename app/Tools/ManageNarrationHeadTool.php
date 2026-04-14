<?php

namespace App\Tools;

use App\Models\NarrationHead;
use App\Models\NarrationSubHead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * ManageNarrationHeadTool — Create or update narration heads and sub-heads.
 *
 * Actions:
 *   create_head      — create a new narration head
 *   update_head      — update an existing head (pass head_id)
 *   create_sub_head  — add a sub-head under an existing head (pass head_id)
 *   update_sub_head  — update an existing sub-head (pass sub_head_id)
 */
class ManageNarrationHeadTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Create or update narration heads and sub-heads used to categorize bank transactions. '
            . 'Use action=create_head or update_head for heads, create_sub_head or update_sub_head for sub-heads. '
            . 'Sub-heads are optional and belong to a head.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('What to do: "create_head", "update_head", "delete_head", "create_sub_head", "update_sub_head", or "delete_sub_head".')
                ->required(),
            'head_id' => $schema->integer()
                ->description('ID of the narration head. Required for update_head and create_sub_head. Pass null otherwise.')
                ->nullable()
                ->required(),
            'sub_head_id' => $schema->integer()
                ->description('ID of the sub-head. Required for update_sub_head. Pass null otherwise.')
                ->nullable()
                ->required(),
            'name' => $schema->string()
                ->description('Name of the head or sub-head. Required when creating.')
                ->nullable()
                ->required(),
            'type' => $schema->string()
                ->description('Transaction type for a head: "credit", "debit", or "both". Pass null to leave unchanged.')
                ->nullable()
                ->required(),
            'description' => $schema->string()
                ->description('Optional description. Pass null to leave unchanged.')
                ->nullable()
                ->required(),
            'ledger_code' => $schema->string()
                ->description('Ledger code for sub-heads (e.g. "6001"). Not required on create — pass null to skip or leave unchanged.')
                ->nullable()
                ->required(),
            'ledger_name' => $schema->string()
                ->description('Ledger name for sub-heads. Not required on create — pass null to skip or leave unchanged.')
                ->nullable()
                ->required(),
            'requires_party' => $schema->boolean()
                ->description('Whether the sub-head requires a party/vendor name. Not required on create — pass null to default to false.')
                ->nullable()
                ->required(),
            'is_active' => $schema->boolean()
                ->description('Set false to deactivate. Pass null to leave unchanged.')
                ->nullable()
                ->required(),
        ];
    }

    private function tenantId(): ?string
    {
        return request()->route('tenant')?->id;
    }

    public function handle(Request $request): Stringable|string
    {
        $action      = trim((string) ($request['action'] ?? ''));
        $headId      = $request['head_id'] ?? null;
        $subHeadId   = $request['sub_head_id'] ?? null;
        $name        = $this->nullableString($request['name'] ?? null);
        $type        = $this->nullableString($request['type'] ?? null);
        $description = $this->nullableString($request['description'] ?? null);
        $ledgerCode  = $this->nullableString($request['ledger_code'] ?? null);
        $ledgerName  = $this->nullableString($request['ledger_name'] ?? null);
        $requiresParty = $request['requires_party'] ?? null;
        $isActive      = $request['is_active'] ?? null;

        Log::info('ManageNarrationHeadTool', ['action' => $action]);

        try {
            return match ($action) {
                'create_head'     => $this->createHead($name, $type, $description),
                'update_head'     => $this->updateHead($headId, $name, $type, $description, $isActive),
                'delete_head'     => $this->deleteHead($headId),
                'create_sub_head' => $this->createSubHead($headId, $name, $description, $ledgerCode, $ledgerName, $requiresParty),
                'update_sub_head' => $this->updateSubHead($subHeadId, $name, $description, $ledgerCode, $ledgerName, $requiresParty, $isActive),
                'delete_sub_head' => $this->deleteSubHead($subHeadId),
                default           => "Unknown action \"{$action}\". Use create_head, update_head, delete_head, create_sub_head, update_sub_head, or delete_sub_head.",
            };
        } catch (\Exception $e) {
            Log::error('ManageNarrationHeadTool error', ['error' => $e->getMessage()]);
            return "Error: {$e->getMessage()}";
        }
    }

    private function createHead(?string $name, ?string $type, ?string $description): string
    {
        if (! $name) {
            return 'Head name is required.';
        }

        $validTypes = ['credit', 'debit', 'both'];
        $type       = in_array($type, $validTypes, true) ? $type : 'both';
        $tid  = $this->tenantId();
        $slug = $this->uniqueHeadSlug(Str::slug($name), $tid);

        $head = NarrationHead::create([
            'tenant_id'   => $tid,
            'name'        => $name,
            'slug'        => $slug,
            'type'        => $type,
            'description' => $description,
            'sort_order'  => NarrationHead::max('sort_order') + 1,
            'is_active'   => true,
        ]);

        return "Narration head **{$head->name}** created (type: {$head->type}, head_id: {$head->id}).";
    }

    private function updateHead(mixed $headId, ?string $name, ?string $type, ?string $description, mixed $isActive): string
    {
        if (! $headId) {
            return 'head_id is required for update_head.';
        }

        $head = NarrationHead::where('tenant_id', $this->tenantId())->find((int) $headId);
        if (! $head) {
            return 'Narration head not found.';
        }

        $changes = [];
        if ($name)        $changes['name'] = $name;
        if ($type && in_array($type, ['credit', 'debit', 'both'], true)) $changes['type'] = $type;
        if ($description !== null) $changes['description'] = $description;
        if ($isActive !== null && $isActive !== 'null') $changes['is_active'] = (bool) $isActive;

        if (empty($changes)) {
            return 'No changes provided.';
        }

        $head->update($changes);

        return "Narration head **{$head->name}** updated. Changed: " . implode(', ', array_keys($changes)) . '.';
    }

    private function createSubHead(mixed $headId, ?string $name, ?string $description, ?string $ledgerCode, ?string $ledgerName, mixed $requiresParty): string
    {
        if (! $headId) {
            return 'head_id is required for create_sub_head.';
        }

        if (! $name) {
            return 'Sub-head name is required.';
        }

        $head = NarrationHead::where('tenant_id', $this->tenantId())->find((int) $headId);
        if (! $head) {
            return 'Narration head not found.';
        }

        $slug = $this->uniqueSubHeadSlug((int) $headId, Str::slug($name));

        $sub = NarrationSubHead::create([
            'narration_head_id' => $head->id,
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $description ?? null,
            'ledger_code'       => $ledgerCode ?? null,
            'ledger_name'       => $ledgerName ?? null,
            'requires_party'    => false,
            'sort_order'        => NarrationSubHead::where('narration_head_id', $head->id)->max('sort_order') + 1,
            'is_active'         => true,
        ]);

        return "Sub-head **{$sub->name}** added under **{$head->name}** (sub_head_id: {$sub->id}).";
    }

    private function updateSubHead(mixed $subHeadId, ?string $name, ?string $description, ?string $ledgerCode, ?string $ledgerName, mixed $requiresParty, mixed $isActive): string
    {
        if (! $subHeadId) {
            return 'sub_head_id is required for update_sub_head.';
        }

        $sub = NarrationSubHead::with('narrationHead')->find((int) $subHeadId);
        if (! $sub || $sub->narrationHead->tenant_id !== $this->tenantId()) {
            return 'Narration sub-head not found.';
        }

        $changes = [];
        if ($name)              $changes['name']         = $name;
        if ($description !== null) $changes['description'] = $description;
        if ($ledgerCode !== null)  $changes['ledger_code'] = $ledgerCode;
        if ($ledgerName !== null)  $changes['ledger_name'] = $ledgerName;
        if ($requiresParty !== null && $requiresParty !== 'null') $changes['requires_party'] = (bool) $requiresParty;
        if ($isActive !== null && $isActive !== 'null')           $changes['is_active']      = (bool) $isActive;

        if (empty($changes)) {
            return 'No changes provided.';
        }

        $sub->update($changes);

        return "Sub-head **{$sub->name}** under **{$sub->narrationHead->name}** updated. Changed: " . implode(', ', array_keys($changes)) . '.';
    }

    private function deleteHead(mixed $headId): string
    {
        if (! $headId) {
            return 'head_id is required for delete_head.';
        }

        $head = NarrationHead::with('subHeads')->where('tenant_id', $this->tenantId())->find((int) $headId);
        if (! $head) {
            return 'Narration head not found.';
        }

        $name         = $head->name;
        $subHeadCount = $head->subHeads->count();

        $head->subHeads()->delete();
        $head->delete();

        $detail = $subHeadCount > 0 ? " (and {$subHeadCount} sub-head(s))" : '';
        return "Narration head **{$name}**{$detail} deleted.";
    }

    private function deleteSubHead(mixed $subHeadId): string
    {
        if (! $subHeadId) {
            return 'sub_head_id is required for delete_sub_head.';
        }

        $sub = NarrationSubHead::with('narrationHead')->find((int) $subHeadId);
        if (! $sub || $sub->narrationHead->tenant_id !== $this->tenantId()) {
            return 'Narration sub-head not found.';
        }

        $name     = $sub->name;
        $headName = $sub->narrationHead->name;
        $sub->delete();

        return "Sub-head **{$name}** deleted from **{$headName}**.";
    }

    private function uniqueHeadSlug(string $base, ?string $tenantId): string
    {
        $slug = $base;
        $i    = 2;
        while (NarrationHead::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function uniqueSubHeadSlug(int $headId, string $base): string
    {
        $slug = $base;
        $i    = 2;
        while (NarrationSubHead::where('narration_head_id', $headId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }
}
