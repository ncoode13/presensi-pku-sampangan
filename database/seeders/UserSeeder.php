<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create(
            [
                'name'           => 'user',
                'username'       => 'user',
                'email'          => 'user@gmail.com',
                'password'       => bcrypt('password'),
                'role'           => 'user',
                'remember_token' => Str::random(10),
            ],
        );
        User::create(
            [
                'name'           => 'admin',
                'username'       => 'admin',
                'email'          => 'admin@gmail.com',
                'password'       => bcrypt('password'),
                'role'           => 'admin',
                'remember_token' => Str::random(10),
            ],
        );
        User::create(
            [
                'name'           => 'superadmin',
                'username'       => 'superadmin',
                'email'          => 'superadmin@gmail.com',
                'password'       => bcrypt('password'),
                'role'           => 'superadmin',
                'remember_token' => Str::random(10),
            ],
        );
    }
}
