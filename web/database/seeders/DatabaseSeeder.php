<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates (or updates) the single household account.
     * Registration is turned off, so this is the only way to get a login.
     * Credentials come from config/app.php 'admin', i.e. ADMIN_* in .env (not committed).
     */
    public function run(): void
    {
        $admin = config('app.admin');
        $password = $admin['password'];

        if (! $password) {
            throw new RuntimeException('Set ADMIN_PASSWORD in web/.env before seeding.');
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $password,            // hashed automatically by the User model
                'email_verified_at' => now(),       // no email server, so mark as verified
            ],
        );
    }
}
