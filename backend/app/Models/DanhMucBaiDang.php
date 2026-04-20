<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DanhMucBaiDang extends Model
{
    protected $table = 'danh_muc_bai_dang';

    protected $fillable = [
        'bai_dang_id',
        'danh_muc_code',
        'is_primary',
        'confidence',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'confidence' => 'float',
    ];

    public function baiDang()
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_id');
    }
}

