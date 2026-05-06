<?php

use App\Models\ThanhVienTroChuyen;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Tất cả kênh private cho chat 1:1.
|
*/

Broadcast::channel('cuoc-tro-chuyen.{cuocTroChuyenId}', function ($user, int $cuocTroChuyenId) {
    return ThanhVienTroChuyen::query()
        ->where('cuoc_tro_chuyen_id', $cuocTroChuyenId)
        ->where('nguoi_dung_id', (int)$user->id)
        ->exists();
    //return true;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
