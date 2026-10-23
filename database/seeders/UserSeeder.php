<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make(config('services.seed_password'));

        $users = [
            ['Quản trị TechPhone', 'admin@gmail.com', '0900000000', 'admin', 'active', '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh'],
            ['Nguyễn Minh Anh', 'user@gmail.com', '0911111111', 'customer', 'active', '45 Lê Lợi, Quận 1, TP. Hồ Chí Minh'],
            ['Trần Hoàng Nam', 'nam@gmail.com', '0922222222', 'customer', 'active', '21 Hai Bà Trưng, Quận 3, TP. Hồ Chí Minh'],
            ['Lê Thu Trang', 'locked@gmail.com', '0933333333', 'customer', 'locked', '9 Nguyễn Trãi, Quận 5, TP. Hồ Chí Minh'],
            ['Phạm Gia Bảo', 'inactive@gmail.com', '0944444444', 'customer', 'inactive', '72 Cách Mạng Tháng 8, Quận 10, TP. Hồ Chí Minh'],
            ['Võ Thị Lan', 'lan@gmail.com', '0955555555', 'customer', 'active', '18 Trần Phú, Hải Châu, Đà Nẵng'],
            ['Đặng Quốc Huy', 'huy@gmail.com', '0966666666', 'customer', 'active', '120 Cầu Giấy, Hà Nội'],
        ];

        foreach ($users as [$name, $email, $phone, $role, $status, $address]) {
            User::create([
                'full_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role,
                'status' => $status,
                'address' => $address,
                'phone_verified_at' => now(),
            ]);
        }
    }
}
