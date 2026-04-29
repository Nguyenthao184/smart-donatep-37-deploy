<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NguoiDungVaiTroSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('nguoi_dung_vai_tro')->truncate();

        $users = DB::table('nguoi_dung')->get();

        foreach ($users as $user) {

            // ADMIN
            if ($user->id == 1) {
                DB::table('nguoi_dung_vai_tro')->insert([
                    'nguoi_dung_id' => $user->id,
                    'vai_tro_id' => 1
                ]);
                continue;
            }

            $hasOrg = DB::table('to_chuc')
                ->where('nguoi_dung_id', $user->id)
                ->exists();

            if ($hasOrg) {
                DB::table('nguoi_dung_vai_tro')->insert([
                    'nguoi_dung_id' => $user->id,
                    'vai_tro_id' => 3
                ]);
            } else{
                DB::table('nguoi_dung_vai_tro')->insert([
                    'nguoi_dung_id' => $user->id,
                    'vai_tro_id' => 2
                ]);
            }
        }
    }
}