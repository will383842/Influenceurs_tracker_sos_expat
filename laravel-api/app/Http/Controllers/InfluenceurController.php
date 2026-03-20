<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Influenceur;
use Illuminate\Http\Request;

class InfluenceurController extends Controller
{
    public function index(Request $request)
    {
        $query = Influenceur::with(['assignedToUser:id,name', 'pendingReminder']);

        // Researcher scoping: only see own influenceurs
        if ($request->user()->isResearcher()) {
            $query->where('created_by', $request->user()->id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->platform) {
            $query->whereJsonContains('platforms', $request->platform);
        }
        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->has_reminder) {
            $query->whereHas('pendingReminder');
        }
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('handle', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 30), 100);
        $results = $query->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->cursor);

        return response()->json([
            'data'        => $results->items(),
            'next_cursor' => $results->nextCursor()?->encode(),
            'has_more'    => $results->hasMorePages(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'handle'               => 'nullable|string|max:255',
            'avatar_url'           => 'nullable|url|max:500',
            'platforms'            => 'required|array|min:1',
            'platforms.*'          => 'string|in:instagram,tiktok,youtube,linkedin,x,facebook,pinterest,podcast,blog,newsletter',
            'primary_platform'     => 'required|string|max:50',
            'followers'            => 'nullable|integer|min:0',
            'followers_secondary'  => 'nullable|array',
            'niche'                => 'nullable|string|max:255',
            'country'              => 'nullable|string|max:100',
            'language'             => 'nullable|string|max:10',
            'email'                => 'nullable|email',
            'phone'                => 'nullable|string|max:50',
            'profile_url'          => 'nullable|string|max:500',
            'status'               => 'sometimes|in:prospect,contacted,negotiating,active,refused,inactive',
            'assigned_to'          => 'nullable|exists:users,id',
            'reminder_days'        => 'sometimes|integer|min:1|max:365',
            'notes'                => 'nullable|string',
            'tags'                 => 'nullable|array',
            'force_duplicate'      => 'sometimes|boolean',
        ]);

        $forceDuplicate = $data['force_duplicate'] ?? false;
        unset($data['force_duplicate']);

        $data['created_by'] = $request->user()->id;

        // Extract and store normalized profile URL domain
        if (!empty($data['profile_url'])) {
            $data['profile_url_domain'] = self::normalizeProfileUrl($data['profile_url']);
        }

        // Duplicate check on profile_url_domain
        $duplicateWarning = null;
        if (!empty($data['profile_url_domain'])) {
            $existing = Influenceur::where('profile_url_domain', $data['profile_url_domain'])->first();
            if ($existing && !$forceDuplicate) {
                return response()->json([
                    'warning'             => 'duplicate_detected',
                    'message'             => "Un influenceur avec un profil similaire existe déjà : {$existing->name} (ID {$existing->id}).",
                    'existing_id'         => $existing->id,
                    'existing_name'       => $existing->name,
                    'profile_url_domain'  => $data['profile_url_domain'],
                ], 409);
            }
            if ($existing && $forceDuplicate) {
                $duplicateWarning = "Doublon créé malgré l'existant : {$existing->name} (ID {$existing->id}).";
            }
        }

        if (($data['status'] ?? 'prospect') === 'active') {
            $data['partnership_date'] = $data['partnership_date'] ?? now()->toDateString();
        }

        $influenceur = Influenceur::create($data);

        ActivityLog::create([
            'user_id'         => $request->user()->id,
            'influenceur_id'  => $influenceur->id,
            'action'          => 'created',
            'details'         => ['name' => $influenceur->name],
        ]);

        $response = $influenceur->load('assignedToUser:id,name')->toArray();
        if ($duplicateWarning) {
            $response['duplicate_warning'] = $duplicateWarning;
        }

        // Check if valid for objective counting
        $isValid = !empty($influenceur->profile_url)
            && !empty($influenceur->name)
            && !empty($influenceur->profile_url_domain)
            && (!empty($influenceur->email) || !empty($influenceur->phone));
        $response['is_valid_for_objective'] = $isValid;

        return response()->json($response, 201);
    }

    public function show(Request $request, Influenceur $influenceur)
    {
        // Researcher can only see own influenceurs
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        return response()->json($influenceur->load([
            'assignedToUser:id,name',
            'createdBy:id,name',
            'contacts.user:id,name',
            'pendingReminder',
        ]));
    }

    public function update(Request $request, Influenceur $influenceur)
    {
        // Researcher can only update own influenceurs
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'handle'              => 'nullable|string|max:255',
            'avatar_url'          => 'nullable|url|max:500',
            'platforms'           => 'sometimes|array|min:1',
            'platforms.*'         => 'string|in:instagram,tiktok,youtube,linkedin,x,facebook,pinterest,podcast,blog,newsletter',
            'primary_platform'    => 'sometimes|string|max:50',
            'followers'           => 'nullable|integer|min:0',
            'followers_secondary' => 'nullable|array',
            'niche'               => 'nullable|string|max:255',
            'country'             => 'nullable|string|max:100',
            'language'            => 'nullable|string|max:10',
            'email'               => 'nullable|email',
            'phone'               => 'nullable|string|max:50',
            'profile_url'         => 'nullable|string|max:500',
            'status'              => 'sometimes|in:prospect,contacted,negotiating,active,refused,inactive',
            'assigned_to'         => 'nullable|exists:users,id',
            'reminder_days'       => 'sometimes|integer|min:1|max:365',
            'reminder_active'     => 'sometimes|boolean',
            'notes'               => 'nullable|string',
            'tags'                => 'nullable|array',
        ]);

        // Re-extract domain if profile_url changed
        if (isset($data['profile_url'])) {
            $data['profile_url_domain'] = !empty($data['profile_url'])
                ? self::normalizeProfileUrl($data['profile_url'])
                : null;
        }

        $oldStatus = $influenceur->status;

        if (isset($data['status']) && $data['status'] === 'active'
            && $oldStatus !== 'active'
            && !$influenceur->partnership_date) {
            $data['partnership_date'] = now()->toDateString();
        }

        $influenceur->update($data);

        $action = (isset($data['status']) && $data['status'] !== $oldStatus)
            ? 'status_changed'
            : 'updated';

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => $action,
            'details'        => $data,
        ]);

        return response()->json($influenceur->load('assignedToUser:id,name'));
    }

    public function destroy(Request $request, Influenceur $influenceur)
    {
        // Admin can delete any, researcher can only delete own, member denied
        $role = $request->user()->role;
        if ($role === 'member') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if ($role === 'researcher' && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => null,
            'action'         => 'deleted',
            'details'        => ['name' => $influenceur->name, 'id' => $influenceur->id],
        ]);

        $influenceur->delete();

        return response()->json(null, 204);
    }

    public function remindersPending(Request $request)
    {
        $query = Influenceur::with(['pendingReminder', 'assignedToUser:id,name'])
            ->whereHas('pendingReminder');

        // Researcher scoping
        if ($request->user()->isResearcher()) {
            $query->where('created_by', $request->user()->id);
        }

        $influenceurs = $query->get()
            ->map(function ($inf) {
                $daysElapsed = $inf->last_contact_at
                    ? (int) now()->diffInDays($inf->last_contact_at)
                    : null;

                return array_merge($inf->toArray(), ['days_elapsed' => $daysElapsed]);
            })
            ->sortByDesc('days_elapsed')
            ->values();

        return response()->json($influenceurs);
    }

    /**
     * Smart URL normalization: extract the profile/channel URL from video/post URLs.
     * Used for duplicate detection and objective validation.
     */
    public static function normalizeProfileUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        // Remove protocol, www, trailing slash, query params, fragments
        $url = preg_replace('#^https?://(www\.)?#', '', $url);
        $url = rtrim($url, '/');
        $url = preg_replace('#\?.*$#', '', $url);
        $url = preg_replace('#\#.*$#', '', $url);

        // TikTok: keep only @username
        if (str_contains($url, 'tiktok.com/')) {
            if (preg_match('#tiktok\.com/(@[^/]+)#', $url, $m)) {
                return 'tiktok.com/' . $m[1];
            }
        }

        // YouTube: keep channel/username, strip video paths
        if (str_contains($url, 'youtube.com/') || str_contains($url, 'youtu.be/')) {
            if (preg_match('#youtube\.com/(@[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            if (preg_match('#youtube\.com/(channel/[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            if (preg_match('#youtube\.com/(c/[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            // watch?v= or youtu.be/xxx — can't extract channel, keep as-is
            return $url;
        }

        // Instagram: keep username only
        if (str_contains($url, 'instagram.com/')) {
            if (preg_match('#instagram\.com/([a-zA-Z0-9_.]+)#', $url, $m)) {
                $username = $m[1];
                // Skip non-profile paths
                if (!in_array($username, ['p', 'reel', 'reels', 'stories', 'explore', 'tv'])) {
                    return 'instagram.com/' . $username;
                }
            }
        }

        // LinkedIn: keep profile path
        if (str_contains($url, 'linkedin.com/')) {
            if (preg_match('#linkedin\.com/(in/[^/]+)#', $url, $m)) {
                return 'linkedin.com/' . $m[1];
            }
            if (preg_match('#linkedin\.com/(company/[^/]+)#', $url, $m)) {
                return 'linkedin.com/' . $m[1];
            }
        }

        // Facebook: keep profile/page path
        if (str_contains($url, 'facebook.com/')) {
            if (preg_match('#facebook\.com/([a-zA-Z0-9.]+)#', $url, $m)) {
                $name = $m[1];
                if (!in_array($name, ['watch', 'video', 'videos', 'photo', 'photos', 'events', 'groups'])) {
                    return 'facebook.com/' . $name;
                }
            }
        }

        // X/Twitter
        if (str_contains($url, 'twitter.com/') || str_contains($url, 'x.com/')) {
            if (preg_match('#(?:twitter|x)\.com/([a-zA-Z0-9_]+)#', $url, $m)) {
                $username = $m[1];
                if (!in_array($username, ['status', 'i', 'intent', 'search', 'hashtag'])) {
                    return 'x.com/' . $username;
                }
            }
        }

        // Default: return cleaned URL
        return $url;
    }

    /**
     * Legacy alias — calls normalizeProfileUrl.
     */
    public static function normalizeUrlDomain(?string $url): ?string
    {
        return self::normalizeProfileUrl($url);
    }
}
