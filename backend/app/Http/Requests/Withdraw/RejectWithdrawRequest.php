<?php

namespace App\Http\Requests\Withdraw;

use Illuminate\Foundation\Http\FormRequest;

class RejectWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'ghi_chu_admin' => $this->ghi_chu_admin
                ? trim($this->ghi_chu_admin)
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'ghi_chu_admin' => [
                'required',
                'string',
                'min:5',
                'max:1000'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ghi_chu_admin.required' => 'Vui lòng nhập lý do từ chối',
            'ghi_chu_admin.min' => 'Lý do từ chối phải có ít nhất 5 ký tự',
            'ghi_chu_admin.max' => 'Lý do từ chối không được vượt quá 1000 ký tự',
        ];
    }
}