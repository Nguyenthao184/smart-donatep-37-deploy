<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class NguoiDungFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'ho_ten' => fake()->name(),
            'ten_tai_khoan' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'mat_khau' => Hash::make('123456'),
            'anh_dai_dien' => null,
            'trang_thai' => fake()->randomElement(['HOAT_DONG', 'HOAT_DONG', 'HOAT_DONG', 'BI_CAM']), // Tăng xác suất 'HOAT_DONG'
        ];
    }
}