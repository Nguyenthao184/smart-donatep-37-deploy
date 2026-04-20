<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToChuc extends Model
{
    protected $table = 'to_chuc';

    protected $fillable = [
        'nguoi_dung_id',
        'xac_minh_to_chuc_id',
        'ten_to_chuc',
        'mo_ta',
        'dia_chi',
        'so_dien_thoai',
        'email',
        'logo',
        'so_cd_dang_hd',
        'trang_thai',
        'diem_uy_tin',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }

    public function taiKhoanGayQuy()
    {
        return $this->hasOne(TaiKhoanGayQuy::class, 'to_chuc_id');
    }  
}
