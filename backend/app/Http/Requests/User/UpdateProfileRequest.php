<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $user = auth()->user();
        $userId = auth()->user();
        $roleId = $user->roles()->first()->id;
        $rules = [
            'ho_ten' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[A-Za-zÀ-ỹ]+( [A-Za-zÀ-ỹ]+)*$/'
            ],
            'anh_dai_dien' => [
                'sometimes',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048'
            ],
            'dia_chi_user' => [
                'sometimes',
                'nullable',
                'string',
                'min:10',
                'max:255',
                'regex:/^[\pL0-9\s,.-]+$/u'
            ],
        ];

        $toChuc = auth()->user()->toChuc;
        if ($toChuc) {
            $rules = array_merge($rules, [
                'email' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                    'email:rfc,dns',
                    Rule::unique('to_chuc','email')->ignore(optional(auth()->user()->toChuc)->id)
                ],
                'mo_ta' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:1000'
                ],
                'dia_chi' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'min:10',
                    'max:255',
                    'regex:/^[\pL0-9\s,.-]+$/u'
                ],
                'so_dien_thoai' => [
                    'sometimes',
                    'nullable',
                    'regex:/^(0[3|5|7|8|9])[0-9]{8}$/'
                ],
                'logo' => [
                    'sometimes',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:2048'
                ],
            ]);
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'ho_ten.required' => 'Họ tên không được để trống.',
            'ho_ten.string' => 'Họ tên phải là chuỗi ký tự.',
            'ho_ten.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'ho_ten.regex' => 'Họ tên chỉ được chứa chữ cái và mỗi từ cách nhau đúng 1 dấu cách.',

            'anh_dai_dien.image' => 'Ảnh đại diện phải là file hình ảnh.',
            'anh_dai_dien.mimes' => 'Ảnh đại diện chỉ được định dạng jpg, jpeg, png.',
            'anh_dai_dien.max' => 'Ảnh đại diện không được lớn hơn 2MB.',


            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại.',

            'mo_ta.string' => 'Mô tả phải là chuỗi ký tự.',
            'mo_ta.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            
            'dia_chi_user.string' => 'Địa chỉ phải là chuỗi ký tự.',
            'dia_chi_user.min' => 'Địa chỉ phải có ít nhất 10 ký tự.',
            'dia_chi_user.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'dia_chi_user.regex' => 'Địa chỉ chỉ được chứa chữ cái, số, dấu cách, dấu phẩy, dấu chấm và dấu gạch ngang.',

            'dia_chi.string' => 'Địa chỉ phải là chuỗi ký tự.',
            'dia_chi.min' => 'Địa chỉ phải có ít nhất 10 ký tự.',
            'dia_chi.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'dia_chi.regex' => 'Địa chỉ chỉ được chứa chữ cái, số, dấu cách, dấu phẩy, dấu chấm và dấu gạch ngang.',

            'so_dien_thoai.regex' => 'Số điện thoại không hợp lệ (phải là số Việt Nam).',

            'logo.image' => 'Logo phải là file hình ảnh.',
            'logo.mimes' => 'Logo chỉ được định dạng jpg, jpeg, png.',
            'logo.max' => 'Logo không được lớn hơn 2MB.',
        ];
    }

    protected function prepareForValidation()
    {
        $data = [];

        if ($this->has('ho_ten')) {
            $data['ho_ten'] = trim($this->ho_ten);
        }

        if ($this->has('email')) {
            $data['email'] = strtolower(trim($this->email));
        }

        if ($this->has('dia_chi')) {
            $data['dia_chi'] = trim($this->dia_chi);
        }

        if ($this->has('so_dien_thoai')) {
            $data['so_dien_thoai'] = trim($this->so_dien_thoai);
        }

        if ($this->has('mo_ta')) {
            $data['mo_ta'] = trim($this->mo_ta);
        }

        $this->merge($data);
    }
}
