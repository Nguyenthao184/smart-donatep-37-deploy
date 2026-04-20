<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuocTroChuyen extends Model
{
    protected $table = 'cuoc_tro_chuyen';

    protected $fillable = [
        'khoa_1_1',
        'tin_nhan_cuoi_id',
    ];

    public function thanhVien(): HasMany
    {
        return $this->hasMany(ThanhVienTroChuyen::class, 'cuoc_tro_chuyen_id');
    }

    public function tinNhan(): HasMany
    {
        return $this->hasMany(TinNhan::class, 'cuoc_tro_chuyen_id');
    }
}

