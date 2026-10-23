<?php

namespace Database\Seeders;

use App\Models\Dapur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One demo login per operational role (PRD §MODULE 0), so every role-gated
 * page in the app has a real account to sign in with. Password for all: 12341234.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $dapurs = Dapur::orderBy('code')->get();

        foreach ($dapurs as $index => $dapur) {
            $user = User::updateOrCreate(
                ['email' => sprintf('dapur%d@bms.test', $index + 1)],
                [
                    'name' => "Admin {$dapur->name}",
                    'password' => Hash::make('12341234'),
                    'dapur_id' => $dapur->id,
                ]
            );
            $user->syncRoles(['dapur']);
        }

        $roleUsers = [
            'ops' => ['email' => 'ops@bms.test', 'name' => 'Tim Operasional'],
            'purchasing' => ['email' => 'purchasing@bms.test', 'name' => 'Tim Purchasing'],
            'gudang' => ['email' => 'gudang@bms.test', 'name' => 'Tim Gudang'],
            'finance' => ['email' => 'finance@bms.test', 'name' => 'Tim Finance'],
        ];

        foreach ($roleUsers as $role => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('12341234')]
            );
            $user->syncRoles([$role]);
        }
    }
}
