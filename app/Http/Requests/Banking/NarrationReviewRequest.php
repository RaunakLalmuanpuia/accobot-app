<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class NarrationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // narration_head_id is only required for 'correct' — approve/reject don't need it
        $headRequired = $this->route('action') === 'correct' ? 'required' : 'nullable';

        return [
            'narration_head_id'     => [$headRequired, 'integer', 'exists:narration_heads,id'],
            'narration_sub_head_id' => ['nullable', 'integer', 'exists:narration_sub_heads,id'],
            'party_name'            => ['nullable', 'string', 'max:255'],
            'narration_note'        => ['nullable', 'string', 'max:500'],
            'save_as_rule'          => ['boolean'],

            // Reconciliation fields (all optional)
            'invoice_id'     => ['nullable', 'integer', 'exists:invoices,id'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'unreconcile'    => ['boolean'],
        ];
    }
}
