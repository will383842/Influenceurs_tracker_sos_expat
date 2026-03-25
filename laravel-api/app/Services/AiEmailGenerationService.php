<?php

namespace App\Services;

use App\Models\Influenceur;
use App\Models\OutreachConfig;
use App\Models\OutreachEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiEmailGenerationService
{
    private string $apiKey;
    private string $model;

    // 3 sending domains in rotation
    private const SENDING_DOMAINS = [
        'williams@provider-expat.com',
        'williams@hub-travelers.com',
        'williams@spaceship.com',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Generate a personalized email for a contact at a given step.
     */
    public function generate(Influenceur $inf, int $step = 1): ?OutreachEmail
    {
        if (!$inf->email && !$this->hasContactForm($inf)) {
            Log::debug('AI Email: skipped, no email or form', ['id' => $inf->id]);
            return null;
        }

        $config = OutreachConfig::getFor($inf->contact_type);
        if (!$config->ai_generation_enabled) return null;

        // Check if email already exists for this step
        $existing = OutreachEmail::where('influenceur_id', $inf->id)
            ->where('step', $step)
            ->whereNotIn('status', ['failed'])
            ->first();
        if ($existing) return $existing;

        // Pick sending domain (round-robin based on influenceur ID)
        $fromEmail = self::SENDING_DOMAINS[$inf->id % count(self::SENDING_DOMAINS)];

        // Get contact type label
        $typeLabel = \App\Models\ContactTypeModel::where('value', $inf->contact_type)->value('label') ?? $inf->contact_type;

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($inf, $step, $typeLabel);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1000,
                'system'     => $systemPrompt,
                'messages'   => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            if (!$response->successful()) {
                Log::warning('AI Email generation failed', ['status' => $response->status(), 'id' => $inf->id]);
                return null;
            }

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';
            $promptTokens = $data['usage']['input_tokens'] ?? 0;
            $completionTokens = $data['usage']['output_tokens'] ?? 0;

            // Parse JSON response
            $emailData = $this->parseResponse($text);
            if (!$emailData) {
                Log::warning('AI Email: failed to parse response', ['id' => $inf->id, 'text' => substr($text, 0, 200)]);
                return null;
            }

            // Determine initial status
            $status = $config->auto_send ? 'approved' : 'pending_review';

            $outreachEmail = OutreachEmail::create([
                'influenceur_id'       => $inf->id,
                'step'                 => $step,
                'subject'              => $emailData['subject'],
                'body_html'            => $emailData['body_html'],
                'body_text'            => $emailData['body'],
                'from_email'           => $fromEmail,
                'from_name'            => 'Williams',
                'status'               => $status,
                'ai_generated'         => true,
                'ai_model'             => $this->model,
                'ai_prompt_tokens'     => $promptTokens,
                'ai_completion_tokens' => $completionTokens,
            ]);

            Log::info('AI Email generated', [
                'id'     => $outreachEmail->id,
                'inf_id' => $inf->id,
                'step'   => $step,
                'status' => $status,
                'tokens' => $promptTokens + $completionTokens,
            ]);

            return $outreachEmail;

        } catch (\Throwable $e) {
            Log::error('AI Email generation exception', ['id' => $inf->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate emails for a batch of contacts.
     */
    public function generateBatch(array $influenceurIds, int $step = 1): array
    {
        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($influenceurIds as $id) {
            $inf = Influenceur::find($id);
            if (!$inf) { $stats['skipped']++; continue; }

            $result = $this->generate($inf, $step);
            if ($result) {
                $stats['generated']++;
            } else {
                $stats['skipped']++;
            }

            // Rate limit: 0.5s between API calls
            usleep(500_000);
        }

        return $stats;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un expert en cold emailing B2B, spécialisé dans le secteur des expatriés.
Tu rédiges des emails de prospection pour SOS-Expat, un service qui connecte
les expatriés avec un avocat francophone par téléphone en 5 minutes, dans 197 pays.

CONTEXTE SOS-EXPAT:
- Service: mise en relation téléphonique avec avocat francophone, <5min
- Couverture: 197 pays
- Programmes partenaires: commission 10€/appel généré par vos membres/visiteurs
- Site: www.sos-expat.com
- Fondateur: Williams

RÈGLES D'ÉCRITURE:
1. Ton: Professionnel mais chaleureux, jamais agressif ni "vendeur"
2. Longueur: 4-6 phrases max pour le body (court et impactant)
3. Personnalisation: Utilise le nom du contact, son type d'activité, son pays
4. Pas de: exclamation excessive, emojis, promesses exagérées, "urgent", "offre limitée"
5. CTA: Une seule question claire à la fin (pas de lien, pas de bouton)
6. Signature: Toujours terminer par "Williams\nFondateur, SOS-Expat\nwww.sos-expat.com"
7. Langue: Français par défaut, anglais si la langue du contact est "en"
8. Objet: Court (< 50 caractères), intrigant, pas commercial

RÉPONSE EN JSON STRICT (rien d'autre):
{"subject": "...", "body": "...", "body_html": "<p>...</p>"}
PROMPT;
    }

    private function buildUserPrompt(Influenceur $inf, int $step, string $typeLabel): string
    {
        $stepDesc = match ($step) {
            1 => "Premier contact. Présente SOS-Expat et la proposition de valeur spécifique à ce type de contact. Explique pourquoi un partenariat serait bénéfique pour EUX (pas pour nous).",
            2 => "Relance douce (J+3). Court. Apporte UNE information nouvelle ou un chiffre concret. Ne répète pas le premier email.",
            3 => "Relance (J+7). Très court. Question directe oui/non. Mentionne que c'est ta dernière relance.",
            4 => "Dernier message (J+14). 2-3 phrases max. Dit que tu ne relanceras plus. Laisse la porte ouverte.",
            default => "Premier contact.",
        };

        return <<<PROMPT
Génère l'email pour ce contact:

NOM: {$inf->name}
TYPE: {$inf->contact_type} ({$typeLabel})
PAYS: {$inf->country}
LANGUE: {$inf->language}
ORGANISATION: {$inf->company}
SITE WEB: {$inf->website_url}

STEP {$step}/4: {$stepDesc}
PROMPT;
    }

    private function parseResponse(string $text): ?array
    {
        // Try to extract JSON from the response
        $text = trim($text);

        // Remove markdown code blocks if present
        $text = preg_replace('/^```json?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $data = json_decode($text, true);
        if (!$data || !isset($data['subject']) || !isset($data['body'])) {
            // Try to find JSON in the text
            if (preg_match('/\{[^}]*"subject"[^}]*\}/s', $text, $match)) {
                $data = json_decode($match[0], true);
            }
        }

        if (!$data || !isset($data['subject'])) return null;

        return [
            'subject'   => $data['subject'],
            'body'      => $data['body'] ?? strip_tags($data['body_html'] ?? ''),
            'body_html' => $data['body_html'] ?? '<p>' . nl2br(htmlspecialchars($data['body'] ?? '')) . '</p>',
        ];
    }

    private function hasContactForm(Influenceur $inf): bool
    {
        $social = $inf->scraped_social;
        return is_array($social) && !empty($social['_contact_form_url']);
    }
}
