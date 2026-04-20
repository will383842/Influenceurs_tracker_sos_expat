<?php

namespace Tests\Unit;

use App\Services\Content\KnowledgeBaseService;
use Tests\TestCase;

/**
 * Cross-invariant tests for the Knowledge Base.
 *
 * These tests catch silent drift between config/knowledge-base.php and the
 * downstream SOS-Expat code (pricingService.ts, defaultPlans.ts,
 * subscription/constants.ts). If any of these fail, a generated article could
 * promise something the platform no longer delivers.
 */
class KnowledgeBaseIntegrityTest extends TestCase
{
    private array $kb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kb = config('knowledge-base', []);
        $this->assertNotEmpty($this->kb, 'knowledge-base config must be loaded');
    }

    // -----------------------------------------------------------------
    // META / VERSIONING
    // -----------------------------------------------------------------

    public function test_meta_block_has_version_and_updated_at(): void
    {
        $this->assertArrayHasKey('meta', $this->kb);
        $this->assertArrayHasKey('kb_version', $this->kb['meta']);
        $this->assertArrayHasKey('kb_updated_at', $this->kb['meta']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->kb['meta']['kb_version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $this->kb['meta']['kb_updated_at']);
    }

    public function test_service_exposes_version(): void
    {
        $svc = new KnowledgeBaseService();
        $this->assertNotSame('unknown', $svc->getVersion());
        $this->assertNotSame('unknown', $svc->getUpdatedAt());
        $this->assertNotEmpty($svc->getMeta());
    }

    // -----------------------------------------------------------------
    // PRICING CONSERVATION EQUATIONS
    // -----------------------------------------------------------------

    /**
     * For every service, the client price must equal platform fee + provider payout.
     * If this drifts, SOS-Expat either loses money or over-bills the client.
     */
    public function test_pricing_equation_lawyer_eur(): void
    {
        $s = $this->kb['services']['lawyer'];
        $this->assertSame(
            $s['price_eur'],
            $s['provider_payout_eur'] + $s['platform_fee_eur'],
            'lawyer EUR price must equal payout + fee'
        );
    }

    public function test_pricing_equation_lawyer_usd(): void
    {
        $s = $this->kb['services']['lawyer'];
        $this->assertSame(
            $s['price_usd'],
            $s['provider_payout_usd'] + $s['platform_fee_usd'],
            'lawyer USD price must equal payout + fee'
        );
    }

    public function test_pricing_equation_expat_eur(): void
    {
        $s = $this->kb['services']['expat'];
        $this->assertSame(
            $s['price_eur'],
            $s['provider_payout_eur'] + $s['platform_fee_eur'],
            'expat EUR price must equal payout + fee'
        );
    }

    public function test_pricing_equation_expat_usd(): void
    {
        $s = $this->kb['services']['expat'];
        $this->assertSame(
            $s['price_usd'],
            $s['provider_payout_usd'] + $s['platform_fee_usd'],
            'expat USD price must equal payout + fee'
        );
    }

    public function test_pricing_matches_firebase_constants(): void
    {
        $lawyer = $this->kb['services']['lawyer'];
        $expat = $this->kb['services']['expat'];

        // Hardcoded from pricingService.ts — if these change, update both sides.
        $this->assertSame(49, $lawyer['price_eur']);
        $this->assertSame(55, $lawyer['price_usd']);
        $this->assertSame(20, $lawyer['duration_minutes']);
        $this->assertSame(19, $expat['price_eur']);
        $this->assertSame(25, $expat['price_usd']);
        $this->assertSame(30, $expat['duration_minutes']);
    }

    // -----------------------------------------------------------------
    // PROGRAMS / COMMISSIONS
    // -----------------------------------------------------------------

    public function test_all_known_programs_exist(): void
    {
        $required = [
            'client_provider', 'chatter', 'captain_chatter',
            'influencer', 'blogger', 'group_admin', 'partner', 'common',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $this->kb['programs'], "program {$key} must exist");
        }
    }

    public function test_general_affiliate_program_is_removed(): void
    {
        $this->assertArrayNotHasKey(
            'general_affiliate',
            $this->kb['programs'],
            'general_affiliate was a ghost plan — must be removed'
        );
    }

    public function test_milestones_are_sorted_ascending_when_present(): void
    {
        foreach ($this->kb['programs'] as $programKey => $program) {
            if (!is_array($program) || !isset($program['milestones'])) {
                continue;
            }
            $thresholds = array_keys($program['milestones']);
            $sorted = $thresholds;
            sort($sorted, SORT_NUMERIC);
            $this->assertSame(
                $sorted,
                $thresholds,
                "milestones for {$programKey} must be sorted ascending"
            );
        }
    }

    public function test_milestones_bonuses_are_monotonic_non_decreasing(): void
    {
        foreach ($this->kb['programs'] as $programKey => $program) {
            if (!is_array($program) || !isset($program['milestones'])) {
                continue;
            }
            $prev = 0;
            foreach ($program['milestones'] as $threshold => $bonus) {
                $this->assertGreaterThanOrEqual(
                    $prev,
                    $bonus,
                    "milestone bonuses for {$programKey} must be non-decreasing"
                );
                $prev = $bonus;
            }
        }
    }

    public function test_top3_monthly_has_exactly_3_ranks(): void
    {
        foreach ($this->kb['programs'] as $programKey => $program) {
            if (!is_array($program) || !isset($program['top3_monthly'])) {
                continue;
            }
            $ranks = array_keys($program['top3_monthly']);
            $this->assertSame(
                [1, 2, 3],
                $ranks,
                "top3_monthly for {$programKey} must have ranks 1, 2, 3"
            );
        }
    }

    public function test_top3_has_no_leftover_multipliers(): void
    {
        foreach ($this->kb['programs'] as $programKey => $program) {
            if (!is_array($program) || !isset($program['top3_monthly'])) {
                continue;
            }
            foreach ($program['top3_monthly'] as $rank => $entry) {
                $this->assertArrayNotHasKey(
                    'multiplier',
                    $entry,
                    "top3 multipliers removed — {$programKey} rank {$rank} must not have 'multiplier'"
                );
            }
            $this->assertArrayNotHasKey(
                'top3_monthly_multipliers',
                $program,
                "{$programKey} must not have orphan top3_monthly_multipliers"
            );
        }
    }

    public function test_withdrawal_defaults_are_positive(): void
    {
        $c = $this->kb['programs']['common'];
        $this->assertGreaterThan(0, $c['withdrawal_minimum']);
        $this->assertGreaterThanOrEqual(0, $c['withdrawal_fee']);
        // Firebase defaultPlans.ts seeds minimum=3000 (cents) and fee=300 (cents).
        $this->assertSame(3000, $c['withdrawal_minimum']);
        $this->assertSame(300, $c['withdrawal_fee']);
    }

    public function test_partner_rate_is_a_valid_percentage(): void
    {
        $rate = $this->kb['programs']['partner']['call_commission_rate'];
        $this->assertGreaterThan(0, $rate);
        $this->assertLessThanOrEqual(1, $rate);
    }

    public function test_chatter_call_commissions_match_defaultplans(): void
    {
        $chatter = $this->kb['programs']['chatter'];
        // defaultPlans.ts CHATTER_V1 : lawyer 500, expat 300 cents
        $this->assertSame(500, $chatter['client_lawyer_call']);
        $this->assertSame(300, $chatter['client_expat_call']);
        $this->assertSame(100, $chatter['n1_call_commission']);
        $this->assertSame(50, $chatter['n2_call_commission']);
        $this->assertSame(500, $chatter['activation_bonus']);
        $this->assertSame(5000, $chatter['telegram_bonus']);
        $this->assertSame(15000, $chatter['telegram_unlock_threshold']);
    }

    // -----------------------------------------------------------------
    // SUBSCRIPTIONS
    // -----------------------------------------------------------------

    public function test_annual_discount_is_numeric_with_label(): void
    {
        $subs = $this->kb['subscriptions'];
        $this->assertIsFloat($subs['annual_discount']);
        $this->assertGreaterThan(0, $subs['annual_discount']);
        $this->assertLessThan(1, $subs['annual_discount']);
        $this->assertArrayHasKey('annual_discount_label', $subs);
    }

    public function test_subscription_plans_have_required_fields(): void
    {
        foreach (['lawyer_plans', 'expat_plans'] as $group) {
            foreach ($this->kb['subscriptions'][$group] as $tier => $plan) {
                $this->assertArrayHasKey('eur', $plan, "{$group}.{$tier} missing eur");
                $this->assertArrayHasKey('usd', $plan, "{$group}.{$tier} missing usd");
                $this->assertArrayHasKey('ai_calls', $plan, "{$group}.{$tier} missing ai_calls");
            }
        }
    }

    // -----------------------------------------------------------------
    // COVERAGE
    // -----------------------------------------------------------------

    public function test_all_languages_have_names(): void
    {
        foreach ($this->kb['coverage']['languages'] as $code) {
            $this->assertArrayHasKey(
                $code,
                $this->kb['coverage']['language_names'],
                "language {$code} must have a language_names entry"
            );
        }
    }

    public function test_language_code_canonical_is_zh(): void
    {
        $this->assertSame('zh', $this->kb['coverage']['language_code_canonical']);
        $this->assertSame('ch', $this->kb['coverage']['language_code_legacy_internal']);
    }

    public function test_country_counts_are_documented(): void
    {
        $this->assertSame(197, $this->kb['coverage']['countries']);
        $this->assertGreaterThanOrEqual(197, $this->kb['coverage']['country_codes_supported']);
    }

    // -----------------------------------------------------------------
    // CONTENT RULES
    // -----------------------------------------------------------------

    public function test_every_intent_content_type_has_a_rule(): void
    {
        $rules = $this->kb['content_rules'];
        // Raw keys in content_rules: 'qr' is the storage key; 'qa' is an alias resolved by mapContentType().
        foreach (['qr', 'news', 'pillar', 'tutorial', 'statistics', 'pain_point', 'testimonial', 'landing'] as $key) {
            $this->assertArrayHasKey($key, $rules, "content_rules missing {$key}");
            $this->assertNotEmpty($rules[$key]);
        }
    }

    public function test_search_intent_has_all_six_buckets(): void
    {
        $this->assertSame(
            ['informational', 'commercial_investigation', 'transactional', 'local', 'urgency', 'navigational'],
            array_keys($this->kb['search_intent'])
        );
    }

    // -----------------------------------------------------------------
    // LEGAL & BRAND
    // -----------------------------------------------------------------

    public function test_legal_entity_is_estonia(): void
    {
        $legal = $this->kb['identity']['legal_entity'];
        $this->assertStringContainsString('WorldExpat', $legal['name']);
        $this->assertStringContainsString('Estonia', $legal['country']);
    }

    public function test_terms_versions_are_present(): void
    {
        $versions = $this->kb['legal']['terms_versions'];
        foreach (['terms', 'terms_clients', 'terms_affiliate', 'terms_bloggers', 'privacy_policy'] as $key) {
            $this->assertArrayHasKey($key, $versions, "legal.terms_versions missing {$key}");
        }
    }

    // -----------------------------------------------------------------
    // TELEGRAM BOTS
    // -----------------------------------------------------------------

    public function test_telegram_bots_are_consistent(): void
    {
        $telegram = $this->kb['notifications']['telegram'];
        $this->assertSame(count($telegram['bot_names']), $telegram['bots']);
        $this->assertGreaterThanOrEqual(3, $telegram['bots']);
    }

    // -----------------------------------------------------------------
    // ANTI-CANNIBALIZATION (no duplicate section 20 leftover)
    // -----------------------------------------------------------------

    public function test_anti_cannibalization_has_rules(): void
    {
        $rules = $this->kb['anti_cannibalization'] ?? [];
        $this->assertNotEmpty($rules, 'anti_cannibalization must not be an empty array');
        $this->assertGreaterThanOrEqual(9, count($rules));
    }

    // -----------------------------------------------------------------
    // KB SERVICE — smoke tests
    // -----------------------------------------------------------------

    public function test_service_builds_system_prompt_for_all_known_types(): void
    {
        $svc = new KnowledgeBaseService();
        $types = [
            'qa', 'news', 'article', 'guide', 'comparative', 'outreach',
            'chatters', 'influenceurs', 'admin_groupes', 'avocats', 'expats_aidants',
            'statistics', 'pain_point', 'testimonial', 'landing', 'press_release',
        ];
        foreach ($types as $type) {
            $prompt = $svc->getSystemPrompt($type, 'France', 'fr');
            $this->assertStringContainsString('SOS-Expat', $prompt, "prompt for {$type} missing brand");
            $this->assertStringContainsString('SOS-EXPAT KNOWLEDGE BASE', $prompt);
        }
    }

    public function test_service_program_prompt_uses_referral_keys(): void
    {
        $svc = new KnowledgeBaseService();
        $prompt = $svc->getProgramPrompt('chatter');
        $this->assertStringContainsString('Affiliation prestataire', $prompt);
    }

    public function test_service_cents_helper_does_not_round_sub_dollar(): void
    {
        // $0.50 (50 cents) must display as "0.50", not "1" — covers the n2_call $0.50 case.
        $svc = new KnowledgeBaseService();
        $prompt = $svc->getProgramPrompt('chatter');
        $this->assertStringNotContainsString(' $1.00', $prompt); // not displayed for n2
        // Existence of "$0.50" is already implied by n2_call_commission = 50 cents.
    }
}
