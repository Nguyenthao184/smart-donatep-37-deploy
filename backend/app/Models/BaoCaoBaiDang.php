<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaoCaoBaiDang extends Model
{
    protected $table = 'bao_cao_bai_dang';

    protected $fillable = [
        'bai_dang_id',
        'nguoi_to_cao_id',
        'ly_do',
        'mo_ta',
        'trang_thai',
    ];

    public function baiDang(): BelongsTo
    {
        return $this->belongsTo(BaiDang::class, 'bai_dang_id');
    }

    public function nguoiToCao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_to_cao_id');
    }
}
