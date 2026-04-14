<?php

namespace App\Http\Controllers;

use App\Models\LinkedInPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class LinkedInController extends Controller
{
    // ── Stats ──────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        $postsThisWeek = LinkedInPost::whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->orWhereBetween('created_at', [$weekStart, $weekEnd])
            ->count();
        $scheduled     = LinkedInPost::where('status', 'scheduled')->count();
        $published     = LinkedInPost::where('status', 'published')->count();
        $totalReach    = LinkedInPost::where('status', 'published')->sum('reach');
        $avgEngagement = LinkedInPost::where('status', 'published')->avg('engagement_rate') ?? 0;

        $topDay = LinkedInPost::where('status', 'published')
            ->selectRaw('day_type, AVG(engagement_rate) as avg_eng')
            ->groupBy('day_type')
            ->orderByDesc('avg_eng')
            ->value('day_type');

        return response()->json([
            'posts_this_week'      => $postsThisWeek,
            'posts_scheduled'      => $scheduled,
            'posts_published'      => $published,
            'total_reach'          => $totalReach,
            'avg_engagement_rate'  => round($avgEngagement, 2),
            'top_performing_day'   => $topDay ?? 'monday',
        ]);
    }

    // ── Queue ──────────────────────────────────────────────────────────

    public function queue(Request $request): JsonResponse
    {
        $query = LinkedInPost::latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->limit(50)->get());
    }

    // ── Generate with AI ───────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:article,faq,testimonial,news,case_study,tip',
            'source_id'   => 'nullable|integer',
            'day_type'    => 'required|in:monday,tuesday,wednesday,thursday,friday',
            'lang'        => 'required|in:fr,en,both',
            'account'     => 'required|in:page,personal,both',
        ]);

        $sourceContent = $this->fetchSourceContent($request->source_type, $request->source_id);
        $generated     = $this->generateWithAI($request->all(), $sourceContent);

        $post = LinkedInPost::create([
            'source_type'  => $request->source_type,
            'source_id'    => $request->source_id,
            'source_title' => $sourceContent['title'] ?? null,
            'day_type'     => $request->day_type,
            'lang'         => $request->lang,
            'account'      => $request->account,
            'hook'         => $generated['hook'],
            'body'         => $generated['body'],
            'hashtags'     => $generated['hashtags'],
            'status'       => 'draft',
            'phase'        => 1,
        ]);

        return response()->json($post, 201);
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function update(Request $request, LinkedInPost $post): JsonResponse
    {
        $post->update($request->only(['hook', 'body', 'hashtags', 'lang', 'account', 'day_type']));
        return response()->json($post);
    }

    // ── Schedule ───────────────────────────────────────────────────────

    public function schedule(Request $request, LinkedInPost $post): JsonResponse
    {
        $request->validate(['scheduled_at' => 'required|date|after:now']);
        $post->update(['status' => 'scheduled', 'scheduled_at' => $request->scheduled_at]);
        return response()->json($post);
    }

    // ── Publish ────────────────────────────────────────────────────────

    public function publish(LinkedInPost $post): JsonResponse
    {
        // Will call LinkedIn API when OAuth tokens are configured
        // For now, mark as published (manual flow)
        $post->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        // TODO: integrate LinkedIn API v2
        // $this->publishToLinkedIn($post);

        return response()->json($post);
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function destroy(LinkedInPost $post): JsonResponse
    {
        $post->delete();
        return response()->json(['message' => 'Post supprimé']);
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function fetchSourceContent(string $type, ?int $id): array
    {
        if (!$id) {
            return ['title' => null, 'content' => null];
        }

        return match ($type) {
            'article'     => $this->fetchArticle($id),
            'faq'         => $this->fetchFaq($id),
            'testimonial' => $this->fetchTestimonial($id),
            default       => ['title' => null, 'content' => null],
        };
    }

    private function fetchArticle(int $id): array
    {
        try {
            $article = \DB::table('generated_articles')->find($id);
            return ['title' => $article?->title ?? '', 'content' => $article?->content ?? ''];
        } catch (\Throwable) {
            return ['title' => '', 'content' => ''];
        }
    }

    private function fetchFaq(int $id): array
    {
        try {
            $faq = \DB::table('qa_entries')->find($id);
            return ['title' => $faq?->question ?? '', 'content' => $faq?->answer ?? ''];
        } catch (\Throwable) {
            return ['title' => '', 'content' => ''];
        }
    }

    private function fetchTestimonial(int $id): array
    {
        try {
            $t = \DB::table('testimonials')->find($id);
            return ['title' => $t?->title ?? '', 'content' => $t?->content ?? ''];
        } catch (\Throwable) {
            return ['title' => '', 'content' => ''];
        }
    }

    private function generateWithAI(array $params, array $source): array
    {
        $lang      = $params['lang'];
        $dayType   = $params['day_type'];
        $langLabel = $lang === 'fr' ? 'français' : ($lang === 'en' ? 'anglais' : 'français et anglais');

        $dayInstructions = match ($dayType) {
            'monday'    => 'Format carrousel/liste "Les X erreurs/conseils pour les expats". Style pratique et informatif.',
            'tuesday'   => 'Story fictive avec personnage ("Marie/Paul voulait s\'installer à [pays]..."). Hook émotionnel fort.',
            'wednesday' => 'Liste d\'actualité visa/légale. Format "🚨 [N] changements importants". Concis et factuel.',
            'thursday'  => 'Q&A format. Commencer par la question, répondre avec structure claire et valeur ajoutée.',
            'friday'    => 'Témoignage court ou tip rapide. Ton inspirant, finir sur une note positive.',
        };

        $prompt = "Tu es expert en marketing LinkedIn. Écris un post LinkedIn parfait en {$langLabel} pour SOS-Expat (plateforme de mise en relation avec avocats et expats aidants).

RÈGLES ABSOLUES :
- Hook puissant (2-3 premières lignes, avant \"Voir plus\")
- 1200-1800 caractères total
- JAMAIS de lien dans le post (il va en commentaire)
- 3-5 hashtags pertinents (expatriation, expat, etc.)
- CTA doux en fin (pas agressif)
- Style humain, empathique, pratique

JOUR : {$dayType}
INSTRUCTIONS : {$dayInstructions}

SOURCE :
Titre : " . ($source['title'] ?? 'Générer librement') . "
Contenu : " . substr($source['content'] ?? '', 0, 800) . "

Retourne JSON avec : hook (string), body (string, le post complet sans le hook), hashtags (array de strings sans #)";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'max_tokens'  => 1200,
                'temperature' => 0.8,
            ]);

            $result = json_decode($response->json('choices.0.message.content'), true);

            return [
                'hook'     => $result['hook']     ?? $this->defaultHook($dayType, $lang),
                'body'     => $result['body']     ?? $this->defaultBody($dayType, $lang),
                'hashtags' => $result['hashtags'] ?? ['expatriation', 'expat', 'vivraetranger', 'sosexpat'],
            ];
        } catch (\Throwable $e) {
            return [
                'hook'     => $this->defaultHook($dayType, $lang),
                'body'     => $this->defaultBody($dayType, $lang),
                'hashtags' => ['expatriation', 'expat', 'vivraetranger', 'sosexpat'],
            ];
        }
    }

    private function defaultHook(string $day, string $lang): string
    {
        $hooks = [
            'fr' => [
                'monday'    => "5 erreurs que font 90% des expatriés à leur arrivée (et comment les éviter)",
                'tuesday'   => "Elle voulait tout quitter pour s'installer au Vietnam. Voici ce que personne ne lui a dit.",
                'wednesday' => "🚨 3 changements visa importants à connaître ce mois-ci",
                'thursday'  => "La question la plus posée cette semaine : comment ouvrir un compte bancaire à l'étranger ?",
                'friday'    => "Il y a 2 ans, il avait peur de tout quitter. Aujourd'hui, il ne regrette rien.",
            ],
            'en' => [
                'monday'    => "5 mistakes 90% of expats make when they first arrive abroad (and how to avoid them)",
                'tuesday'   => "She wanted to start a new life in Thailand. Here's what nobody told her.",
                'wednesday' => "🚨 3 important visa changes you need to know about this month",
                'thursday'  => "Most asked question this week: how to open a bank account abroad without a fixed address?",
                'friday'    => "2 years ago, he was afraid to leave everything. Today, he has no regrets.",
            ],
        ];

        $langKey = $lang === 'en' ? 'en' : 'fr';
        return $hooks[$langKey][$day] ?? $hooks['fr']['monday'];
    }

    private function defaultBody(string $day, string $lang): string
    {
        return $lang === 'en'
            ? "SOS-Expat helps expats navigate legal and administrative challenges abroad.\n\nOur network of partner lawyers and experienced expat helpers is available to guide you.\n\n→ Whether it's visa issues, tax questions, or simply finding the right contact: we're here.\n\nHave you faced this situation? Share your experience in the comments 👇\n\n(Link in first comment)"
            : "SOS-Expat aide les expatriés à naviguer dans les défis juridiques et administratifs à l'étranger.\n\nNotre réseau d'avocats partenaires et d'expats aidants expérimentés est disponible pour vous guider.\n\n→ Que ce soit pour des questions de visa, de fiscalité ou simplement pour trouver le bon interlocuteur : nous sommes là.\n\nVous avez vécu cette situation ? Partagez votre expérience en commentaire 👇\n\n(Lien en premier commentaire)";
    }
}
