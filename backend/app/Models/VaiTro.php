<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
    protected $table = 'vai_tro';

    protected $fillable = [
        'ten_vai_tro'
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'nguoi_dung_vai_tro',
            'vai_tro_id',
            'nguoi_dung_id'
        );
    }
}