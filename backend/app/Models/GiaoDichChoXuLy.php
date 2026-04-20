<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiaoDichChoXuLy extends Model
{
    protected $table = 'giao_dich_cho_xu_ly';

    protected $fillable = [
        'so_tien',
        'noi_dung',
        'thoi_gian',
        'trang_thai'
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'thoi_gian' => 'datetime',
    ];
}
