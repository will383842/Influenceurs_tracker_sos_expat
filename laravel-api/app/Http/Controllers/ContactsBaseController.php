<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Unified contacts base — merges all scraped contact tables into a single
 * tiered view with deduplication support.
 *
 * TIERS:
 *   1 = Email vérifié (smtp_valid OR email_verified)
 *   2 = Email présent (non vérifié)
 *   3 = Formulaire / site web uniquement (pas d'email)
 *   4 = Aucun moyen de contact
 */
class ContactsBaseController extends Controller
{
    // ─── STATS ────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $data = Cache::remember('contacts-base-stats', 180, function () {
            // influenceurs
            $inf = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_verified_status = 'verified') as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_verified_status IS NULL OR email_verified_status != 'verified')) as tier2,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND website_url IS NOT NULL AND website_url != '') as tier3,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND (website_url IS NULL OR website_url = '')) as tier4
                FROM influenceurs WHERE deleted_at IS NULL
            ");

            // lawyers
            $law = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_verified = true) as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_verified IS NULL OR email_verified = false)) as tier2,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND website IS NOT NULL AND website != '') as tier3,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND (website IS NULL OR website = '')) as tier4
                FROM lawyers
            ");

            // press_contacts
            $press = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_smtp_valid = true) as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_smtp_valid IS NULL OR email_smtp_valid = false)) as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM press_contacts
            ");

            // content_businesses
            $biz = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE contact_email IS NOT NULL AND contact_email != '') as tier2,
                    COUNT(*) FILTER (WHERE (contact_email IS NULL OR contact_email = '') AND website IS NOT NULL AND website != '') as tier3,
                    COUNT(*) FILTER (WHERE (contact_email IS NULL OR contact_email = '') AND (website IS NULL OR website = '')) as tier4
                FROM content_businesses
            ");

            // content_contacts
            $cc = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM content_contacts
            ");

            // duplicates
            $dupInf = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM influenceurs
                    WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");
            $dupLaw = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM lawyers
                    WHERE email IS NOT NULL AND email != ''
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");
            $dupPress = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM press_contacts
                    WHERE email IS NOT NULL AND email != ''
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");

            return [
                'sources' => [
                    'influenceurs'       => (array) $inf,
                    'lawyers'            => (array) $law,
                    'press_contacts'     => (array) $press,
                    'content_businesses' => (array) $biz,
                    'content_contacts'   => (array) $cc,
                ],
                'totals' => [
                    'all'   => ($inf->total + $law->total + $press->total + $biz->total + $cc->total),
                    'tier1' => ($inf->tier1 + $law->tier1 + $press->tier1),
                    'tier2' => ($inf->tier2 + $law->tier2 + $press->tier2 + $biz->tier2 + $cc->tier2),
                    'tier3' => ($inf->tier3 + $law->tier3 + $biz->tier3),
                    'tier4' => ($inf->tier4 + $law->tier4 + $press->tier4 + $biz->tier4 + $cc->tier4),
                ],
                'duplicates' => [
                    'influenceurs'   => (int) $dupInf->n,
                    'lawyers'        => (int) $dupLaw->n,
                    'press_contacts' => (int) $dupPress->n,
                ],
                'inf_by_type' => DB::select("
                    SELECT contact_type, COUNT(*) as n,
                        COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as with_email
                    FROM influenceurs WHERE deleted_at IS NULL
                    GROUP BY contact_type ORDER BY n DESC
                "),
            ];
        });

        return response()->json($data);
    }

    // ─── UNIFIED LIST ─────────────────────────────────────────────────────────

    public function contacts(Request $request): JsonResponse
    {
        $source = $request->input('source', 'all');   // all | influenceurs | lawyers | press | businesses | content
        $tier   = (int) $request->input('tier', 0);   // 0=all, 1-4
        $type   = $request->input('type', '');
        $search = $request->input('search', '');
        $page   = max(1, (int) $request->input('page', 1));
        $perPage = min(100, (int) $request->input('per_page', 50));
        $offset = ($page - 1) * $perPage;

        $results = [];
        $total   = 0;

        if (in_array($source, ['all', 'influenceurs'])) {
            [$rows, $count] = $this->queryInfluenceurs($tier, $type, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'lawyers'])) {
            [$rows, $count] = $this->queryLawyers($tier, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'press'])) {
            [$rows, $count] = $this->queryPress($tier, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'businesses'])) {
            [$rows, $count] = $this->queryBusinesses($tier, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        // Sort merged results: tier asc, name asc
        usort($results, fn($a, $b) => $a['tier'] <=> $b['tier'] ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return response()->json([
            'data'      => array_slice($results, 0, $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    // ─── DEDUPLICATION ────────────────────────────────────────────────────────

    public function duplicates(Request $request): JsonResponse
    {
        $source = $request->input('source', 'influenceurs');

        if ($source === 'influenceurs') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(name ORDER BY created_at ASC) as names,
                    array_agg(contact_type ORDER BY created_at ASC) as types,
                    array_agg(status ORDER BY created_at ASC) as statuses
                FROM influenceurs
                WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } elseif ($source === 'lawyers') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(full_name ORDER BY created_at ASC) as names,
                    array_agg(country ORDER BY created_at ASC) as countries
                FROM lawyers
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } elseif ($source === 'press') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(full_name ORDER BY created_at ASC) as names,
                    array_agg(publication ORDER BY created_at ASC) as publications
                FROM press_contacts
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } else {
            return response()->json(['error' => 'Source inconnue'], 422);
        }

        return response()->json([
            'source'     => $source,
            'total_groups' => count($groups),
            'groups'     => $groups,
        ]);
    }

    public function deduplicateAuto(Request $request): JsonResponse
    {
        $source   = $request->input('source', 'influenceurs');
        $strategy = $request->input('strategy', 'keep_oldest'); // keep_oldest | keep_most_complete

        $deleted = 0;

        if ($source === 'lawyers') {
            // For lawyers: keep first (oldest), soft-delete or hard-delete duplicates
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM lawyers
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                $toDelete = array_slice($ids, 1); // keep first
                DB::table('lawyers')->whereIn('id', $toDelete)->delete();
                $deleted += count($toDelete);
            }
        } elseif ($source === 'influenceurs') {
            // Soft-delete duplicates (keep oldest or most complete)
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM influenceurs
                WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                if ($strategy === 'keep_most_complete') {
                    // Keep the one with most filled fields - keep last in this simple impl
                    $toDelete = array_slice($ids, 0, -1);
                } else {
                    $toDelete = array_slice($ids, 1);
                }
                DB::table('influenceurs')->whereIn('id', $toDelete)->update(['deleted_at' => now()]);
                $deleted += count($toDelete);
            }
        } elseif ($source === 'press') {
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM press_contacts
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                $toDelete = array_slice($ids, 1);
                DB::table('press_contacts')->whereIn('id', $toDelete)->delete();
                $deleted += count($toDelete);
            }
        }

        Cache::forget('contacts-base-stats');

        return response()->json([
            'message' => "{$deleted} doublons supprimés dans {$source}",
            'deleted' => $deleted,
        ]);
    }

    // ─── PRIVATE QUERY HELPERS ────────────────────────────────────────────────

    private function queryInfluenceurs(int $tier, string $type, string $search, int $limit, int $offset): array
    {
        $where = ["deleted_at IS NULL"];
        $bindings = [];

        if ($tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_verified_status = 'verified'";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_verified_status IS NULL OR email_verified_status != 'verified')";
        elseif ($tier === 3) $where[] = "(email IS NULL OR email = '') AND website_url IS NOT NULL AND website_url != ''";
        elseif ($tier === 4) $where[] = "(email IS NULL OR email = '') AND (website_url IS NULL OR website_url = '')";

        if ($type) { $where[] = "contact_type = ?"; $bindings[] = $type; }
        if ($search) { $where[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM influenceurs WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, email, phone, website_url as website, country, contact_type as type,
                   status, email_verified_status, score, 'influenceurs' as source_table
            FROM influenceurs WHERE {$whereStr}
            ORDER BY score DESC NULLS LAST, name ASC
            LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'influenceurs'), (int) $count];
    }

    private function queryLawyers(int $tier, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_verified = true";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_verified IS NULL OR email_verified = false)";
        elseif ($tier === 3) $where[] = "(email IS NULL OR email = '') AND website IS NOT NULL AND website != ''";
        elseif ($tier === 4) $where[] = "(email IS NULL OR email = '') AND (website IS NULL OR website = '')";

        if ($search) { $where[] = "(LOWER(full_name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM lawyers WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, full_name as name, email, phone, website, country, 'avocat' as type,
                   enrichment_status as status, email_verified::text as email_verified_status,
                   NULL as score, 'lawyers' as source_table
            FROM lawyers WHERE {$whereStr}
            ORDER BY full_name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'lawyers'), (int) $count];
    }

    private function queryPress(int $tier, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_smtp_valid = true";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_smtp_valid IS NULL OR email_smtp_valid = false)";
        elseif ($tier === 4) $where[] = "email IS NULL OR email = ''";

        if ($search) { $where[] = "(LOWER(full_name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(publication) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM press_contacts WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, full_name as name, email, phone, NULL as website, country, 'journaliste' as type,
                   contact_status as status, email_smtp_valid::text as email_verified_status,
                   NULL as score, 'press_contacts' as source_table
            FROM press_contacts WHERE {$whereStr}
            ORDER BY full_name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'press_contacts'), (int) $count];
    }

    private function queryBusinesses(int $tier, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 2) $where[] = "contact_email IS NOT NULL AND contact_email != ''";
        elseif ($tier === 3) $where[] = "(contact_email IS NULL OR contact_email = '') AND website IS NOT NULL AND website != ''";
        elseif ($tier === 4) $where[] = "(contact_email IS NULL OR contact_email = '') AND (website IS NULL OR website = '')";
        if ($tier === 1) $where[] = "1=0"; // no verified emails for businesses

        if ($search) { $where[] = "(LOWER(name) LIKE ? OR LOWER(contact_email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM content_businesses WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, contact_email as email, NULL as phone, website, country, category as type,
                   NULL as status, NULL as email_verified_status,
                   NULL as score, 'content_businesses' as source_table
            FROM content_businesses WHERE {$whereStr}
            ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'content_businesses'), (int) $count];
    }

    private function tagTier(array $rows, string $sourceTable): array
    {
        return array_map(function ($row) use ($sourceTable) {
            $r = (array) $row;
            $hasEmail    = !empty($r['email']);
            $hasWebsite  = !empty($r['website']);
            $isVerified  = $r['email_verified_status'] === 'verified' || $r['email_verified_status'] === 'true';

            if ($hasEmail && $isVerified)       $r['tier'] = 1;
            elseif ($hasEmail && !$isVerified)  $r['tier'] = 2;
            elseif (!$hasEmail && $hasWebsite)  $r['tier'] = 3;
            else                                $r['tier'] = 4;

            return $r;
        }, $rows);
    }
}
