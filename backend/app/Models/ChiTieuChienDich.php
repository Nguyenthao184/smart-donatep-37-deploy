<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTieuChienDich extends Model
{
    use HasFactory;

    protected $table = 'chi_tieu_chien_dich';

    protected $fillable = [
        'chien_dich_gay_quy_id',
        'giao_dich_quy_id',
        'ten_hoat_dong',
        'mo_ta',
        'so_tien',
    ];

    public function chienDich()
    {
        return $this->belongsTo(ChienDichGayQuy::class, 'chien_dich_gay_quy_id');
    }

    public function giaoDich()
    {
        return $this->belongsTo(GiaoDichQuy::class, 'giao_dich_quy_id');
    }
}