<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ClinicsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('clinics')->delete();
        
        \DB::table('clinics')->insert(array (
            0 => 
            array (
                'id' => 1,
                'slug' => 'sohil-clinic',
                'name' => 'sohil clinic',
                'address_line1' => '706 RK Prime 2, Mahapuja Dham Chock 150 Feet Ring Road',
                'address_line2' => NULL,
                'pincode' => '360005',
                'city' => 'Rajkot',
                'gst_number' => 'GST120',
                'first_sale_share' => '10.00',
                'sale_share' => '12.00',
                'google_map_link' => 'https://laragon.lemonsqueezy.com',
                'logo' => NULL,
                'phone' => '09687784381',
                'email' => 'sohilclinic@gmail.com',
                'website' => NULL,
                'description' => 'test',
                'is_active' => 1,
                'created_at' => '2025-10-26 18:28:20',
                'updated_at' => '2025-10-26 18:28:20',
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}