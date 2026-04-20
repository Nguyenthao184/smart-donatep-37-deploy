<?php

namespace App\Http\Requests\Donate;

use Illuminate\Foundation\Http\FormRequest;

class DonateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chien_dich_gay_quy_id' => 'required|exists:chien_dich_gay_quy,id',
            'so_tien' => 'required|numeric|min:1000|max:50000000',
        ];
    }

    public function messages()
    {
        return [
            'chien_dich_gay_quy_id.required' => 'Chiến dịch không được để trống',
            'chien_dich_gay_quy_id.exists' => 'Chiến dịch không tồn tại',
            'so_tien.required' => 'Số tiền không được để trống',
            'so_tien.numeric' => 'Số tiền phải là số',
            'so_tien.min' => 'Số tiền tối thiểu là 1.000 VND',
            'so_tien.max' => 'Số tiền tối đa là 50.000.000 VND',
        ];
    }
}
