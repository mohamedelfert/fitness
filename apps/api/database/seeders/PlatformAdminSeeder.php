<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Platform\Models\PlatformUser;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformUser::firstOrCreate(
            ['email' => 'admin@fitnessos.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'role' => 'platform.superadmin',
            ],
        );
    }
}
