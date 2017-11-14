<?php

use Illuminate\Database\Seeder;
use Rhumsaa\Uuid\Uuid;

class StatesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'New South Wales',
            'short_name' => 'NSW',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Australian Capital Territory',
            'short_name' => 'ACT',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Northern Territory',
            'short_name' => 'NT',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Queensland',
            'short_name' => 'QLD',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'South Australia',
            'short_name' => 'SA',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Tasmania',
            'short_name' => 'TAS',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Victoria',
            'short_name' => 'VIC',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        DB::table('states')->insert([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Western Australia',
            'short_name' => 'WA',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
    	]);
        
        
    }
}