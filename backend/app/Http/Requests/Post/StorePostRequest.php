<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'loai_bai' => 'required|in:CHO,NHAN',
            'tieu_de' => 'required|string|max:255',
            'mo_ta' => 'required|string|max:5000',
            // multiple images: hinh_anh[] (max 6)
            'hinh_anh' => 'nullable|array|max:6',
            'hinh_anh.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
            // dia_diem: địa chỉ user nhập để hiển thị (đường/quận/tỉnh...).
            // Nếu không gửi lat/lng thì bắt buộc dia_diem phải đủ chi tiết.
            'dia_diem' => [
                'required_without_all:lat,lng',
                'string',
                'max:255',
                'not_regex:/^\s*\d+/',
                'regex:/,/',
            ],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'so_luong' => 'required|integer|min:1',

            'trang_thai' => 'nullable|in:CON_NHAN,CON_TANG,DA_NHAN,DA_TANG',
        ];
    }

    public function messages(): array
    {
        return [
            'loai_bai.required' => 'Vui lòng chọn loại bài (CHO hoặc NHAN).',
            'so_luong.min' => 'Số lượng phải >= 1.',
            'dia_diem.not_regex' => 'Vui lòng không nhập số nhà. Chỉ nhập Phường/Xã, Quận/Huyện, Tỉnh/Thành.',
            'dia_diem.regex' => 'Địa điểm cần theo dạng: Phường/Xã, Quận/Huyện, Tỉnh/Thành.',
        ];
    }
}

