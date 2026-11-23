<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates (or updates) the first household account, as an admin.
     * Registration is turned off: this admin adds the rest of the family in Settings → Family.
     * Credentials come from config/app.php 'admin', i.e. ADMIN_* in .env (not committed).
     */
    public function run(): void
    {
        $admin = config('app.admin');
        $password = $admin['password'];

        if (! $password) {
            throw new RuntimeException('Set ADMIN_PASSWORD in web/.env before seeding.');
        }

        $user = User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $password,            // hashed automatically by the User model
                'email_verified_at' => now(),       // no email server, so mark as verified
            ],
        );

        // is_admin is deliberately not mass-assignable, so set it explicitly.
        $user->forceFill(['is_admin' => true])->save();
    }
}
