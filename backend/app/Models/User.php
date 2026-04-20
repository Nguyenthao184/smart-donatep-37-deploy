<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory; 

    protected $table = 'nguoi_dung';

    protected $fillable = [
        'google_id',
        'ho_ten',
        'ten_tai_khoan',
        'email',
        'mat_khau',
        'anh_dai_dien',
        'dia_chi',
        'trang_thai'
    ];

    protected $hidden = [
        'mat_khau'
    ];

    public function getAuthPassword()
    {
        return $this->mat_khau;
    }

    public function roles()
    {
        return $this->belongsToMany(
            VaiTro::class,
            'nguoi_dung_vai_tro',
            'nguoi_dung_id',
            'vai_tro_id'
        );
    }

    public function toChuc()
    {
        return $this->hasOne(ToChuc::class, 'nguoi_dung_id');
    }

    public function thichBaiDangs()
    {
        return $this->hasMany(ThichBaiDang::class, 'nguoi_dung_id');
    }

    public function binhLuanBaiDangs()
    {
        return $this->hasMany(BinhLuanBaiDang::class, 'nguoi_dung_id');
    }

    public function baoCaoBaiDangs()
    {
        return $this->hasMany(BaoCaoBaiDang::class, 'nguoi_to_cao_id');
    }
}