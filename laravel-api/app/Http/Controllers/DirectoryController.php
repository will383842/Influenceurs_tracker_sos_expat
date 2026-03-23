<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeDirectoryJob;
use App\Models\Directory;
use App\Models\Influenceur;
use App\Services\BlockedDomainService;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    /**
     * List directories with filters.
     */
    public function index(Request $request)
    {
        $query = Directory::with('createdBy:id,name');

        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->country) {
            $query->where('country', $request->country);
        }
        if ($request->language) {
            $query->where('language', $request->language);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('url', 'like', "%{$s}%")
                  ->orWhere('domain', 'like', "%{$s}%");
            });
        }

        $directories = $query->orderByDesc('updated_at')->get();

        return response()->json($directories);
    }

    /**
     * Create a new directory entry.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'url'      => 'required|url|max:500',
            'category' => 'required|string|max:50',
            'country'  => 'nullable|string|max:100',
            'language' => 'nullable|string|max:10',
            'notes'    => 'nullable|string',
        ]);

        $domain = Directory::extractDomain($data['url']);

        // Check for duplicate
        $existing = Directory::where('url', $data['url'])
            ->where('category', $data['category'])
            ->first();

        if ($existing) {
            return response()->json([
                'message'  => 'Cet annuaire existe déjà pour cette catégorie.',
                'existing' => $existing,
            ], 409);
        }

        $directory = Directory::create([
            'name'       => $data['name'],
            'url'        => $data['url'],
            'domain'     => $domain,
            'category'   => $data['category'],
            'country'    => $data['country'] ?? null,
            'language'   => $data['language'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'status'     => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return response()->json($directory, 201);
    }

    /**
     * Update a directory.
     */
    public function update(Request $request, Directory $directory)
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'url'      => 'sometimes|url|max:500',
            'category' => 'sometimes|string|max:50',
            'country'  => 'nullable|string|max:100',
            'language' => 'nullable|string|max:10',
            'notes'    => 'nullable|string',
        ]);

        if (isset($data['url'])) {
            $data['domain'] = Directory::extractDomain($data['url']);
        }

        $directory->update($data);

        return response()->json($directory);
    }

    /**
     * Delete a directory.
     */
    public function destroy(Directory $directory)
    {
        $directory->delete();
        return response()->json(null, 204);
    }

    /**
     * Show a single directory with stats.
     */
    public function show(Directory $directory)
    {
        // Count contacts extracted from this directory
        $contactsCount = Influenceur::where('source', 'directory:' . $directory->domain)->count();

        $response = $directory->toArray();
        $response['live_contacts_count'] = $contactsCount;
        $response['is_on_cooldown'] = $directory->isOnCooldown();
        $response['cooldown_remaining'] = $directory->isOnCooldown()
            ? $directory->cooldown_until->diffForHumans()
            : null;

        return response()->json($response);
    }

    /**
     * Launch scraping for a directory.
     */
    public function scrape(Request $request, Directory $directory)
    {
        // Check cooldown
        if ($directory->isOnCooldown()) {
            return response()->json([
                'message'            => 'Cet annuaire est en période de cooldown.',
                'cooldown_until'     => $directory->cooldown_until,
                'cooldown_remaining' => $directory->cooldown_until->diffForHumans(),
            ], 429);
        }

        // Check if already scraping
        if ($directory->status === 'scraping') {
            return response()->json([
                'message' => 'Cet annuaire est déjà en cours de scraping.',
            ], 409);
        }

        // Force rescrape option (bypass cooldown)
        $force = $request->boolean('force', false);
        if ($force) {
            $directory->update(['cooldown_until' => null]);
        }

        // Dispatch the job
        ScrapeDirectoryJob::dispatch($directory->id);

        $directory->update(['status' => 'pending']);

        return response()->json([
            'message' => 'Scraping lancé pour : ' . $directory->name,
            'directory' => $directory->fresh(),
        ]);
    }

    /**
     * List contacts extracted from a directory.
     */
    public function contacts(Directory $directory)
    {
        $contacts = Influenceur::where('source', 'directory:' . $directory->domain)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'name', 'email', 'phone', 'website_url', 'country', 'status', 'created_at']);

        return response()->json($contacts);
    }

    /**
     * Get aggregated stats across all directories.
     */
    public function stats()
    {
        $total = Directory::count();
        $byStatus = Directory::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $byCategory = Directory::selectRaw('category, COUNT(*) as count, SUM(contacts_created) as total_contacts')
            ->groupBy('category')
            ->get();
        $totalContacts = Directory::sum('contacts_created');

        return response()->json([
            'total_directories' => $total,
            'total_contacts_created' => $totalContacts,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Batch scrape: launch scraping for multiple directories.
     */
    public function batchScrape(Request $request)
    {
        $data = $request->validate([
            'directory_ids' => 'required|array|min:1|max:20',
            'directory_ids.*' => 'integer|exists:directories,id',
        ]);

        $launched = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data['directory_ids'] as $id) {
            $directory = Directory::find($id);
            if (!$directory) continue;

            if ($directory->isOnCooldown()) {
                $skipped++;
                $errors[] = "{$directory->name}: en cooldown";
                continue;
            }
            if ($directory->status === 'scraping') {
                $skipped++;
                $errors[] = "{$directory->name}: déjà en cours";
                continue;
            }

            ScrapeDirectoryJob::dispatch($directory->id);
            $directory->update(['status' => 'pending']);
            $launched++;
        }

        return response()->json([
            'launched' => $launched,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }
}
