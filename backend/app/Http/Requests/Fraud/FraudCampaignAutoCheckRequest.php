<?php

namespace App\Http\Requests\Fraud;

use Illuminate\Foundation\Http\FormRequest;

class FraudCampaignAutoCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaign_ids' => 'nullable|array|min:1',
            'campaign_ids.*' => 'integer|exists:chien_dich_gay_quy,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
