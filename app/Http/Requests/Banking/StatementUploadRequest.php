<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class StatementUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'statement'         => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,csv,xlsx,xls,jpg,jpeg,png',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'statement.mimes' => 'Please upload a PDF, CSV, Excel (.xlsx/.xls), or Image (.jpg/.png) bank statement.',
            'statement.max'   => 'The statement file must not be larger than 20 MB.',
        ];
    }
}
