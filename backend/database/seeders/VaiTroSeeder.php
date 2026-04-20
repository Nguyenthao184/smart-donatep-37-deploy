<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VaiTroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('vai_tro')->upsert([
            [
                'id' => 1,
                'ten_vai_tro' => 'ADMIN'
            ],
            [
                'id' => 2,
                'ten_vai_tro' => 'NGUOI_DUNG'
            ],
            [
                'id' => 3,
                'ten_vai_tro' => 'TO_CHUC'
            ]
        ], ['id'], ['ten_vai_tro']);
    }
}
