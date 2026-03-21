<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Models\EmailTemplate;
use App\Models\Influenceur;
use App\Services\OutreachService;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * List all templates, optionally filtered.
     */
    public function index(Request $request)
    {
        $query = EmailTemplate::query();

        if ($request->contact_type) {
            $query->where('contact_type', $request->contact_type);
        }
        if ($request->language) {
            $query->where('language', $request->language);
        }
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(
            $query->orderBy('contact_type')
                ->orderBy('language')
                ->orderBy('step')
                ->get()
        );
    }

    /**
     * Get a single template.
     */
    public function show(EmailTemplate $template)
    {
        return response()->json($template);
    }

    /**
     * Create a template (admin only).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_type' => 'required|in:' . implode(',', ContactType::values()),
            'language'     => 'required|string|max:10',
            'name'         => 'required|string|max:255',
            'subject'      => 'required|string|max:500',
            'body'         => 'required|string',
            'variables'    => 'nullable|array',
            'step'         => 'sometimes|integer|min:1|max:10',
            'delay_days'   => 'sometimes|integer|min:0|max:365',
        ]);

        $template = EmailTemplate::create($data);

        return response()->json($template, 201);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, EmailTemplate $template)
    {
        $data = $request->validate([
            'contact_type' => 'sometimes|in:' . implode(',', ContactType::values()),
            'language'     => 'sometimes|string|max:10',
            'name'         => 'sometimes|string|max:255',
            'subject'      => 'sometimes|string|max:500',
            'body'         => 'sometimes|string',
            'variables'    => 'nullable|array',
            'is_active'    => 'sometimes|boolean',
            'step'         => 'sometimes|integer|min:1|max:10',
            'delay_days'   => 'sometimes|integer|min:0|max:365',
        ]);

        $template->update($data);

        return response()->json($template);
    }

    /**
     * Delete a template.
     */
    public function destroy(EmailTemplate $template)
    {
        $template->delete();
        return response()->json(null, 204);
    }

    /**
     * Preview a template rendered with an influenceur's data.
     */
    public function preview(Request $request, EmailTemplate $template)
    {
        $request->validate([
            'influenceur_id' => 'required|exists:influenceurs,id',
        ]);

        $influenceur = Influenceur::findOrFail($request->influenceur_id);
        $outreach = new OutreachService();
        $message = $outreach->generateMessage($influenceur, $template->step);

        return response()->json($message);
    }

    /**
     * Generate outreach message for a specific influenceur.
     */
    public function generateForInfluenceur(Request $request, Influenceur $influenceur)
    {
        $step = $request->query('step', 1);
        $outreach = new OutreachService();
        $message = $outreach->generateMessage($influenceur, (int) $step);

        if (!$message) {
            return response()->json([
                'message' => 'Aucun template trouvé pour ce type de contact.',
            ], 404);
        }

        return response()->json($message);
    }

    /**
     * Generate outreach messages for a batch of influenceurs.
     */
    public function generateBatch(Request $request)
    {
        $data = $request->validate([
            'influenceur_ids'   => 'required|array|min:1|max:50',
            'influenceur_ids.*' => 'exists:influenceurs,id',
            'step'              => 'sometimes|integer|min:1',
        ]);

        $outreach = new OutreachService();
        $results = $outreach->generateBatch($data['influenceur_ids'], $data['step'] ?? 1);

        return response()->json($results);
    }
}
