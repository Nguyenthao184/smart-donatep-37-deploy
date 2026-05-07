<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiaoDichQuy extends Model
{
    protected $table = 'giao_dich_quy';

    protected $fillable = [
        'tai_khoan_gay_quy_id',
        'chien_dich_gay_quy_id',
        'ung_ho_id',
        'so_tien',
        'loai_giao_dich',
        'mo_ta',
        'trang_thai',
        'ma_giao_dich_ngan_hang',
        'ghi_chu_admin',
        'ngay_giao_dich',
    ];

    public function ungHo()
    {
        return $this->belongsTo(UngHo::class, 'ung_ho_id');
    }

    public function chienDich()
    {
        return $this->belongsTo(ChienDichGayQuy::class, 'chien_dich_gay_quy_id');
    }
}
