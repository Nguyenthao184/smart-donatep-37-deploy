<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trang_thai') && is_string($this->trang_thai)) {
            $this->merge([
                'trang_thai' => strtoupper(trim($this->trang_thai)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'trang_thai' => ['required', 'in:CHO_XU_LY,DA_XU_LY,TU_CHOI'],
        ];
    }
}
