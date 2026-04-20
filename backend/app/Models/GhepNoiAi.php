<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GhepNoiAi extends Model
{
    protected $table = 'ghep_noi_ai';

    protected $fillable = [
        'bai_dang_nguon_id',
        'bai_dang_phu_hop_id',
        'diem_phu_hop',
        'trang_thai',
    ];

    public function baiDangNguon()
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_nguon_id');
    }

    public function baiDangPhuHop()
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_phu_hop_id');
    }
}

