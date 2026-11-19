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
     * Credentials come from .env (not committed): ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            throw new RuntimeException('Set ADMIN_PASSWORD in web/.env before seeding.');
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@home.local')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => $password,            // hashed automatically by the User model
                'email_verified_at' => now(),       // no email server, so mark as verified
            ],
        );
    }
}
