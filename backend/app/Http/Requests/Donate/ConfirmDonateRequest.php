<?php

namespace App\Http\Requests\Donate;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDonateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'so_tien' => 'required|numeric|min:10000',
        ];
    }

    public function messages()
    {
        return [
            'so_tien.required' => 'Số tiền không được để trống',
            'so_tien.numeric' => 'Số tiền phải là số',
            'so_tien.min' => 'Số tiền tối thiểu là 10,000 VND',
        ];
    }
}
