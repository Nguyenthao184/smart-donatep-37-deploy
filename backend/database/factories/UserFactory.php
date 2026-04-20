<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        $ho = ['Nguyễn','Trần','Lê','Phạm','Hoàng','Huỳnh','Phan','Vũ','Đặng','Bùi'];
        $tenDem = ['Hồ','Ánh','Minh','Quang','Thanh','Hữu','Đức'];
        $ten = ['An','Bình','Huy','Nam','Linh','Trang','Tuấn','Hà','Phúc','Khoa'];

        $fullName = $ho[array_rand($ho)] . ' ' . $tenDem[array_rand($tenDem)] . ' ' . $ten[array_rand($ten)];
        $parts = explode(' ', $fullName);
        $firstName = end($parts);
        $username = Str::slug($firstName, '');
        $number = fake()->unique()->numberBetween(1,999);
        return [
            'ho_ten' => $fullName,
            'ten_tai_khoan' => $username . $number,
            'email' => $username . $number . '@gmail.com',
            'mat_khau' => Hash::make('123456'),
            'anh_dai_dien' => null,
            'trang_thai' => fake()->boolean(90) ? 'HOAT_DONG' : 'BI_CAM',
        ];
    }
}