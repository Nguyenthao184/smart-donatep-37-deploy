<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UngHo extends Model
{
    protected $table = 'ung_ho';

    protected $fillable = [
        'nguoi_dung_id',
        'chien_dich_gay_quy_id',
        'so_tien',
        'phuong_thuc',
        'payment_ref', 
        'trang_thai',
    ];

    public function giaoDichQuy()
    {
        return $this->hasOne(GiaoDichQuy::class, 'ung_ho_id');
    }

    public function chienDich()
    {
        return $this->belongsTo(ChienDichGayQuy::class, 'chien_dich_gay_quy_id');
    }
}
