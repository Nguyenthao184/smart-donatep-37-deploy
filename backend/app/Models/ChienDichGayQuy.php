<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChienDichGayQuy extends Model
{
    use HasFactory;

    protected $table = 'chien_dich_gay_quy';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'tai_khoan_gay_quy_id',
        'to_chuc_id',
        'danh_muc_id',
        'ten_chien_dich',
        'mo_ta',
        'hinh_anh',
        'muc_tieu_tien',
        'so_tien_da_nhan',
        'ngay_ket_thuc',
        'vi_tri',
        'lat',
        'lng',
        'ma_noi_dung_ck',
        'trang_thai'
    ];

    public function toChuc()
    {
        return $this->belongsTo(ToChuc::class, 'to_chuc_id');
    }

    public function danhMuc()
    {
        return $this->belongsTo(DanhMuc::class, 'danh_muc_id');
    }

    public function taiKhoanGayQuy()
    {
        return $this->belongsTo(TaiKhoanGayQuy::class, 'tai_khoan_gay_quy_id');
    }

    public function ungHos()
    {
        return $this->hasMany(UngHo::class, 'chien_dich_gay_quy_id');
    }

    public function chiTieus()
    {
        return $this->hasMany(ChiTieuChienDich::class, 'chien_dich_gay_quy_id');
    }
}
