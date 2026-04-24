<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'giao_dich_quy_id' => 'required|exists:giao_dich_quy,id',

            'mo_ta' => [
                'nullable',
                'string',
                'max:1000'
            ],

            'chi_tiet' => 'required|array|min:1',
            'chi_tiet.*.ten_hoat_dong' => 'required|string|max:255',
            'chi_tiet.*.so_tien' => 'required|numeric|min:1000',
        ];
    }

    public function messages()
    {
        return [
            'giao_dich_quy_id.required' => 'Vui lòng chọn giao dịch rút tiền',
            'giao_dich_quy_id.exists' => 'Giao dịch rút tiền không tồn tại hoặc không hợp lệ',
            
            'chi_tiet.required' => 'Vui lòng nhập ít nhất 1 khoản chi',
            'chi_tiet.array' => 'Dữ liệu chi tiết không hợp lệ',
            'chi_tiet.min' => 'Phải có ít nhất 1 khoản chi',

            'chi_tiet.*.ten_hoat_dong.required' => 'Vui lòng nhập tên khoản chi',
            'chi_tiet.*.ten_hoat_dong.max' => 'Tên khoản chi không quá 255 ký tự',

            'chi_tiet.*.so_tien.required' => 'Vui lòng nhập số tiền',
            'chi_tiet.*.so_tien.numeric' => 'Số tiền phải là số',
            'chi_tiet.*.so_tien.min' => 'Số tiền phải lớn hơn hoặc bằng 1.000',

            'mo_ta.max' => 'Ghi chú không quá 1000 ký tự',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'ten_hoat_dong' => trim($this->ten_hoat_dong),
            'mo_ta' => $this->mo_ta ? trim($this->mo_ta) : null,
        ]);
    }
}