<?php

namespace App\Http\Requests\Withdraw;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ChienDichGayQuy;
use App\Models\GiaoDichQuy;

class StoreWithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->toChuc;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'mo_ta' => $this->mo_ta ? trim($this->mo_ta) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'chien_dich_gay_quy_id' => [
                'required',
                'integer',
                'exists:chien_dich_gay_quy,id'
            ],

            'so_tien' => [
                'required',
                'numeric',
                'min:1000',
                'max:999999999'
            ],

            'mo_ta' => [
                'nullable',
                'string',
                'max:255'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'chien_dich_gay_quy_id.required' => 'Vui lòng chọn chiến dịch',
            'chien_dich_gay_quy_id.exists' => 'Chiến dịch không tồn tại',

            'so_tien.required' => 'Vui lòng nhập số tiền',
            'so_tien.numeric' => 'Số tiền không hợp lệ',
            'so_tien.min' => 'Số tiền tối thiểu là 1.000đ',
            'so_tien.max' => 'Số tiền vượt quá giới hạn',

            'mo_ta.max' => 'Mô tả không được vượt quá 255 ký tự',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $campaignId = $this->chien_dich_gay_quy_id;
            $soTienRut = (float) $this->so_tien;

            $orgId = auth()->user()->toChuc->id;

            $campaign = ChienDichGayQuy::where('id', $campaignId)
                ->where('to_chuc_id', $orgId)
                ->first();

            if (!$campaign) {
                $validator->errors()->add(
                    'chien_dich_gay_quy_id',
                    'Chiến dịch không hợp lệ'
                );

                return;
            }

            // =========================
            // TÍNH SỐ DƯ CÓ THỂ RÚT
            // =========================

            $tongUngHo = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                ->where('loai_giao_dich', 'UNG_HO')
                ->sum('so_tien');

            $tongRutDaDuyet = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                ->where('loai_giao_dich', 'RUT')
                ->where('trang_thai', 'DA_DUYET')
                ->sum('so_tien');

            // tính luôn request đang chờ duyệt
            $tongRutChoDuyet = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                ->where('loai_giao_dich', 'RUT')
                ->where('trang_thai', 'CHO_DUYET')
                ->sum('so_tien');

            $soDuCoTheRut = $tongUngHo
                - $tongRutDaDuyet
                - $tongRutChoDuyet;

            // =========================
            // CHẶN RÚT QUÁ SỐ DƯ
            // =========================

            if ($soTienRut > $soDuCoTheRut) {

                $validator->errors()->add(
                    'so_tien',
                    'Số tiền vượt quá số dư có thể rút'
                );
            }

            // =========================
            // CHẶN SPAM REQUEST
            // =========================

            $hasPendingRequest = GiaoDichQuy::where('chien_dich_gay_quy_id', $campaign->id)
                ->where('loai_giao_dich', 'RUT')
                ->where('trang_thai', 'CHO_DUYET')
                ->exists();

            if ($hasPendingRequest) {
                $validator->errors()->add(
                    'chien_dich_gay_quy_id',
                    'Chiến dịch đang có yêu cầu rút tiền chờ duyệt'
                );
            }
        });
    }
}
