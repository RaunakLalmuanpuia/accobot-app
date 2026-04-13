<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class SmsIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_sms'          => ['required', 'string', 'min:10', 'max:1000'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
