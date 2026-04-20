<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaiDang extends Model
{
    protected $table = 'bai_dang';

    protected $fillable = [
        'nguoi_dung_id',
        'loai_bai',
        'tieu_de',
        'mo_ta',
        'hinh_anh',
        'dia_diem',
        'lat',
        'lng',
        'region',
        'so_luong',
        'trang_thai',
    ];

    protected $casts = [
        'hinh_anh' => 'array',
    ];

    public function nguoiDung()
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }

    public function thichs()
    {
        return $this->hasMany(ThichBaiDang::class, 'bai_dang_id');
    }

    public function binhLuans()
    {
        return $this->hasMany(BinhLuanBaiDang::class, 'bai_dang_id');
    }

    public function baoCaos()
    {
        return $this->hasMany(BaoCaoBaiDang::class, 'bai_dang_id');
    }
}

