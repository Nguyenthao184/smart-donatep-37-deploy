<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ten_chien_dich' => 'required|string|max:255',
            'danh_muc_id' => 'required|exists:danh_muc,id',
            'mo_ta' => 'required|string',

            'anh_cu' => 'nullable|array',
            'anh_cu.*' => 'string',

            'xoa_anh' => 'nullable|array',
            'xoa_anh.*' => 'string',

            'anh_moi' => 'nullable|array',
            'anh_moi.*' => 'image|mimes:jpg,jpeg,png|max:2048',

            'muc_tieu_tien' => 'required|numeric|min:10000',
            'ngay_ket_thuc' => 'required|date|after:today',

            'vi_tri' => 'required|string|max:255',
            'lat' => 'required|numeric|between:8,24',
            'lng' => 'required|numeric|between:102,110',
        ];
    }

    public function messages()
    {
        return [
            'ten_chien_dich.required' => 'Tên chiến dịch không được để trống',

            'danh_muc_id.required' => 'Vui lòng chọn danh mục',

            'mo_ta.required' => 'Mô tả không được để trống',

            'anh_moi.*.image' => 'File phải là hình ảnh',
            'anh_moi.*.mimes' => 'Ảnh phải là jpg, jpeg hoặc png',
            'anh_moi.*.max' => 'Ảnh không quá 2MB',

            'muc_tieu_tien.required' => 'Mục tiêu tiền không được để trống',

            'ngay_ket_thuc.after' => 'Ngày kết thúc phải sau hôm nay',

            'lat.between' => 'Vĩ độ không hợp lệ',
            'lng.between' => 'Kinh độ không hợp lệ',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cu = $this->anh_cu ?? [];
            $moi = $this->file('anh_moi') ?? [];

            if (count($cu) + count($moi) === 0) {
                $validator->errors()->add('hinh_anh', 'Phải có ít nhất 1 hình ảnh');
            }
        });
    }
}