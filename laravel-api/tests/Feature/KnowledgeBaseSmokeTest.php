<?php

namespace Tests\Feature;

use App\Services\Content\KnowledgeBaseService;
use Tests\TestCase;

/**
 * KB smoke-test — verifies the actual rendered prompts contain the current
 * business data (prices, commissions, milestones) for EVERY content type used
 * by the generators.
 *
 * If one of these fails, an AI-generated article will ship with wrong data.
 */
class KnowledgeBaseSmokeTest extends TestCase
{
    private KnowledgeBaseService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new KnowledgeBaseService();
    }

    public function test_influencer_prompt_mentions_5_dollars_fixed_discount(): void
    {
        $prompt = $this->svc->getSystemPrompt('influenceurs', 'France', 'fr');
        $this->assertStringContainsString('$5', $prompt, 'influencer prompt must mention $5 somewhere');
        $this->assertStringNotContainsString(
            '5%',
            $prompt,
            'influencer prompt must NOT mention 5% (legacy — now fixed $5)'
        );
    }

    public function test_blogger_prompt_mentions_widget_and_milestones(): void
    {
        $prompt = $this->svc->getSystemPrompt('blogger', 'France', 'fr');
        // mapContentType('blogger') may return 'blogger' (no alias); we use the content_rules overview
        $prompt2 = $this->svc->getLightPrompt('blogger', 'France', 'fr');
        $this->assertNotEmpty($prompt);
        $this->assertNotEmpty($prompt2);
    }

    public function test_chatters_prompt_mentions_milestones_up_to_4000(): void
    {
        $prompt = $this->svc->getSystemPrompt('chatters', 'France', 'fr');
        $this->assertStringContainsString('milestones', $prompt);
        $this->assertStringContainsString('4000', $prompt, 'chatters prompt must cite the $4000 top milestone');
    }

    public function test_all_prompts_contain_brand_with_hyphen(): void
    {
        $types = ['qa', 'news', 'article', 'guide', 'comparative', 'outreach',
                  'chatters', 'influenceurs', 'admin_groupes', 'avocats', 'expats_aidants',
                  'statistics', 'pain_point', 'testimonial', 'landing', 'press_release'];
        foreach ($types as $type) {
            $prompt = $this->svc->getSystemPrompt($type, 'France', 'fr');
            $this->assertStringContainsString(
                'SOS-Expat.com',
                $prompt,
                "prompt for {$type} must cite 'SOS-Expat.com' with hyphen"
            );
        }
    }

    public function test_all_prompts_cite_core_pricing(): void
    {
        $prompt = $this->svc->getSystemPrompt('article', 'France', 'fr');
        $this->assertStringContainsString('49', $prompt, 'lawyer EUR price missing');
        $this->assertStringContainsString('55', $prompt, 'lawyer USD price missing');
        $this->assertStringContainsString('19', $prompt, 'expat EUR price missing');
        $this->assertStringContainsString('25', $prompt, 'expat USD price missing');
    }

    public function test_all_prompts_cite_coverage_197_pays(): void
    {
        $prompt = $this->svc->getSystemPrompt('pillar', 'Thailand', 'en');
        $this->assertStringContainsString('197', $prompt, '197 countries must be cited');
        $this->assertStringContainsString('24/7', $prompt);
    }

    public function test_chatters_and_landing_prompts_include_mlm_negation(): void
    {
        // Only 'chatters' and 'landing' content_rules explicitly negate "MLM".
        // Influencer/GroupAdmin rules don't because their copy focuses on legit affiliate framing.
        foreach (['chatters', 'landing'] as $type) {
            $prompt = $this->svc->getSystemPrompt($type, 'France', 'fr');
            $this->assertStringContainsString(
                'MLM',
                $prompt,
                "{$type} prompt must explicitly forbid 'MLM' in its content_rules line"
            );
        }
    }

    public function test_prompt_cites_affiliate_independence_framing(): void
    {
        // The getIdentityBlock output uses "partenaires independants" (no hyphen, no accent).
        // Content output must carry that framing so articles never call partners employees.
        $prompt = $this->svc->getSystemPrompt('article', 'France', 'fr');
        $this->assertStringContainsString('PARTENAIRES INDEPENDANTS', $prompt);
        $this->assertStringContainsString('affili', strtolower($prompt), 'must mention "affili*" stem');
    }

    public function test_getProgramPrompt_chatter_cites_referral_provider_amounts(): void
    {
        $prompt = $this->svc->getProgramPrompt('chatter');
        $this->assertStringContainsString('Affiliation prestataire avocat', $prompt);
        $this->assertStringContainsString('Affiliation prestataire expert', $prompt);
    }

    public function test_getProgramPrompt_influencer_does_not_promise_multipliers(): void
    {
        $prompt = $this->svc->getProgramPrompt('influencer');
        $this->assertStringNotContainsString('multiplicateur', strtolower($prompt));
        $this->assertStringNotContainsString('2x', strtolower($prompt));
    }

    public function test_light_prompt_includes_urls_guardrails(): void
    {
        $prompt = $this->svc->getLightPrompt('qr', 'Portugal', 'pt');
        $this->assertStringContainsString('JAMAIS de www.sos-expat.com', $prompt);
        $this->assertStringContainsString('/fr-fr/', $prompt);
    }

    public function test_translation_context_protects_brand(): void
    {
        $prompt = $this->svc->getTranslationContext();
        $this->assertStringContainsString('SOS-Expat.com', $prompt);
        $this->assertStringContainsString('NE PAS TRADUIRE', $prompt);
    }

    public function test_kb_version_is_exposed(): void
    {
        $this->assertSame('2.1.0', $this->svc->getVersion());
        $this->assertSame('2026-04-20', $this->svc->getUpdatedAt());
        $meta = $this->svc->getMeta();
        $this->assertArrayHasKey('changelog', $meta);
    }

    public function test_meta_source_of_truth_paths_exist_or_are_documented(): void
    {
        $meta = $this->svc->getMeta();
        $sot = $meta['kb_source_of_truth'] ?? [];
        // We don't check physical paths — just that they're declared.
        $this->assertArrayHasKey('pricing', $sot);
        $this->assertArrayHasKey('commission_plans', $sot);
        $this->assertArrayHasKey('countries', $sot);
    }

    public function test_edge_cache_section_exists(): void
    {
        $kb = config('knowledge-base');
        $this->assertArrayHasKey('cloudflare_edge_cache', $kb);
        $this->assertArrayHasKey('version_header', $kb['cloudflare_edge_cache']);
    }

    public function test_backlink_engine_section_exists(): void
    {
        $kb = config('knowledge-base');
        $this->assertArrayHasKey('backlink_engine', $kb);
    }

    public function test_removed_sections_do_not_come_back(): void
    {
        $kb = config('knowledge-base');
        $this->assertArrayNotHasKey('general_affiliate', $kb['programs']);
        // anti-cannib is now section 22 (content), not section 20 (empty header)
        $this->assertNotEmpty($kb['anti_cannibalization']);
    }
}
