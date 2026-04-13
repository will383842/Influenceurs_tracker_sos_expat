<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Country Campaign — full coverage of 197 sovereign states.
 *
 * Sets the queue with:
 *  1. User priority order (11 countries): TH → VN → SG → MY → PH → JP → AU → MX → BR → CR → US
 *  2. Then the 186 remaining countries in a deterministic shuffled order
 *     (seed = 42, fixed so the migration is reproducible across environments)
 *
 * Also raises campaign_articles_per_country from 220 to 240 (200 SEO + 40 brand
 * SOS-Expat.com: 12 brand-info + 8 brand-conversion + 20 brand pain-solution).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. User-defined priority order (must come first in the queue)
        $priority = [
            'TH', 'VN', 'SG', 'MY', 'PH', 'JP', 'AU', 'MX', 'BR', 'CR', 'US',
        ];

        // 2. All 186 other ISO 3166-1 alpha-2 codes for sovereign states
        $others = [
            // Europe
            'AL','AD','AT','BE','BA','BG','HR','CY','CZ','DK','EE','FI','FR','DE',
            'GR','HU','IS','IE','IT','XK','LV','LI','LT','LU','MT','MD','MC','ME',
            'NL','MK','NO','PL','PT','RO','RU','SM','RS','SK','SI','ES','SE','CH',
            'UA','GB','VA','BY',
            // Asia
            'AF','AM','AZ','BH','BD','BT','BN','KH','CN','GE','HK','IN','ID','IR',
            'IQ','IL','JO','KZ','KP','KR','KW','KG','LA','LB','MO','MV','MN','MM',
            'NP','OM','PK','PS','QA','SA','LK','SY','TW','TJ','TL','TR','TM','AE',
            'UZ','YE',
            // Americas
            'AG','AR','BS','BB','BZ','BO','CA','CL','CO','CU','DM','DO','EC','SV',
            'GD','GT','GY','HT','HN','JM','NI','PA','PY','PE','KN','LC','VC','SR',
            'TT','UY','VE',
            // Africa
            'DZ','AO','BJ','BW','BF','BI','CM','CV','CF','TD','KM','CD','CG','CI',
            'DJ','EG','GQ','ER','SZ','ET','GA','GM','GH','GN','GW','KE','LS','LR',
            'LY','MG','MW','ML','MR','MU','MA','MZ','NA','NE','NG','RW','ST','SN',
            'SC','SL','SO','ZA','SS','SD','TZ','TG','TN','UG','ZM','ZW',
            // Oceania
            'FJ','KI','MH','FM','NR','NZ','PW','PG','WS','SB','TO','TV','VU',
        ];

        // Deterministic shuffle (seed=42) — reproducible across environments
        mt_srand(42);
        for ($i = count($others) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$others[$i], $others[$j]] = [$others[$j], $others[$i]];
        }
        mt_srand(); // restore default seeding

        $fullQueue = array_values(array_unique(array_merge($priority, $others)));

        DB::table('content_orchestrator_config')->update([
            'campaign_country_queue'        => json_encode($fullQueue),
            'campaign_articles_per_country' => 240,
            'updated_at'                    => now(),
        ]);
    }

    public function down(): void
    {
        // Revert to the previous 30-country queue and the previous 220 threshold
        $previousQueue = [
            'TH','US','VN','SG','PT','ES','ID','MX','MA','AE',
            'JP','DE','GB','CA','AU','BR','CO','CR','GR','HR',
            'IT','NL','BE','CH','TR','PH','MY','KH','IN','PL',
        ];

        DB::table('content_orchestrator_config')->update([
            'campaign_country_queue'        => json_encode($previousQueue),
            'campaign_articles_per_country' => 220,
            'updated_at'                    => now(),
        ]);
    }
};
