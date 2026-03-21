<?php

namespace App\Http\Controllers;

use App\Models\ContactTypeModel;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get scraper configuration.
     */
    public function scraperConfig()
    {
        $globalEnabled = Setting::getBool('scraper_enabled', false);
        $types = ContactTypeModel::orderBy('sort_order')
            ->get(['value', 'label', 'icon', 'scraper_enabled']);

        return response()->json([
            'global_enabled' => $globalEnabled,
            'types'          => $types,
        ]);
    }

    /**
     * Update scraper configuration.
     */
    public function updateScraperConfig(Request $request)
    {
        $data = $request->validate([
            'global_enabled' => 'sometimes|boolean',
            'types'          => 'sometimes|array',
            'types.*'        => 'boolean',
        ]);

        // Global toggle
        if (isset($data['global_enabled'])) {
            Setting::set('scraper_enabled', $data['global_enabled'] ? 'true' : 'false');
        }

        // Per-type toggles: { "school": true, "youtuber": false }
        if (isset($data['types'])) {
            foreach ($data['types'] as $typeValue => $enabled) {
                ContactTypeModel::where('value', $typeValue)
                    ->update(['scraper_enabled' => $enabled]);
            }
            ContactTypeModel::flushCache();
        }

        return $this->scraperConfig();
    }
}
