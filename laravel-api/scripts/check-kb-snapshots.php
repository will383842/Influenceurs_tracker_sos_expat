<?php
// Semantic drift scan across dumped KB prompts.
$storage = __DIR__ . '/../storage/kb-snapshots';
$dirs = glob($storage . '/*', GLOB_ONLYDIR);
usort($dirs, fn($a, $b) => filemtime($b) - filemtime($a));
$latest = $dirs[0] ?? null;
if (!$latest) {
    echo "No snapshots found.\n";
    exit(1);
}
echo "Latest snapshot: " . basename($latest) . "\n";
$index = json_decode(file_get_contents($latest . '/_index.json'), true);
echo "KB version: " . $index['kb_version'] . "\n\n";

$files = glob($latest . '/*.md');
$issues = [];

foreach ($files as $f) {
    $basename = basename($f, '.md');
    $content = file_get_contents($f);
    $flags = [];

    // Affirmative "it's MLM" — bad
    if (preg_match("/\\b(c'est|is)\\s+un\\s+MLM\\b/i", $content)) {
        $flags[] = 'affirmative-MLM';
    }
    // 5% discount mentioned — should now be $5 fixed
    if (preg_match('/\b5%\s*(de remise|discount|reduction)/i', $content)) {
        $flags[] = '5pct-discount-reference-LEGACY';
    }
    // Top 3 multipliers — removed
    if (preg_match('/multiplicateur.+(2\.0|1\.5|1\.15|mois suivant)/i', $content)) {
        $flags[] = 'top3-multiplier-LEGACY';
    }
    // Missing pricing anchors
    if (!preg_match('/\$5|5EUR|5\sUSD|49EUR/', $content)) {
        $flags[] = 'no-price-anchor';
    }
    // France-centric unguarded — only count if NOT in the DO-NOT list
    if (preg_match('/\bPole Emploi\b|\bCPAM\b|\bCFE\b/i', $content)) {
        $blockHints = substr_count($content, 'INTERDIT ABSOLU');
        if ($blockHints === 0) {
            $flags[] = 'france-centric-unguarded';
        }
    }

    if (!empty($flags)) {
        $issues[] = ['file' => $basename, 'flags' => $flags];
    }
}

echo "Semantic check across " . count($files) . " prompts:\n";
if (empty($issues)) {
    echo "  ALL CLEAN — no semantic drift detected\n";
    exit(0);
}
foreach ($issues as $i) {
    echo "  FAIL " . $i['file'] . ": " . implode(', ', $i['flags']) . "\n";
}
exit(1);
