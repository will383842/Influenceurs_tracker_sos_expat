<?php

namespace Database\Seeders;

use App\Models\ContentTemplate;
use App\Models\ContentTemplateItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Import SOS-Expat keyword strategy into content_templates.
 *
 * 2 types:
 * - Generic keywords (562 articles) -> Templates with expansion_mode='manual', items pre-loaded
 * - Country-specific templates (37 × 197 pays) -> Templates with expansion_mode='all_countries'
 *
 * Run: php artisan db:seed --class=KeywordTemplatesSeeder
 */
class KeywordTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $this->importGenericKeywords();
        $this->importCountryTemplates();
    }

    /**
     * Import 562 generic keyword articles grouped by cluster.
     */
    private function importGenericKeywords(): void
    {
        $csvPath = database_path('data/SOS_Expat_KW_GENERIC.csv');
        if (! file_exists($csvPath)) {
            $this->command->warn("CSV not found: {$csvPath}");
            $this->command->info("Copy SOS_Expat_KW_GENERIC.csv to database/data/");
            return;
        }

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);

        // Group by cluster
        $byCluster = [];
        while (($row = fgetcsv($handle)) !== false) {
            $cluster = $row[1] ?? 'Uncategorized';
            $byCluster[$cluster][] = [
                'primary_keyword' => $row[3] ?? '',
                'secondary_keywords' => $row[4] ?? '',
                'intent' => $row[6] ?? 'transactionnel',
            ];
        }
        fclose($handle);

        $totalItems = 0;

        foreach ($byCluster as $cluster => $keywords) {
            // Clean cluster name for template name
            $cleanName = preg_replace('/^\d+\.\s*/', '', $cluster);

            $template = ContentTemplate::updateOrCreate(
                ['name' => "KW - {$cleanName}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => "Mots-cles generiques: {$cleanName} ({$this->count($keywords)} articles)",
                    'preset_type' => 'mots-cles',
                    'content_type' => 'article',
                    'title_template' => '{mot_cle}',
                    'variables' => json_encode([['name' => 'mot_cle', 'type' => 'keyword', 'required' => true]]),
                    'expansion_mode' => 'manual',
                    'expansion_values' => '[]',
                    'language' => 'fr',
                    'tone' => 'professional',
                    'article_length' => 'medium',
                    'generation_instructions' => "Article SEO complet pour expatries, voyageurs et vacanciers. Cluster: {$cleanName}. Donnees chiffrees, exemples concrets, liens vers services SOS-Expat. Featured snippet en premier paragraphe.",
                    'generate_faq' => true,
                    'faq_count' => 6,
                    'research_sources' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => true,
                    'auto_translate' => true,
                    'image_source' => 'unsplash',
                    'is_active' => true,
                ]
            );

            // Add keyword items
            foreach ($keywords as $kw) {
                $title = $kw['primary_keyword'];
                if (empty($title)) continue;

                ContentTemplateItem::updateOrCreate(
                    ['template_id' => $template->id, 'expanded_title' => $title],
                    [
                        'variable_values' => json_encode([
                            'mot_cle' => $title,
                            'secondary' => $kw['secondary_keywords'],
                            'intent' => $kw['intent'],
                        ]),
                        'status' => 'pending',
                    ]
                );
                $totalItems++;
            }

            // Update stats
            $template->update([
                'total_items' => $template->items()->count(),
            ]);
        }

        $this->command->info("Generic keywords: {$totalItems} items imported into " . count($byCluster) . " templates");
    }

    /**
     * Import 37 country-specific keyword templates (× 197 pays).
     */
    private function importCountryTemplates(): void
    {
        $csvPath = database_path('data/SOS_Expat_KW_PAYS.csv');
        if (! file_exists($csvPath)) {
            $this->command->warn("CSV not found: {$csvPath}");
            $this->command->info("Copy SOS_Expat_KW_PAYS.csv to database/data/");
            return;
        }

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);

        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $cluster = $row[1] ?? '';
            $sub = $row[2] ?? '';
            $titleTemplate = $row[3] ?? '';
            $intent = $row[4] ?? 'informationnel';

            if (empty($titleTemplate)) continue;

            ContentTemplate::updateOrCreate(
                ['name' => "Pays - {$cluster} - {$sub}"],
                [
                    'uuid' => (string) Str::uuid(),
                    'description' => "Template pays: {$titleTemplate}",
                    'preset_type' => 'visa-pays',
                    'content_type' => 'article',
                    'title_template' => $titleTemplate,
                    'variables' => json_encode([['name' => 'pays', 'type' => 'country', 'required' => true]]),
                    'expansion_mode' => 'all_countries',
                    'expansion_values' => '[]',
                    'language' => 'fr',
                    'tone' => 'professional',
                    'article_length' => $intent === 'urgence' ? 'short' : 'long',
                    'generation_instructions' => "Article {$cluster}/{$sub} specifique au pays. Donnees locales obligatoires: montants en devise locale + EUR, noms d'organismes officiels, liens gouvernementaux. Intent: {$intent}. CTA vers SOS-Expat pour assistance.",
                    'generate_faq' => true,
                    'faq_count' => 6,
                    'research_sources' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => true,
                    'auto_translate' => true,
                    'image_source' => 'unsplash',
                    'is_active' => true,
                    'total_items' => 197,
                ]
            );
            $count++;
        }
        fclose($handle);

        $this->command->info("Country templates: {$count} templates created (each expands to 197 pays)");
    }

    private function count(array $arr): int
    {
        return count($arr);
    }
}
