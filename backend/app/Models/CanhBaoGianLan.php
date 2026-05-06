<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanhBaoGianLan extends Model
{
    protected $table = 'canh_bao_gian_lan';

    public $timestamps = true;

    protected $fillable = [
        'nguoi_dung_id',
        'chien_dich_id',
        'target_type',
        'target_id',
        'source',
        'violation_code',
        'count',
        'time_window_seconds',
        'window_started_at',
        'details',
        'last_seen_at',
        'loai_canh_bao',
        'muc_rui_ro',
        'loai_gian_lan',
        'diem_rui_ro',
        'ly_do',
        'mo_ta',
        'trang_thai',
        'decision',
        'admin_id',
        'admin_note',
        'reviewed_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'diem_rui_ro' => 'float',
        'target_id' => 'int',
        'admin_id' => 'int',
        'count' => 'int',
        'time_window_seconds' => 'int',
        'window_started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'details' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

