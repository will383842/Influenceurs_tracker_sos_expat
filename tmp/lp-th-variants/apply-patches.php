<?php
/**
 * Apply Opus 4.7 TH variant patches to DB.
 * Each patch file contains { parent_id, variants: { lang: { meta_title, meta_description, hero, earnings|topics, freedom|intro, process, faq, cta } } }
 *
 * Run: docker exec inf-app php /tmp/lp-variants/apply-patches.php
 */
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LandingPage;

$files = glob('/tmp/lp-variants/patches-*.json');
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (!$data || empty($data['parent_id']) || empty($data['variants'])) {
        echo "SKIP $file — invalid\n";
        continue;
    }
    $pid = $data['parent_id'];
    echo "=== Patching parent #$pid from " . basename($file) . " ===\n";

    foreach ($data['variants'] as $lang => $v) {
        $row = LandingPage::where('parent_id', $pid)->where('language', $lang)->first();
        if (!$row) {
            echo "  ! $lang — row not found, skipping\n";
            continue;
        }

        // Build the sections array. Key names match template Blade accessors.
        $sections = [
            ['type' => 'hero',     'content' => $v['hero']],
        ];
        if (!empty($v['earnings']))  $sections[] = ['type' => 'earnings',  'content' => $v['earnings']];
        if (!empty($v['freedom']))   $sections[] = ['type' => 'freedom',   'content' => $v['freedom']];
        if (!empty($v['intro']))     $sections[] = ['type' => 'intro',     'content' => $v['intro']];
        if (!empty($v['topics']))    $sections[] = ['type' => 'topics',    'content' => $v['topics']];
        if (!empty($v['categories']))$sections[] = ['type' => 'visa_categories', 'content' => $v['categories']];
        if (!empty($v['process']))   $sections[] = ['type' => 'process',   'content' => $v['process']];
        if (!empty($v['faq']))       $sections[] = ['type' => 'faq',       'content' => $v['faq']];
        if (!empty($v['cta']))       $sections[] = ['type' => 'cta',       'content' => $v['cta']];

        // DB limit varchar(170). Truncate on character boundary to stay safe.
        $title = mb_substr($v['meta_title'], 0, 170);
        $desc  = mb_substr($v['meta_description'], 0, 170);
        $row->meta_title = $title;
        $row->meta_description = $desc;
        $row->og_title = $title;
        $row->og_description = $desc;
        $row->twitter_title = $title;
        $row->twitter_description = $desc;
        $row->sections = $sections;
        $row->generation_source = 'manual';  // promoted above deterministic_backfill
        $row->date_modified_at = now();
        $row->save();

        echo "  ✓ $lang → LP #{$row->id} patched (" . count($sections) . " sections, seo={$row->seo_score})\n";
    }
}

echo "\nDone.\n";
