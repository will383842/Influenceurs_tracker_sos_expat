<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $isResearcher = $request->user()->role === 'researcher';
        $userId       = $request->user()->id;

        // Base query scoped for researchers
        $baseInfluenceurQuery = Influenceur::query();
        if ($isResearcher) {
            $baseInfluenceurQuery->where('created_by', $userId);
        }

        // Totaux par statut
        $total    = (clone $baseInfluenceurQuery)->count();
        $byStatus = (clone $baseInfluenceurQuery)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Taux de réponse
        $contacted    = (clone $baseInfluenceurQuery)->whereIn('status', ['contacted', 'negotiating', 'active', 'refused', 'inactive'])->count();
        $repliedQuery = Contact::where('result', 'replied');
        if ($isResearcher) {
            $repliedQuery->whereHas('influenceur', fn($q) => $q->where('created_by', $userId));
        }
        $replied      = $repliedQuery->distinct('influenceur_id')->count('influenceur_id');
        $responseRate = $contacted > 0 ? round($replied / $contacted * 100, 1) : 0;

        // Taux de conversion
        $active         = (clone $baseInfluenceurQuery)->where('status', 'active')->count();
        $prospects      = (clone $baseInfluenceurQuery)->where('status', 'prospect')->count();
        $conversionRate = ($prospects + $active) > 0
            ? round($active / ($prospects + $active) * 100, 1)
            : 0;

        $newThisMonth = (clone $baseInfluenceurQuery)->where('created_at', '>=', now()->startOfMonth())->count();

        // Évolution contacts (12 semaines)
        $contactsEvolutionQuery = Contact::select(
            DB::raw("TO_CHAR(date, 'IYYY-IW') as week"),
            DB::raw('count(*) as count')
        )
            ->where('date', '>=', now()->subWeeks(12));
        if ($isResearcher) {
            $contactsEvolutionQuery->whereHas('influenceur', fn($q) => $q->where('created_by', $userId));
        }
        $contactsEvolution = $contactsEvolutionQuery
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Répartition plateformes
        $byPlatform = (clone $baseInfluenceurQuery)->select('primary_platform', DB::raw('count(*) as count'))
            ->groupBy('primary_platform')
            ->orderByDesc('count')
            ->get();

        // Taux de réponse par plateforme
        $responseByPlatformQuery = DB::table('contacts')
            ->join('influenceurs', 'contacts.influenceur_id', '=', 'influenceurs.id');
        if ($isResearcher) {
            $responseByPlatformQuery->where('influenceurs.created_by', $userId);
        }
        $responseByPlatform = $responseByPlatformQuery
            ->select(
                'influenceurs.primary_platform',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when contacts.result = 'replied' then 1 else 0 end) as replied")
            )
            ->groupBy('influenceurs.primary_platform')
            ->get()
            ->map(fn($r) => [
                'platform' => $r->primary_platform,
                'rate'     => $r->total > 0 ? round($r->replied / $r->total * 100, 1) : 0,
                'total'    => $r->total,
            ]);

        // Activité équipe ce mois
        $teamActivityQuery = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->where('action', 'contact_added')
            ->where('created_at', '>=', now()->startOfMonth());
        if ($isResearcher) {
            $teamActivityQuery->where('user_id', $userId);
        }
        $teamActivity = $teamActivityQuery
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get();

        // Funnel conversion
        $funnel = [
            ['stage' => 'Prospect',     'count' => $byStatus['prospect']     ?? 0],
            ['stage' => 'Contacté',     'count' => $byStatus['contacted']    ?? 0],
            ['stage' => 'Négociation',  'count' => $byStatus['negotiating']  ?? 0],
            ['stage' => 'Actif',        'count' => $byStatus['active']       ?? 0],
        ];

        // 10 dernières activités
        $recentActivityQuery = ActivityLog::with(['user:id,name', 'influenceur:id,name'])
            ->orderByDesc('created_at')
            ->limit(10);
        if ($isResearcher) {
            $recentActivityQuery->where('user_id', $userId);
        }
        $recentActivity = $recentActivityQuery->get();

        return response()->json(compact(
            'total', 'byStatus', 'responseRate', 'conversionRate',
            'newThisMonth', 'active', 'contactsEvolution',
            'byPlatform', 'responseByPlatform', 'teamActivity',
            'funnel', 'recentActivity'
        ));
    }

    /**
     * Admin-only: stats for all researchers.
     */
    public function researcherStats()
    {
        $researchers = User::where('role', 'researcher')
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'last_login_at', 'created_at')
            ->get();

        $stats = $researchers->map(function ($researcher) {
            $baseQuery = Influenceur::where('created_by', $researcher->id);

            $totalCreated = (clone $baseQuery)->count();
            $validCount   = (clone $baseQuery)->validForObjective()->count();
            $createdToday = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            $createdThisWeek = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();
            $createdThisMonth = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            // Active objectives with progress
            $objectives = Objective::where('user_id', $researcher->id)
                ->active()
                ->orderByDesc('created_at')
                ->get();

            $objectivesData = $objectives->map(function ($objective) use ($researcher) {
                $query = Influenceur::where('created_by', $researcher->id)
                    ->validForObjective();

                if ($objective->country) {
                    $query->where('country', $objective->country);
                }
                if ($objective->language) {
                    $query->where('language', $objective->language);
                }
                if ($objective->niche) {
                    $query->where('niche', $objective->niche);
                }

                $currentCount = $query->count();
                $daysRemaining = max(0, (int) now()->startOfDay()->diffInDays($objective->deadline, false));

                return [
                    'id'             => $objective->id,
                    'country'        => $objective->country,
                    'language'       => $objective->language,
                    'niche'          => $objective->niche,
                    'target_count'   => $objective->target_count,
                    'deadline'       => $objective->deadline->toDateString(),
                    'current_count'  => $currentCount,
                    'percentage'     => $objective->target_count > 0
                        ? round($currentCount / $objective->target_count * 100, 1)
                        : 0,
                    'days_remaining' => $daysRemaining,
                ];
            });

            return [
                'id'                 => $researcher->id,
                'name'               => $researcher->name,
                'email'              => $researcher->email,
                'last_login_at'      => $researcher->last_login_at?->toIso8601String(),
                'total_created'      => $totalCreated,
                'valid_count'        => $validCount,
                'created_today'      => $createdToday,
                'created_this_week'  => $createdThisWeek,
                'created_this_month' => $createdThisMonth,
                'objectives'         => $objectivesData,
            ];
        });

        return response()->json($stats);
    }
}
