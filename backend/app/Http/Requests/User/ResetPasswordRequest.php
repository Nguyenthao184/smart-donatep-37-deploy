<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'email' => trim($this->email),
            'new_password' => trim($this->new_password),
            'confirm_password' => trim($this->confirm_password),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',

            'new_password' => [
                'required',
                'string',
                'min:6'
            ],

            'confirm_password' => [
                'required',
                'same:new_password'
            ]
        ];
    }
}
