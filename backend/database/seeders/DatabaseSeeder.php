<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\VaiTroSeeder; 
use Database\Seeders\NguoiDungSeeder; 
use Database\Seeders\NguoiDungVaiTroSeeder;
use Database\Seeders\XacMinhToChucSeeder;
use Database\Seeders\ToChucSeeder;
use Database\Seeders\TaiKhoanGayQuySeeder;
use Database\Seeders\DanhMucSeeder;
use Database\Seeders\BaiDangSeeder;
use Database\Seeders\ChienDichGayQuySeeder;
use Database\Seeders\UngHoSeeder;
use Database\Seeders\GiaoDichQuySeeder;
use Database\Seeders\FraudDemoSeeder;
use Database\Seeders\DanhMucBaiDangSeeder;
use Database\Seeders\ChiTieuChienDichSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            VaiTroSeeder::class,
            NguoiDungSeeder::class,
            XacMinhToChucSeeder::class,
            ToChucSeeder::class,
            TaiKhoanGayQuySeeder::class,
            DanhMucSeeder::class,
            BaiDangSeeder::class,
            ChienDichGayQuySeeder::class,
            UngHoSeeder::class,
            GiaoDichQuySeeder::class,
            ChiTieuChienDichSeeder::class,
            NguoiDungVaiTroSeeder::class,
            DanhMucBaiDangSeeder::class,
            FraudDemoSeeder::class,
        ]);
    }
}
