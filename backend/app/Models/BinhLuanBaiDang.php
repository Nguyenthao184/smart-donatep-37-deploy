<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinhLuanBaiDang extends Model
{
    protected $table = 'binh_luan_bai_dang';

    protected $fillable = [
        'bai_dang_id',
        'nguoi_dung_id',
        'noi_dung',
        'id_cha',
    ];

    public function baiDang(): BelongsTo
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_id');
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }public function replies()
    {
        return $this->hasMany(BinhLuanBaiDang::class, 'id_cha');
    }
    
    public function parent()
    {
        return $this->belongsTo(BinhLuanBaiDang::class, 'id_cha');
    }
}
