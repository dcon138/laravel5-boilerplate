<?php

use Illuminate\Database\Seeder;
use Rhumsaa\Uuid\Uuid;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'first_name' => 'Default',
            'last_name' => 'User',
            'email' => 'default.user@domain.com',
            'password' => Hash::make('Password1!'),
            'phone' => '0412123123',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        
    }
}