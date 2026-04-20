<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiaChiRequest extends FormRequest
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
            'dia_chi' => [
                'required',
                'string',
                'min:10', // tối thiểu 10 ký tự
                'max:255',
                'regex:/^[\pL0-9\s,.-]+$/u' 
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'dia_chi.required' => 'Địa chỉ không được để trống.',
            'dia_chi.string' => 'Địa chỉ phải là một chuỗi.',
            'dia_chi.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'dia_chi.min' => 'Địa chỉ phải có ít nhất 10 ký tự.',
            'dia_chi.regex' => 'Địa chỉ chỉ được chứa chữ cái, số, dấu cách, dấu phẩy, dấu chấm và dấu gạch ngang.'
        ];
    }
}
