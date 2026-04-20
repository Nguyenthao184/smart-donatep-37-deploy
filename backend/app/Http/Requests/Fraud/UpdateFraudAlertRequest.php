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
            'trang_thai' => 'required|string|in:CHO_XU_LY,DA_KIEM_TRA,CANH_BAO_SAI',
        ];
    }
}
