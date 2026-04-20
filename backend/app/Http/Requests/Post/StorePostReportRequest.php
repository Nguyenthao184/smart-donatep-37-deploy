<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ly_do') && is_string($this->ly_do)) {
            $this->merge([
                'ly_do' => strtoupper(trim($this->ly_do)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'ly_do' => ['required', 'in:SPAM,LUA_DAO,NOI_DUNG_XAU,KHAC'],
            'mo_ta' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
