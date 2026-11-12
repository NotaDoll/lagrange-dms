<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SampleComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->error('No tenant found. Create a tenant first.');
            return;
        }

        $complaints = [
            // [category, status, days ago, description]
            ['maintenance', 'in_progress', 9, 'The aircon in room 204 has been leaking water onto the floor every night since last week. The mattress is getting wet.'],
            ['maintenance', 'in_progress', 8, 'Water drips from the aircon unit in my room (204) when it is turned on. Please check it.'],
            ['maintenance', 'in_progress', 5, 'The bathroom light on the 2nd floor comfort room keeps flickering and sometimes goes off completely.'],
            ['maintenance', 'in_progress', 3, 'Our bedroom door lock is broken and cannot be locked from inside. I do not feel safe.'],
            ['noise',       'in_progress', 6, 'Tenants in the next room play loud music and talk until 2am almost every night. I cannot sleep before my classes.'],
            ['noise',       'in_progress', 4, 'There is very loud music from the 3rd floor after 11pm on weekends.'],
            ['billing',     'in_progress', 7, 'My electricity charge this month is 1,850 pesos, which is much higher than usual. I only use a fan and a laptop.'],
            ['billing',     'in_progress', 2, 'I already paid my rent on the 5th through GCash but it still shows as unpaid in my account.'],
            ['other',       'in_progress', 5, 'The wifi is very slow in the evening and disconnects often. I cannot join my online classes.'],
            ['other',       'in_progress', 1, 'Someone keeps using my food in the shared refrigerator even though I labeled it.'],
            ['other',       'in_progress', 2, 'Please fix it'],
            ['maintenance', 'in_progress',10, 'asdfg hjkl'],

            // Bisaya / Tagalog
            ['maintenance', 'in_progress', 6, 'Naay tulo ang atop sa kwarto 204 kung ulan. Nabasa na akong kama ug mga libro.'],
            ['noise',       'in_progress', 4, 'Kusog kaayo ang musika sa kasikbit nga kwarto hangtod alas 2 sa buntag. Dili ko makatulog.'],
            ['maintenance', 'in_progress', 3, 'Madulom ang CR sa ikaduhang andana kay patay ang suga.'],
            ['billing',     'in_progress', 5, 'Nibayad na ko sa akong renta pinaagi sa GCash pero wala pa gihapon makita sa akong account.'],
            ['other',       'in_progress', 2, 'Hinay kaayo ang wifi sa gabii, dili ko makaapil sa akong online class.'],
            ['maintenance', 'in_progress', 8, 'Sira ang kandado ng pinto namin, hindi ma-lock mula sa loob. Natatakot ako.'],
        ];

        foreach ($complaints as [$category, $status, $daysAgo, $description]) {
            $complaint = Complaint::create([
                'tenant_id'   => $tenant->id,
                'category'    => $category,
                'description' => $description,
                'status'      => $status,
            ]);

            $complaint->forceFill([
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ])->save();
        }

        $this->command->info('Added '.count($complaints).' sample complaints.');
    }
}
