<?php

namespace App\Http\Requests\Withdraw;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'ma_giao_dich_ngan_hang' => $this->ma_giao_dich_ngan_hang
                ? trim($this->ma_giao_dich_ngan_hang)
                : null,

            'ghi_chu_admin' => $this->ghi_chu_admin
                ? trim($this->ghi_chu_admin)
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'ma_giao_dich_ngan_hang' => [
                'required',
                'string',
                'max:255'
            ],

            'ngay_giao_dich' => [
                'required',
                'date'
            ],

            'ghi_chu_admin' => [
                'nullable',
                'string',
                'max:1000'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ma_giao_dich_ngan_hang.required' => 'Vui lòng nhập mã giao dịch ngân hàng',
            'ma_giao_dich_ngan_hang.max' => 'Mã giao dịch quá dài',

            'ngay_giao_dich.required' => 'Vui lòng chọn ngày giao dịch',
            'ngay_giao_dich.date' => 'Ngày giao dịch không hợp lệ',

            'ghi_chu_admin.max' => 'Ghi chú không được vượt quá 1000 ký tự',
        ];
    }
}