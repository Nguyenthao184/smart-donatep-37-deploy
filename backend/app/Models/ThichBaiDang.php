<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThichBaiDang extends Model
{
    protected $table = 'thich_bai_dang';

    protected $fillable = [
        'bai_dang_id',
        'nguoi_dung_id',
    ];

    public function baiDang(): BelongsTo
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_id');
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }
}
