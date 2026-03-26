<?php

namespace Database\Seeders;

use App\Models\PublicationSchedule;
use App\Models\PublishingEndpoint;
use Illuminate\Database\Seeder;

class PublishingEndpointSeeder extends Seeder
{
    public function run(): void
    {
        // ── Blog SOS-Expat (default) ──────────────────────────────
        $blogEndpoint = PublishingEndpoint::updateOrCreate(
            ['name' => 'Blog SOS-Expat'],
            [
                'type' => 'blog',
                'config' => [
                    'blog_api_url' => config('services.blog.url', 'http://localhost:8082'),
                    'blog_api_token' => config('services.blog.api_key', ''),
                    'site_url' => config('services.blog.site_url', 'https://blog.sos-expat.com'),
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );

        PublicationSchedule::updateOrCreate(
            ['endpoint_id' => $blogEndpoint->id],
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

        // ── Firestore (legacy, inactive) ──────────────────────────
        $firestoreEndpoint = PublishingEndpoint::updateOrCreate(
            ['name' => 'SOS-Expat Firestore'],
            [
                'type' => 'firestore',
                'config' => [
                    'project_id' => 'sos-urgently-ac307',
                    'collection' => 'blog_articles',
                ],
                'is_active' => false,
                'is_default' => false,
            ]
        );
    }
}
