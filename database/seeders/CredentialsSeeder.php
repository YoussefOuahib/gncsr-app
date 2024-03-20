<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Termwind\Components\Hr;

class CredentialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $credentials = [
            [
                'user_id' => 1,
                'tak_url' => 'https://20.151.152.0:8446',
                'tak_login' => 'useric4w',
                'tak_password' => 'V@T4ESvwTL@v28i',
                'sharepoint_url' => 'https://gsarics.sharepoint.com',
                'sharepoint_client_id' => 'youssefo@gsarics.ca',
                'sharepoint_client_secret' => 'linkin22dynamics@',
                'sharepoint_tenant_id' => 'gsarics.onmicrosoft.com',
                'dynamics_url' => 'https://gsarics.crm3.dynamics.com/',
                'dynamics_client_id' => '3dddeb5e-9e00-454e-a921-2120529b1e63',
                'dynamics_client_secret' => 'z.f8Q~isyy9FDiKhRupOAeDZDbq3~JgFDUTicdcd',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Add more data as needed
        ];

        // Insert data into the 'credentials' table
        DB::table('credentials')->insert($credentials);
    }
}
