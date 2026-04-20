<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'ho_ten' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-zÀ-ỹ]+( [A-Za-zÀ-ỹ]+)*$/'
            ],
            'email' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
            ],
            'password' => 'required|min:6',
            'confirm_password' => [
                'required',
                'same:password'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'ho_ten.required' => 'Họ tên không được để trống.',
            'ho_ten.string' => 'Họ tên phải là chuỗi ký tự.',
            'ho_ten.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'ho_ten.regex' => 'Họ tên chỉ được chứa chữ cái và mỗi từ cách nhau đúng 1 dấu cách.',

            'email.required' => 'Email không được để trống.',
            'email.regex' => 'Email không đúng định dạng.',

            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',

            'confirm_password.required' => 'Vui lòng xác nhận mật khẩu.',
            'confirm_password.same' => 'Mật khẩu xác nhận không khớp.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'ten_tai_khoan' => trim($this->ten_tai_khoan),
            'ho_ten' => trim($this->ho_ten),
        ]);
    }
}
