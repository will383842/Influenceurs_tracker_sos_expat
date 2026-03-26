<?php

namespace Database\Seeders;

use App\Models\PublicationSchedule;
use App\Models\PublishingEndpoint;
use Illuminate\Database\Seeder;

class PublishingEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $endpoint = PublishingEndpoint::updateOrCreate(
            ['name' => 'SOS-Expat Firestore'],
            [
                'type' => 'firestore',
                'config' => [
                    'project_id' => 'sos-urgently-ac307',
                    'collection' => 'blog_articles',
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );

        PublicationSchedule::updateOrCreate(
            ['endpoint_id' => $endpoint->id],
            [
                'max_per_day' => 50,
                'max_per_hour' => 10,
                'min_interval_minutes' => 6,
                'active_hours_start' => '08:00',
                'active_hours_end' => '20:00',
                'active_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'auto_pause_on_errors' => 5,
                'is_active' => true,
            ]
        );
    }
}
