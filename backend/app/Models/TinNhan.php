<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TinNhan extends Model
{
    protected $table = 'tin_nhan';

    protected $fillable = [
        'cuoc_tro_chuyen_id',
        'nguoi_gui_id',
        'noi_dung',
        'loai_tin',
        'da_xem',
        'da_thu_hoi',
        'tep_dinh_kem',
    ];

    protected $casts = [
        'da_xem' => 'boolean',
        'da_thu_hoi' => 'boolean',
    ];

    public function cuocTroChuyen(): BelongsTo
    {
        return $this->belongsTo(CuocTroChuyen::class, 'cuoc_tro_chuyen_id');
    }
}

