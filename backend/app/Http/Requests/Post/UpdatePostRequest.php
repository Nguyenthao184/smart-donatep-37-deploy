<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('loai_bai') && is_string($this->loai_bai)) {
            $this->merge([
                'loai_bai' => strtoupper(trim($this->loai_bai)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'loai_bai' => 'nullable|in:CHO,NHAN',
            'tieu_de' => 'nullable|string|max:255',
            'mo_ta' => 'nullable|string|max:5000',
            // multiple images: hinh_anh[] (max 6)
            'hinh_anh' => 'nullable|array|max:6',
            'hinh_anh.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
            'existing_images' => 'nullable|array|max:6',
            'existing_images.*' => 'string|max:2048',
            // Nếu user chỉ update lat/lng (từ map) mà không update dia_diem thì ok.
            // Nếu update dia_diem mà bỏ lat/lng thì sẽ auto geocode.
            'dia_diem' => [
                'nullable',
                'string',
                'max:255',
                'not_regex:/^\s*\d+/',
                'regex:/,/',
            ],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'so_luong' => 'nullable|integer|min:1',
            'trang_thai' => 'nullable|in:CON_NHAN,CON_TANG,DA_NHAN,DA_TANG',
        ];
    }

    public function messages(): array
    {
        return [
            'loai_bai.in' => 'loai_bai chỉ được nhận CHO hoặc NHAN.',
            'so_luong.min' => 'Số lượng phải >= 1.',
            'dia_diem.not_regex' => 'Vui lòng không nhập số nhà. Chỉ nhập Phường/Xã, Quận/Huyện, Tỉnh/Thành.',
            'dia_diem.regex' => 'Địa điểm cần theo dạng: Phường/Xã, Quận/Huyện, Tỉnh/Thành.',
        ];
    }
}

