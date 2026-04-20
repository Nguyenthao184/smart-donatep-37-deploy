<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'current_password' => $this->current_password ? trim($this->current_password) : null,
            'new_password' => trim($this->new_password),
            'confirm_password' => trim($this->confirm_password),
        ]);
    }

    public function rules(): array
    {
        $user = Auth::user();

        return [
            'current_password' => [
                $user && $user->mat_khau ? 'required' : 'nullable'
            ],

            'new_password' => [
                'required',
                'string',
                'min:6',
                'different:current_password'
            ],

            'confirm_password' => [
                'required',
                'same:new_password'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Mật khẩu hiện tại không được để trống.',

            'new_password.required' => 'Mật khẩu mới không được để trống.',
            'new_password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.different' => 'Mật khẩu mới không được trùng mật khẩu cũ.',

            'confirm_password.required' => 'Vui lòng xác nhận mật khẩu.',
            'confirm_password.same' => 'Mật khẩu xác nhận không khớp.'
        ];
    }
}
