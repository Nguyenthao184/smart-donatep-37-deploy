<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'ten_chien_dich' => 'required|string|max:255',
            'danh_muc_id' => 'required|exists:danh_muc,id',
            'mo_ta' => 'required|string',
            'hinh_anh' => 'required|array|min:1',
            'hinh_anh.*' => 'image|mimes:jpg,jpeg,png|max:2048',
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
            'ten_chien_dich.max' => 'Tên chiến dịch không quá 255 ký tự',

            'danh_muc_id.required' => 'Vui lòng chọn danh mục',
            'danh_muc_id.exists' => 'Danh mục không hợp lệ',

            'mo_ta.required' => 'Mô tả không được để trống',
            
            'hinh_anh.required' => 'Hình ảnh không được để trống',
            'hinh_anh.image' => 'File phải là hình ảnh',
            'hinh_anh.mimes' => 'Hình ảnh phải có định dạng jpg, jpeg hoặc png',
            'hinh_anh.max' => 'Hình ảnh không được lớn hơn 2MB',

            'muc_tieu_tien.required' => 'Mục tiêu tiền không được để trống',
            'muc_tieu_tien.numeric' => 'Mục tiêu tiền phải là số',
            'muc_tieu_tien.min' => 'Mục tiêu tiền phải lớn hơn hoặc bằng 10.000',

            'ngay_ket_thuc.required' => 'Ngày kết thúc không được để trống',
            'ngay_ket_thuc.date' => 'Ngày kết thúc phải là một ngày hợp lệ',
            'ngay_ket_thuc.after' => 'Ngày kết thúc phải sau ngày hôm nay',

            'vi_tri.required' => 'Vị trí không được để trống',
            'vi_tri.min' => 'Địa chỉ quá ngắn',
            'vi_tri.regex' => 'Địa chỉ không được chứa ký tự đặc biệt',
        ];
    }
}
