<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanhBaoGianLan extends Model
{
    protected $table = 'canh_bao_gian_lan';

    public $timestamps = false;

    protected $fillable = [
        'nguoi_dung_id',
        'chien_dich_id',
        'loai_gian_lan',
        'diem_rui_ro',
        'mo_ta',
        'trang_thai',
        'created_at',
    ];

    protected $casts = [
        'diem_rui_ro' => 'float',
        'created_at' => 'datetime',
    ];
}

