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
                // 'first_sale_share' => '10.00',
                'sale_share' => '12.00',
                'google_map_link' => 'https://laragon.lemonsqueezy.com',
                'logo' => NULL,
                'phone' => '09687784381',
                'email' => 'sohilclinic@gmail.com',
                'website' => NULL,
                // 'description' => 'test',
                'start_time' => '08:00:00',
                'end_time' => '21:00:00',
                'number_of_beds' => 5,
                'is_active' => 1,
                'created_at' => '2025-10-26 18:28:20',
                'updated_at' => '2025-12-13 10:11:22',
                'deleted_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'slug' => 'am-dermatology-aesthetics',
                'name' => 'am dermatology & aesthetics',
                'address_line1' => '1B Kuvar house, 112 Shahid Bhagat Singh Road',
                'address_line2' => 'Colaba',
                'pincode' => '400005',
                'city' => 'Mumbai',
                'gst_number' => '1234',
                // 'first_sale_share' => '0.00',
                'sale_share' => '0.00',
                'google_map_link' => 'https://share.google/T9Nkggog4eTjPVn0T',
                'logo' => NULL,
                'phone' => '2248966605',
                'email' => NULL,
                'website' => NULL,
                // 'description' => NULL,
                'start_time' => '10:30:00',
                'end_time' => '20:00:00',
                'number_of_beds' => 1,
                'is_active' => 1,
                'created_at' => '2025-12-04 17:17:17',
                'updated_at' => '2025-12-04 17:17:17',
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}