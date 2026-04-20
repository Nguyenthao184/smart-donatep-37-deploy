<?php

namespace App\Http\Requests\Fraud;

use Illuminate\Foundation\Http\FormRequest;

class FraudAutoCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'nullable|array|min:1',
            'user_ids.*' => 'integer|exists:nguoi_dung,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}

