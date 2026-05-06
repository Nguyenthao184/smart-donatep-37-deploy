<?php

namespace App\Http\Requests\Fraud;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFraudAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trang_thai' => 'required|string|in:CHO_XU_LY,DA_XU_LY',
            'decision' => 'nullable|string|in:CHO_XU_LY,VI_PHAM,KHONG_VI_PHAM',
            'admin_note' => 'nullable|string|max:1000',
        ];
    }
}
