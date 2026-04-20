<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'noi_dung' => ['required', 'string', 'min:1', 'max:2000'],

            'id_cha' => 'nullable|exists:binh_luan_bai_dang,id'
        ];
    }
}
