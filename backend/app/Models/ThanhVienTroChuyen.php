<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThanhVienTroChuyen extends Model
{
    protected $table = 'thanh_vien_tro_chuyen';

    protected $fillable = [
        'cuoc_tro_chuyen_id',
        'nguoi_dung_id',
        'lan_cuoi_xem_luc',
        'sau_tin_nhan_id',
    ];

    protected $casts = [
        'lan_cuoi_xem_luc' => 'datetime',
    ];

    public function cuocTroChuyen(): BelongsTo
    {
        return $this->belongsTo(CuocTroChuyen::class, 'cuoc_tro_chuyen_id');
    }
}

