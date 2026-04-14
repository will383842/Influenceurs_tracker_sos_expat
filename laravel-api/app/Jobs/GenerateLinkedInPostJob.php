<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\LinkedInPost;
use App\Models\QaEntry;
use App\Services\AI\ClaudeService;
use App\Services\Content\AudienceContextService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLinkedInPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;

    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $postId)
    {
        $this->onQueue('linkedin');
    }

    public function handle(ClaudeService $claude, KnowledgeBaseService $kb): void
    {
        $post = LinkedInPost::find($this->postId);
        if (!$post) {
            Log::warning('GenerateLinkedInPostJob: post not found', ['id' => $this->postId]);
            return;
        }

        try {
            $lang    = $post->lang === 'both' ? 'fr' : $post->lang;
            $dayType = $post->day_type;

            // ── 1. Fetch source content ──────────────────────────────
            $source = $this->fetchSource($post->source_type, $post->source_id, $lang);

            // ── 2. Build system prompt ───────────────────────────────
            $kbContext       = $kb->getLightPrompt('linkedin', null, $lang);
            $audienceContext = AudienceContextService::getContextFor($lang);
            $dayInstructions = $this->getDayInstructions($dayType, $lang);
            $langLabel       = $lang === 'fr' ? 'français' : 'English';

            $systemPrompt = <<<SYSTEM
{$kbContext}

{$audienceContext}

Tu es un expert LinkedIn de niveau international (top 1% des créateurs LinkedIn 2026).
Tu crées du contenu LinkedIn parfait pour SOS-Expat.com — la plateforme de mise en relation
entre expatriés et avocats/experts locaux dans 197 pays.

RÈGLES ABSOLUES LINKEDIN 2026 :
- Hook IRRÉSISTIBLE sur 2-3 lignes (avant "Voir plus" — max 140 caractères)
- Corps total (hook + body) : 1200-1800 caractères
- JAMAIS de lien dans le post (le lien va en 1er commentaire)
- 3-5 hashtags de niche pertinents — pas de hashtags génériques comme #business
- CTA doux et authentique — jamais commercial ni agressif
- Style : humain, empathique, conversationnel, pratique
- Texte brut LinkedIn (INTERDIT : Markdown, **, ##, *, _)
- Ligne vide entre chaque paragraphe (lisibilité mobile)
- Terminer par une question ouverte pour générer des commentaires
- Toujours mentionner SOS-Expat.com comme ressource (avec le .com)
SYSTEM;

            $userPrompt = <<<USER
Génère un post LinkedIn en {$langLabel} pour SOS-Expat.com.

JOUR : {$dayType}
FORMAT OBLIGATOIRE : {$dayInstructions}

SOURCE :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}

Retourne UNIQUEMENT un objet JSON valide avec exactement ces 3 clés :
{
  "hook": "2-3 lignes d'accroche avant Voir plus (max 140 caractères, aucun saut de ligne)",
  "body": "Corps complet du post sans le hook (900-1500 caractères, sauts de ligne réels avec \\n)",
  "hashtags": ["expatriation", "expat", "sosexpat", "vivraetranger"]
}
USER;

            // ── 3. Generate with Claude Haiku ────────────────────────
            $result = $claude->complete($systemPrompt, $userPrompt, [
                'model'       => 'claude-haiku-4-5-20251001',
                'max_tokens'  => 1500,
                'temperature' => 0.75,
                'json_mode'   => true,
            ]);

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'Claude API error');
            }

            $data = json_decode($result['content'] ?? '', true) ?? [];

            // ── 4. Derive hashtags from source keywords if AI returned empty ──
            $hashtags = $data['hashtags'] ?? [];
            if (empty($hashtags) && !empty($source['hashtag_seeds'])) {
                $hashtags = array_slice($source['hashtag_seeds'], 0, 4);
            }
            if (empty($hashtags)) {
                $hashtags = ['expatriation', 'expat', 'vivraetranger', 'sosexpat'];
            }
            // Sanitize: strip # prefix if present, lowercase
            $hashtags = array_map(fn($h) => strtolower(ltrim(trim($h), '#')), $hashtags);
            $hashtags = array_values(array_unique(array_filter($hashtags)));

            // ── 5. Update post ───────────────────────────────────────
            $post->update([
                'hook'          => $data['hook'] ?? $this->defaultHook($dayType, $lang),
                'body'          => $data['body'] ?? $this->defaultBody($lang),
                'hashtags'      => $hashtags,
                'status'        => 'draft',
                'error_message' => null,
            ]);

            Log::info('GenerateLinkedInPostJob: done', [
                'post_id' => $post->id,
                'day'     => $dayType,
                'lang'    => $lang,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateLinkedInPostJob: failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
            $post->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e; // let queue handle retry
        }
    }

    // ── Source resolution ──────────────────────────────────────────────

    private function fetchSource(string $type, ?int $id, string $lang): array
    {
        $empty = ['title' => 'SOS-Expat.com', 'content' => '', 'keywords' => 'expatriation, expat, visa, étranger', 'hashtag_seeds' => []];

        // Auto-select if no explicit source_id
        if (!$id) {
            return match ($type) {
                'article' => $this->bestArticle($lang) ?? $empty,
                'faq'     => $this->bestFaq($lang) ?? $empty,
                default   => $empty, // tip / news / case_study → free generation
            };
        }

        return match ($type) {
            'article' => $this->fetchArticle($id) ?? $empty,
            'faq'     => $this->fetchFaq($id) ?? $empty,
            default   => $empty,
        };
    }

    private function bestArticle(string $lang): ?array
    {
        $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'article')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $article = GeneratedArticle::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('editorial_score')
            ->first();

        return $article ? $this->articleToSource($article) : null;
    }

    private function bestFaq(string $lang): ?array
    {
        $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'faq')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $faq = QaEntry::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('seo_score')
            ->first();

        return $faq ? $this->faqToSource($faq) : null;
    }

    private function fetchArticle(int $id): ?array
    {
        try {
            $article = GeneratedArticle::find($id);
            return $article ? $this->articleToSource($article) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchFaq(int $id): ?array
    {
        try {
            $faq = QaEntry::find($id);
            return $faq ? $this->faqToSource($faq) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function articleToSource(GeneratedArticle $article): array
    {
        $plainText = strip_tags($article->content_html ?? '');
        $plainText = trim(preg_replace('/\s+/', ' ', $plainText));
        $plainText = substr($plainText, 0, 800);

        $primary   = $article->keywords_primary ?? '';
        $secondary = is_array($article->keywords_secondary)
            ? implode(', ', array_slice($article->keywords_secondary, 0, 5))
            : '';
        $allKeys   = trim($primary . ($secondary ? ', ' . $secondary : ''));

        $seeds = array_filter(array_map('trim', explode(',', $allKeys)));
        $seeds = array_slice(array_values($seeds), 0, 5);

        return [
            'title'         => $article->title ?? '',
            'content'       => $plainText,
            'keywords'      => $allKeys,
            'hashtag_seeds' => $seeds,
        ];
    }

    private function faqToSource(QaEntry $faq): array
    {
        $answer  = ($faq->answer_short ?? '') . ' ' . strip_tags($faq->answer_detailed_html ?? '');
        $answer  = trim(preg_replace('/\s+/', ' ', $answer));
        $answer  = substr($answer, 0, 800);

        $primary   = $faq->keywords_primary ?? '';
        $secondary = is_array($faq->keywords_secondary)
            ? implode(', ', array_slice($faq->keywords_secondary, 0, 4))
            : '';
        $allKeys   = trim($primary . ($secondary ? ', ' . $secondary : ''));

        $seeds = array_filter(array_map('trim', explode(',', $allKeys)));
        $seeds = array_slice(array_values($seeds), 0, 4);

        return [
            'title'         => $faq->question ?? '',
            'content'       => $answer,
            'keywords'      => $allKeys,
            'hashtag_seeds' => $seeds,
        ];
    }

    // ── Day-type instructions ──────────────────────────────────────────

    private function getDayInstructions(string $day, string $lang): string
    {
        $fr = [
            'monday'    => 'Format carrousel/liste : "Les X erreurs / conseils pour les expats". Commencer par un chiffre choc. Style pratique, liste numérotée ou à puces dans le corps.',
            'tuesday'   => 'Story fictive : un personnage type ("Marie voulait s\'installer au Vietnam..."). Hook émotionnel fort. Situation → problème → résolution grâce à SOS-Expat.',
            'wednesday' => 'Actualité légale/visa. Format "🚨 [N] changements importants". Concis, factuel, chiffres précis. Mentionner que SOS-Expat suit ces évolutions en temps réel.',
            'thursday'  => 'Q&A format. Commencer par la question d\'un expat. Répondre : contexte → points clés → conseil pratique. Valeur ajoutée maximale.',
            'friday'    => 'Témoignage court OU tip rapide et actionnable. Ton inspirant et positif. Finir sur une note d\'espoir ou de fierté.',
        ];

        $en = [
            'monday'    => '"X mistakes / tips for expats" carousel format. Start with a shocking stat. Practical style, numbered or bulleted list.',
            'tuesday'   => 'Fictional story: a typical character ("Sarah wanted to move to Thailand..."). Strong emotional hook. Situation → problem → resolution via SOS-Expat.',
            'wednesday' => 'Legal/visa news. Format "🚨 [N] important changes". Concise, factual, precise figures. Mention SOS-Expat tracks these changes in real time.',
            'thursday'  => 'Q&A format. Start with an expat question. Answer: context → key points → practical advice. Maximum added value.',
            'friday'    => 'Short testimonial OR quick actionable tip. Inspiring, positive tone. End on a hopeful or proud note.',
        ];

        $map = ($lang === 'en') ? $en : $fr;
        return $map[$day] ?? 'Post LinkedIn professionnel pour SOS-Expat.com.';
    }

    // ── Fallbacks ──────────────────────────────────────────────────────

    private function defaultHook(string $day, string $lang): string
    {
        $hooks = [
            'fr' => [
                'monday'    => "5 erreurs que font 90% des expatriés à leur arrivée (et comment les éviter) 👇",
                'tuesday'   => "Elle voulait tout quitter pour s'installer au Vietnam. Voici ce que personne ne lui a dit.",
                'wednesday' => "🚨 3 changements visa importants à connaître ce mois-ci",
                'thursday'  => "La question la plus posée cette semaine : comment ouvrir un compte bancaire à l'étranger ?",
                'friday'    => "Il y a 2 ans, il avait peur de tout quitter. Aujourd'hui, il ne regrette rien. ✈️",
            ],
            'en' => [
                'monday'    => "5 mistakes 90% of expats make when they first arrive (and how to avoid them) 👇",
                'tuesday'   => "She wanted to start a new life in Thailand. Here's what nobody told her.",
                'wednesday' => "🚨 3 important visa changes you need to know about this month",
                'thursday'  => "Most asked question this week: how to open a bank account abroad without a fixed address?",
                'friday'    => "2 years ago, he was afraid to leave everything. Today, he has zero regrets. ✈️",
            ],
        ];

        $key = ($lang === 'en') ? 'en' : 'fr';
        return $hooks[$key][$day] ?? $hooks['fr']['monday'];
    }

    private function defaultBody(string $lang): string
    {
        return $lang === 'en'
            ? "SOS-Expat helps expats navigate legal and administrative challenges abroad.\n\nOur network of partner lawyers and experienced expat helpers is available to guide you step by step.\n\n→ Visa questions, tax issues, finding the right contact: we cover it all in 197 countries.\n\nHave you faced this situation? Share your experience in the comments 👇\n\n(Link in first comment)"
            : "SOS-Expat aide les expatriés à naviguer dans les défis juridiques et administratifs à l'étranger.\n\nNotre réseau d'avocats partenaires et d'expats aidants est disponible pour vous guider pas à pas.\n\n→ Questions de visa, fiscalité, trouver le bon interlocuteur dans un nouveau pays : on couvre tout dans 197 pays.\n\nVous avez vécu cette situation ? Partagez votre expérience en commentaire 👇\n\n(Lien en premier commentaire)";
    }
}
