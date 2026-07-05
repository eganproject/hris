<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed default application users.
     */
    public function run(): void
    {
        User::query()
            ->whereIn('email', [
                'test@example.com',
                'admin@cahayaoptima.test',
                'hr@cahayaoptima.test',
                'reader@cahayaoptima.test',
                'payroll@cahayaoptima.test',
            ])
            ->delete();

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => 'Password!2',
            ],
        );

        $user->syncRoles(['superadmin']);
    }
}
