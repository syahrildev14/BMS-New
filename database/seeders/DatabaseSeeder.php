<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(SatuanSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(DapurSeeder::class);
        $this->call(GudangSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(StockSeeder::class);
        $this->call(DailyPriceSeeder::class);
        $this->call(OrderSeeder::class);

        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('12341234'),
            ]
        );
        $admin->syncRoles(['admin']);

        User::updateOrCreate(
            ['email' => 'user@deevatech.my.id'],
            [
                'name' => 'User',
                'password' => Hash::make('User123!'),
            ]
        );
    }
}
