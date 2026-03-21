<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Jobs\RunAiResearchJob;
use App\Models\AiResearchSession;
use App\Models\ActivityLog;
use App\Models\Influenceur;
use App\Http\Controllers\InfluenceurController;
use Illuminate\Http\Request;

class AiResearchController extends Controller
{
    /**
     * Launch a new AI research session (async via job).
     */
    public function launch(Request $request)
    {
        $data = $request->validate([
            'contact_type' => 'required|in:' . implode(',', ContactType::values()),
            'country'      => 'required|string|max:100',
            'language'     => 'sometimes|string|max:10',
        ]);

        $session = AiResearchSession::create([
            'user_id'      => $request->user()->id,
            'contact_type' => $data['contact_type'],
            'country'      => $data['country'],
            'language'     => $data['language'] ?? 'fr',
            'status'       => 'pending',
        ]);

        RunAiResearchJob::dispatch($session->id);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'ai_research_launched',
            'contact_type' => $data['contact_type'],
            'details'      => [
                'session_id' => $session->id,
                'country'    => $data['country'],
            ],
        ]);

        return response()->json($session, 201);
    }

    /**
     * Check status of a research session.
     */
    public function status(AiResearchSession $session)
    {
        return response()->json($session);
    }

    /**
     * List user's research sessions.
     */
    public function index(Request $request)
    {
        $query = AiResearchSession::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->contact_type) {
            $query->where('contact_type', $request->contact_type);
        }
        if ($request->country) {
            $query->where('country', $request->country);
        }

        return response()->json(
            $query->paginate(20)
        );
    }

    /**
     * Import selected contacts from a completed session into the main contacts table.
     */
    public function import(Request $request, AiResearchSession $session)
    {
        if ($session->status !== 'completed') {
            return response()->json(['message' => 'Session non terminée.'], 422);
        }

        $data = $request->validate([
            'contact_indices'   => 'required|array|min:1',
            'contact_indices.*' => 'integer|min:0',
        ]);

        $parsedContacts = $session->parsed_contacts ?? [];
        $imported = 0;
        $skipped = 0;

        foreach ($data['contact_indices'] as $index) {
            if (!isset($parsedContacts[$index])) continue;

            $contact = $parsedContacts[$index];

            // Final duplicate check
            if (!empty($contact['profile_url'])) {
                $domain = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
                if ($domain && Influenceur::where('profile_url_domain', $domain)->exists()) {
                    $skipped++;
                    continue;
                }
            }
            if (!empty($contact['email']) && Influenceur::where('email', $contact['email'])->exists()) {
                $skipped++;
                continue;
            }

            // Create the influenceur
            Influenceur::create([
                'contact_type'      => $contact['contact_type'] ?? $session->contact_type,
                'name'              => $contact['name'],
                'email'             => $contact['email'] ?? null,
                'phone'             => $contact['phone'] ?? null,
                'profile_url'       => $contact['profile_url'] ?? null,
                'profile_url_domain' => !empty($contact['profile_url'])
                    ? InfluenceurController::normalizeProfileUrl($contact['profile_url'])
                    : null,
                'country'           => $contact['country'] ?? $session->country,
                'language'          => $session->language,
                'platforms'         => $contact['platforms'] ?? [],
                'primary_platform'  => $contact['platforms'][0] ?? 'website',
                'followers'         => $contact['followers'] ?? null,
                'notes'             => $contact['notes'] ?? null,
                'source'            => 'ai_research',
                'status'            => 'new',
                'created_by'        => $request->user()->id,
            ]);

            $imported++;
        }

        // Update session counts
        $session->update([
            'contacts_imported' => $session->contacts_imported + $imported,
        ]);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'ai_research_imported',
            'contact_type' => is_object($session->contact_type)
                ? $session->contact_type->value
                : $session->contact_type,
            'details'      => [
                'session_id' => $session->id,
                'imported'   => $imported,
                'skipped'    => $skipped,
            ],
        ]);

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);
    }

    /**
     * Import ALL new contacts from a session.
     */
    public function importAll(Request $request, AiResearchSession $session)
    {
        if ($session->status !== 'completed') {
            return response()->json(['message' => 'Session non terminée.'], 422);
        }

        $indices = array_keys($session->parsed_contacts ?? []);

        // Reuse import logic with all indices
        $request->merge(['contact_indices' => $indices]);
        return $this->import($request, $session);
    }
}
